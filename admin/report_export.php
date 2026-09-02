<?php
/**
 * Report Export — Feliciano Restaurant
 * Supports: CSV download, Print-ready HTML (PDF via browser)
 */
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once '../config/database.php';
require_once '../config/rbac.php';
require_once '../config/settings_helper.php';

// Auth check
$allowed_panel_roles = ['admin','manager','cashier'];
if (!isset($_SESSION['user_logged_in']) || !in_array($_SESSION['user_role'] ?? '', $allowed_panel_roles)) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}
rbac_init($conn);
if (!rbac_can('analytics')) {
    http_response_code(403);
    die('Access denied');
}

$site_settings = get_restaurant_settings($conn);
$restaurant_name = $site_settings['restaurant_name'] ?? 'Feliciano Restaurant';

$type   = $_GET['type']   ?? 'sales';       // sales|orders|customers|reservations|inventory|salary|tax
$format = $_GET['format'] ?? 'csv';          // csv|print
$start  = $_GET['start']  ?? date('Y-m-01');
$end    = $_GET['end']    ?? date('Y-m-d');

// Sanitize dates
$start = date('Y-m-d', strtotime($start));
$end   = date('Y-m-d', strtotime($end));

// ── Data Fetchers ─────────────────────────────────────────────────────────────

function fetch_sales($conn, $start, $end) {
    $stmt = $conn->prepare("
        SELECT
            DATE(created_at)            AS date,
            COUNT(*)                    AS total_orders,
            SUM(total_amount)           AS revenue,
            AVG(total_amount)           AS avg_order,
            SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END) AS completed_revenue,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_orders($conn, $start, $end) {
    $stmt = $conn->prepare("
        SELECT order_id, customer_name, customer_email, customer_phone,
               order_type, total_amount, status, payment_status,
               payment_method, created_at
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_customers($conn) {
    return $conn->query("
        SELECT u.full_name, u.email, u.phone, u.status, u.created_at,
               COALESCE(c.total_orders, 0) as total_orders,
               COALESCE(c.total_spent, 0) as total_spent,
               c.last_order_date
        FROM users u
        LEFT JOIN customers c ON LOWER(TRIM(u.email)) = LOWER(TRIM(c.email))
        WHERE u.role = 'customer'
        ORDER BY total_spent DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

function fetch_reservations($conn, $start, $end) {
    $stmt = $conn->prepare("
        SELECT reservation_id, customer_name, customer_email, customer_phone,
               reservation_date, reservation_time, guests_count, occasion,
               special_requests, status, created_at
        FROM reservations
        WHERE DATE(reservation_date) BETWEEN ? AND ?
        ORDER BY reservation_date ASC, reservation_time ASC
    ");
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_inventory($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'inventory'");
    if (!$check || $check->num_rows === 0) return [];
    return $conn->query("
        SELECT name, category, stock_quantity, min_stock, unit, expiry_date,
               CASE WHEN stock_quantity <= min_stock THEN 'Low Stock' ELSE 'OK' END AS stock_status
        FROM inventory
        ORDER BY stock_status DESC, name ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

function fetch_salary($conn, $start, $end) {
    $check = $conn->query("SHOW TABLES LIKE 'staff_salaries'");
    if (!$check || $check->num_rows === 0) return [];
    $month = date('Y-m', strtotime($start));
    $stmt = $conn->prepare("
        SELECT u.full_name, u.role, ss.month, ss.base_salary, ss.bonus,
               ss.deduction, (ss.base_salary + ss.bonus - ss.deduction) AS net_salary,
               ss.payment_status
        FROM staff_salaries ss
        JOIN users u ON ss.user_id = u.id
        WHERE ss.month >= ? AND ss.month <= ?
        ORDER BY ss.month DESC, u.full_name ASC
    ");
    $end_month = date('Y-m', strtotime($end));
    $stmt->bind_param("ss", $month, $end_month);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_tax($conn, $start, $end) {
    $tax_res = $conn->query("SELECT setting_value FROM restaurant_settings WHERE setting_key='tax_percentage'");
    $tax_pct = ($tax_res && $tax_res->num_rows > 0) ? (float)$tax_res->fetch_assoc()['setting_value'] : 0;

    $stmt = $conn->prepare("
        SELECT DATE(created_at) AS date,
               COUNT(*) AS orders,
               SUM(total_amount) AS revenue,
               SUM(total_amount) * ? / 100 AS tax_collected
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
          AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->bind_param("dss", $tax_pct, $start, $end);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return ['rows' => $rows, 'tax_pct' => $tax_pct];
}

// ── Report config ─────────────────────────────────────────────────────────────
$reports = [
    'sales' => [
        'title'   => 'Sales Report',
        'icon'    => '📊',
        'columns' => ['Date', 'Total Orders', 'Revenue (TK)', 'Avg Order (TK)', 'Completed Revenue (TK)', 'Cancelled'],
        'keys'    => ['date', 'total_orders', 'revenue', 'avg_order', 'completed_revenue', 'cancelled'],
        'data'    => fn() => fetch_sales($conn, $start, $end),
    ],
    'orders' => [
        'title'   => 'Order Report',
        'icon'    => '🛒',
        'columns' => ['Order ID', 'Customer', 'Email', 'Phone', 'Type', 'Total (TK)', 'Status', 'Payment Status', 'Payment Method', 'Date'],
        'keys'    => ['order_id','customer_name','customer_email','customer_phone','order_type','total_amount','status','payment_status','payment_method','created_at'],
        'data'    => fn() => fetch_orders($conn, $start, $end),
    ],
    'customers' => [
        'title'   => 'Customer Report',
        'icon'    => '👥',
        'columns' => ['Name', 'Email', 'Phone', 'Status', 'Total Orders', 'Total Spent (TK)', 'Last Order', 'Joined'],
        'keys'    => ['full_name','email','phone','status','total_orders','total_spent','last_order_date','created_at'],
        'data'    => fn() => fetch_customers($conn),
    ],
    'reservations' => [
        'title'   => 'Reservation Report',
        'icon'    => '📅',
        'columns' => ['Reservation ID', 'Customer', 'Email', 'Phone', 'Date', 'Time', 'Guests', 'Occasion', 'Special Requests', 'Status', 'Booked At'],
        'keys'    => ['reservation_id','customer_name','customer_email','customer_phone','reservation_date','reservation_time','guests_count','occasion','special_requests','status','created_at'],
        'data'    => fn() => fetch_reservations($conn, $start, $end),
    ],
    'inventory' => [
        'title'   => 'Inventory Report',
        'icon'    => '📦',
        'columns' => ['Item Name', 'Category', 'Stock Qty', 'Min Stock', 'Unit', 'Expiry Date', 'Status'],
        'keys'    => ['name','category','stock_quantity','min_stock','unit','expiry_date','stock_status'],
        'data'    => fn() => fetch_inventory($conn),
    ],
    'salary' => [
        'title'   => 'Staff Salary Report',
        'icon'    => '💰',
        'columns' => ['Staff Name', 'Role', 'Month', 'Base Salary (TK)', 'Bonus (TK)', 'Deduction (TK)', 'Net Salary (TK)', 'Payment Status'],
        'keys'    => ['full_name','role','month','base_salary','bonus','deduction','net_salary','payment_status'],
        'data'    => fn() => fetch_salary($conn, $start, $end),
    ],
    'tax' => [
        'title'   => 'Tax / VAT Report',
        'icon'    => '🧾',
        'columns' => ['Date', 'Orders', 'Revenue (TK)', 'Tax Collected (TK)'],
        'keys'    => ['date','orders','revenue','tax_collected'],
        'data'    => fn() => fetch_tax($conn, $start, $end)['rows'],
        'extra'   => fn() => fetch_tax($conn, $start, $end)['tax_pct'],
    ],
];

if (!isset($reports[$type])) {
    die('Invalid report type');
}

$report    = $reports[$type];
$title     = $report['title'];
$columns   = $report['columns'];
$keys      = $report['keys'];
$data      = ($report['data'])();

// ── CSV Export ────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $filename = strtolower(str_replace(' ', '_', $title)) . '_' . $start . '_to_' . $end . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");

    // Meta rows
    fputcsv($out, [$restaurant_name . ' — ' . $title]);
    fputcsv($out, ['Period: ' . $start . ' to ' . $end]);
    fputcsv($out, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($out, []);

    // Header row
    fputcsv($out, $columns);

    // Data rows
    foreach ($data as $row) {
        $line = [];
        foreach ($keys as $k) {
            $val = $row[$k] ?? '';
            // Format numbers
            if (in_array($k, ['revenue','total_amount','avg_order','completed_revenue','total_spent','base_salary','bonus','deduction','net_salary','tax_collected'])) {
                $val = number_format((float)$val, 2, '.', '');
            }
            $line[] = $val;
        }
        fputcsv($out, $line);
    }

    // Summary totals for numeric reports
    if (in_array($type, ['sales','orders','tax'])) {
        fputcsv($out, []);
        $totals = ['TOTAL'];
        foreach (array_slice($keys, 1) as $k) {
            $sum = array_sum(array_column($data, $k));
            if (in_array($k, ['revenue','total_amount','avg_order','completed_revenue','tax_collected'])) {
                $totals[] = number_format($sum, 2, '.', '');
            } else if (is_numeric($sum)) {
                $totals[] = $sum;
            } else {
                $totals[] = '';
            }
        }
        fputcsv($out, $totals);
    }

    fclose($out);
    exit;
}

// ── Print / PDF HTML ──────────────────────────────────────────────────────────
if ($format === 'print') {
    $totals = [];
    $numeric_keys = ['revenue','total_amount','avg_order','completed_revenue','total_spent','base_salary','bonus','deduction','net_salary','tax_collected','total_orders','orders'];
    foreach ($keys as $k) {
        if (in_array($k, $numeric_keys)) {
            $totals[$k] = array_sum(array_column($data, $k));
        }
    }
    $extra_info = isset($report['extra']) ? ($report['extra'])() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($restaurant_name) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; }

    /* ── Header ── */
    .print-header {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        padding: 24px 32px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #c9a74d;
    }
    .print-logo { font-size: 22px; font-weight: 800; color: #c9a74d; letter-spacing: 1px; }
    .print-logo small { display: block; font-size: 10px; color: #94a3b8; font-weight: 400; margin-top: 2px; }
    .print-meta { text-align: right; font-size: 11px; color: #94a3b8; line-height: 1.8; }
    .print-meta strong { color: #c9a74d; }

    /* ── Report Title ── */
    .report-title-bar {
        padding: 16px 32px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .report-title-bar .icon { font-size: 20px; }
    .report-title-bar h1 { font-size: 18px; color: #0f172a; font-weight: 700; }
    .report-title-bar .period { margin-left: auto; font-size: 11px; color: #64748b; background: #e2e8f0; padding: 4px 12px; border-radius: 20px; }

    /* ── Summary cards ── */
    .summary-row { display: flex; gap: 12px; padding: 16px 32px; background: #fff; flex-wrap: wrap; }
    .summary-card {
        flex: 1; min-width: 120px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        text-align: center;
    }
    .summary-card .val { font-size: 16px; font-weight: 800; color: #0f172a; }
    .summary-card .lbl { font-size: 10px; color: #64748b; margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }

    /* ── Table ── */
    .table-wrap { padding: 0 32px 24px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 8px; }
    thead tr { background: #1a1a2e; }
    thead th { color: #c9a74d; padding: 9px 10px; text-align: left; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody tr:hover { background: #f0f9ff; }
    tbody td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    tfoot tr { background: #0f172a; }
    tfoot td { color: #c9a74d; font-weight: 700; padding: 9px 10px; font-size: 11px; }

    /* Status badges */
    .badge {
        display: inline-block; padding: 2px 8px; border-radius: 12px;
        font-size: 9px; font-weight: 700; text-transform: uppercase;
    }
    .badge-completed,.badge-confirmed,.badge-paid,.badge-active { background:#dcfce7;color:#166534; }
    .badge-pending   { background:#fef9c3;color:#854d0e; }
    .badge-cancelled { background:#fee2e2;color:#991b1b; }
    .badge-preparing { background:#dbeafe;color:#1e40af; }
    .badge-low-stock { background:#fee2e2;color:#991b1b; }
    .badge-ok        { background:#dcfce7;color:#166534; }

    /* ── Footer ── */
    .print-footer {
        margin-top: 24px;
        padding: 12px 32px;
        border-top: 1px solid #e2e8f0;
        font-size: 10px;
        color: #94a3b8;
        display: flex;
        justify-content: space-between;
    }

    /* ── Extra info box ── */
    .info-box { margin: 0 32px 12px; padding: 10px 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; font-size: 11px; color: #92400e; }

    /* ── No data ── */
    .no-data { padding: 32px; text-align: center; color: #94a3b8; font-size: 13px; }

    /* ── Print controls (hidden when printing) ── */
    .print-controls {
        position: fixed; top: 16px; right: 16px;
        display: flex; gap: 8px; z-index: 999;
    }
    .print-controls button {
        padding: 8px 18px; border: none; border-radius: 6px;
        cursor: pointer; font-size: 13px; font-weight: 600;
    }
    .btn-print  { background: #c9a74d; color: #1a1a2e; }
    .btn-close  { background: #e2e8f0; color: #475569; }
    @media print {
        .print-controls { display: none; }
        body { background: white; }
        .print-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .summary-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<!-- Print Controls -->
<div class="print-controls">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Close</button>
</div>

<!-- Header -->
<div class="print-header">
    <div>
        <div class="print-logo">
            <?= htmlspecialchars($restaurant_name) ?>
            <small>Management Report</small>
        </div>
    </div>
    <div class="print-meta">
        <div><strong>Report:</strong> <?= htmlspecialchars($title) ?></div>
        <div><strong>Period:</strong> <?= $start ?> → <?= $end ?></div>
        <div><strong>Generated:</strong> <?= date('d M Y, H:i') ?></div>
        <div><strong>Prepared by:</strong> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
    </div>
</div>

<!-- Report Title Bar -->
<div class="report-title-bar">
    <span class="icon"><?= $report['icon'] ?></span>
    <h1><?= htmlspecialchars($title) ?></h1>
    <span class="period"><?= $start ?> — <?= $end ?></span>
</div>

<?php
// Extra info (e.g., tax rate)
if ($extra_info !== null):
?>
<div class="info-box">
    <strong>ℹ️ Note:</strong> Tax/VAT rate applied: <strong><?= $extra_info ?>%</strong>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<?php
$summary_cards = [];
if ($type === 'sales') {
    $total_rev = array_sum(array_column($data, 'revenue'));
    $total_ord = array_sum(array_column($data, 'total_orders'));
    $total_can = array_sum(array_column($data, 'cancelled'));
    $avg_daily = count($data) > 0 ? $total_rev / count($data) : 0;
    $summary_cards = [
        ['Total Revenue',     'TK ' . number_format($total_rev, 2)],
        ['Total Orders',      $total_ord],
        ['Avg Daily Revenue', 'TK ' . number_format($avg_daily, 2)],
        ['Cancelled Orders',  $total_can],
        ['Days Covered',      count($data)],
    ];
} elseif ($type === 'orders') {
    $total = array_sum(array_column($data, 'total_amount'));
    $completed = count(array_filter($data, fn($r) => $r['status'] === 'completed'));
    $cancelled = count(array_filter($data, fn($r) => $r['status'] === 'cancelled'));
    $summary_cards = [
        ['Total Orders',    count($data)],
        ['Total Revenue',   'TK ' . number_format($total, 2)],
        ['Completed',       $completed],
        ['Cancelled',       $cancelled],
    ];
} elseif ($type === 'customers') {
    $total_spent = array_sum(array_column($data, 'total_spent'));
    $active = count(array_filter($data, fn($r) => $r['status'] === 'active'));
    $summary_cards = [
        ['Total Customers', count($data)],
        ['Active',          $active],
        ['Total Revenue',   'TK ' . number_format($total_spent, 2)],
    ];
} elseif ($type === 'reservations') {
    $confirmed = count(array_filter($data, fn($r) => $r['status'] === 'confirmed'));
    $cancelled = count(array_filter($data, fn($r) => $r['status'] === 'cancelled'));
    $total_guests = array_sum(array_column($data, 'guests_count'));
    $summary_cards = [
        ['Total Reservations', count($data)],
        ['Confirmed',          $confirmed],
        ['Cancelled',          $cancelled],
        ['Total Guests',       $total_guests],
    ];
} elseif ($type === 'inventory') {
    $low = count(array_filter($data, fn($r) => $r['stock_status'] === 'Low Stock'));
    $summary_cards = [
        ['Total Items', count($data)],
        ['Low Stock',   $low],
        ['OK',          count($data) - $low],
    ];
} elseif ($type === 'salary') {
    $total_net = array_sum(array_column($data, 'net_salary'));
    $total_bonus = array_sum(array_column($data, 'bonus'));
    $paid = count(array_filter($data, fn($r) => $r['payment_status'] === 'paid'));
    $summary_cards = [
        ['Total Net Salary', 'TK ' . number_format($total_net, 2)],
        ['Total Bonus',      'TK ' . number_format($total_bonus, 2)],
        ['Paid',             $paid],
        ['Pending',          count($data) - $paid],
    ];
} elseif ($type === 'tax') {
    $total_tax = array_sum(array_column($data, 'tax_collected'));
    $total_rev = array_sum(array_column($data, 'revenue'));
    $summary_cards = [
        ['Total Revenue',      'TK ' . number_format($total_rev, 2)],
        ['Total Tax Collected', 'TK ' . number_format($total_tax, 2)],
        ['Tax Rate',           ($extra_info ?? 0) . '%'],
        ['Days Covered',       count($data)],
    ];
}
?>
<?php if (!empty($summary_cards)): ?>
<div class="summary-row">
    <?php foreach ($summary_cards as $card): ?>
    <div class="summary-card">
        <div class="val"><?= htmlspecialchars((string)$card[1]) ?></div>
        <div class="lbl"><?= htmlspecialchars($card[0]) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Data Table -->
<div class="table-wrap">
    <?php if (empty($data)): ?>
    <div class="no-data">No data found for the selected period.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <?php foreach ($columns as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $idx => $row): ?>
            <tr>
                <td style="color:#94a3b8"><?= $idx + 1 ?></td>
                <?php foreach ($keys as $k): ?>
                <td>
                    <?php
                    $val = $row[$k] ?? '';
                    // Format numbers
                    if (in_array($k, ['revenue','total_amount','avg_order','completed_revenue','total_spent','base_salary','bonus','deduction','net_salary','tax_collected'])) {
                        echo 'TK ' . number_format((float)$val, 2);
                    } elseif (in_array($k, ['status','payment_status','stock_status'])) {
                        $cls = strtolower(str_replace(' ', '-', $val));
                        echo '<span class="badge badge-' . htmlspecialchars($cls) . '">' . htmlspecialchars($val) . '</span>';
                    } elseif (in_array($k, ['created_at','last_order_date']) && $val) {
                        echo date('d M Y H:i', strtotime($val));
                    } elseif ($k === 'reservation_time' && $val) {
                        echo date('h:i A', strtotime($val));
                    } else {
                        echo htmlspecialchars((string)$val);
                    }
                    ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <!-- Totals footer for numeric reports -->
        <?php if (in_array($type, ['sales','orders','tax','salary'])): ?>
        <tfoot>
            <tr>
                <td colspan="2"><strong>TOTAL</strong></td>
                <?php
                $skipped = true;
                foreach ($keys as $i => $k) {
                    if ($i === 0) continue; // skip first key (already in colspan)
                    if (isset($totals[$k])) {
                        echo '<td><strong>TK ' . number_format($totals[$k], 2) . '</strong></td>';
                    } elseif (array_key_exists($k, $totals)) {
                        echo '<td><strong>' . number_format($totals[$k], 0) . '</strong></td>';
                    } else {
                        echo '<td>—</td>';
                    }
                }
                ?>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="print-footer">
    <span><?= htmlspecialchars($restaurant_name) ?> — Confidential Report</span>
    <span>Total records: <?= count($data) ?> | Generated on <?= date('d M Y, H:i:s') ?></span>
</div>

<script>
// Auto-open print dialog after short delay
window.addEventListener('load', () => {
    // Only auto-print if triggered from export button (not direct URL)
    if (document.referrer.includes('admin.php')) {
        setTimeout(() => window.print(), 600);
    }
});
</script>
</body>
</html>
<?php
    exit;
}

// Unknown format
http_response_code(400);
echo 'Invalid format';
