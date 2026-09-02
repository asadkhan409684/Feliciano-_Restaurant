<?php
// Suppress PHP warnings/notices from corrupting JSON output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require '../config/database.php';
require_once '../config/rbac.php';

header('Content-Type: application/json');

// Security Check — allow admin + all staff roles
$allowed_panel_roles = ['admin','chef','waiter','cashier'];
if (!isset($_SESSION['user_logged_in']) || !in_array($_SESSION['user_role'] ?? '', $allowed_panel_roles)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Initialize RBAC
rbac_init($conn);

$action = $_GET['action'] ?? '';

// --- HELPER: IMAGE UPLOAD & RESIZE ---
function uploadAndResizeImage($file, $target_dir = '../assets/images/menu/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('menu_') . '.jpg';
    $target_file = $target_dir . $new_filename;

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // fallback if GD is not loaded
    if (!extension_loaded('gd')) {
        $final_filename = uniqid('menu_') . '.' . $file_extension;
        if (move_uploaded_file($file['tmp_name'], $target_dir . $final_filename)) {
            return 'assets/images/menu/' . $final_filename;
        }
        return null;
    }

    switch ($file_extension) {
        case 'jpeg': case 'jpg': $src = imagecreatefromjpeg($file['tmp_name']); break;
        case 'png': $src = imagecreatefrompng($file['tmp_name']); break;
        case 'webp': $src = imagecreatefromwebp($file['tmp_name']); break;
        default: return null;
    }

    if (!$src) return null;

    $w = imagesx($src); $h = imagesy($src);
    $tw = 800; $th = 600; // Standard size

    $dst = imagecreatetruecolor($tw, $th);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagejpeg($dst, $target_file, 85);

    imagedestroy($src);
    imagedestroy($dst);

    return 'assets/images/menu/' . $new_filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // --- DASHBOARD STATS ---
    if ($action === 'get_stats') {
        $stats = [];
        $branch_id = $_GET['branch_id'] ?? 'all';
        $where_clause = ($branch_id !== 'all') ? " WHERE branch_id = " . intval($branch_id) : "";
        $branch_and   = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";

        // Menu Items
        $res = $conn->query("SELECT COUNT(*) as cnt FROM menu_items");
        $stats['menu_items'] = $res->fetch_assoc()['cnt'];

        // Customers
        $res = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'customer'");
        $stats['total_customers'] = $res->fetch_assoc()['cnt'];

        // Monthly Revenue — use MySQL CURDATE() to avoid PHP timezone issue
        $res = $conn->query("SELECT IFNULL(SUM(total_amount),0) as monthly
            FROM orders
            WHERE status='completed'
            AND DATE(created_at) BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND LAST_DAY(CURDATE())
            $branch_and");
        $stats['monthly_revenue'] = $res->fetch_assoc()['monthly'] ?? 0;

        // Today's stats — all using MySQL CURDATE()
        $res = $conn->query("SELECT
            COUNT(*) as today_orders,
            IFNULL(SUM(total_amount),0) as today_revenue,
            SUM(status='pending') as pending_orders,
            SUM(status='completed') as completed_orders,
            SUM(status='cancelled') as cancelled_orders
            FROM orders
            WHERE DATE(created_at) = CURDATE()
            $branch_and");
        $today = $res->fetch_assoc();
        $stats['today_orders']     = (int)($today['today_orders'] ?? 0);
        $stats['today_revenue']    = (float)($today['today_revenue'] ?? 0);
        $stats['pending_orders']   = (int)($today['pending_orders'] ?? 0);
        $stats['completed_orders'] = (int)($today['completed_orders'] ?? 0);
        $stats['cancelled_orders'] = (int)($today['cancelled_orders'] ?? 0);

        // Yesterday's stats for comparison
        $res = $conn->query("SELECT
            COUNT(*) as yesterday_orders,
            IFNULL(SUM(total_amount),0) as yesterday_revenue
            FROM orders
            WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
            $branch_and");
        $yest = $res->fetch_assoc();
        $stats['yesterday_orders']  = (int)($yest['yesterday_orders'] ?? 0);
        $stats['yesterday_revenue'] = (float)($yest['yesterday_revenue'] ?? 0);

        // Total reservations (today)
        $check_resv = $conn->query("SHOW TABLES LIKE 'reservations'");
        if ($check_resv && $check_resv->num_rows > 0) {
            $res = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE DATE(created_at) = CURDATE() $branch_and");
            $stats['today_reservations'] = (int)($res->fetch_assoc()['cnt'] ?? 0);
            $res2 = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY $branch_and");
            $stats['yesterday_reservations'] = (int)($res2->fetch_assoc()['cnt'] ?? 0);
        } else {
            $stats['today_reservations'] = 0;
            $stats['yesterday_reservations'] = 0;
        }

        echo json_encode(['status' => 'success', 'data' => $stats]);
    }

    // --- GET ORDERS ---
    elseif ($action === 'get_orders') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $where_clause = ($branch_id !== 'all') ? " WHERE branch_id = " . intval($branch_id) : "";
        $sql = "SELECT * FROM orders" . $where_clause . " ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $orders = [];
        
        while($row = $result->fetch_assoc()) {
            $order_id = $row['id'];
            // Fetch items for this order
            $item_sql = "SELECT * FROM order_items WHERE order_id = $order_id";
            $item_res = $conn->query($item_sql);
            $items = [];
            while($item = $item_res->fetch_assoc()) {
                $items[] = [
                    'name' => $item['menu_item_name'],
                    'qty' => $item['quantity'],
                    'price' => $item['unit_price']
                ];
            }
            
            $orders[] = [
                'id' => $row['order_id'], // Display ID (e.g., ORD-123)
                'db_id' => $row['id'],    // Database ID
                'customer' => $row['customer_email'],
                'customer_name' => $row['customer_name'],
                'customer_phone' => $row['customer_phone'],
                'order_type' => $row['order_type'],
                'date' => $row['created_at'],
                'total' => $row['total_amount'],
                'status' => $row['status'],
                'items' => $items
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $orders]);
    }

    // --- GET USERS ---
    elseif ($action === 'get_users') {
        $sql = "SELECT id, first_name, last_name, full_name, email, phone, role, status, branch_id, created_at FROM users ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $users = [];
        while($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $users]);
    }

    // --- GET CUSTOMERS ---
    elseif ($action === 'get_customers') {
        $sql = "SELECT 
                    u.id, 
                    u.full_name, 
                    u.email, 
                    u.phone, 
                    COALESCE(c.customer_id, CONCAT('CUST-', u.id)) as customer_id,
                    COALESCE(c.total_orders, 0) as total_orders, 
                    COALESCE(c.total_spent, '0.00') as total_spent, 
                    c.last_order_date 
                FROM users u 
                LEFT JOIN customers c ON LOWER(TRIM(u.email)) = LOWER(TRIM(c.email)) 
                WHERE u.role = 'customer' 
                ORDER BY total_orders DESC";
        $result = $conn->query($sql);
        $customers = [];
        while($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $customers]);
    }

    // --- GET MENU ---
    elseif ($action === 'get_menu') {
        if (!rbac_can('menu-management') && !rbac_can('orders')) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied']); exit;
        }
        // Auto-create stock columns if missing
        $stock_cols = [
            "in_stock TINYINT(1) NOT NULL DEFAULT 1",
            "stock_quantity INT DEFAULT NULL"
        ];
        foreach ($stock_cols as $col_def) {
            $col_name = explode(' ', $col_def)[0];
            $chk = $conn->query("SHOW COLUMNS FROM menu_items LIKE '$col_name'");
            if ($chk && $chk->num_rows === 0) {
                $conn->query("ALTER TABLE menu_items ADD COLUMN $col_def");
            }
        }
        $sql = "SELECT * FROM menu_items ORDER BY id DESC";
        $result = $conn->query($sql);
        $menu = [];
        while($row = $result->fetch_assoc()) {
            $menu[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $menu]);
    }

    // --- GET GALLERY IMAGES FOR A MENU ITEM ---
    elseif ($action === 'get_menu_images') {
        $item_id = intval($_GET['item_id'] ?? 0);
        if (!$item_id) { echo json_encode(['status' => 'error', 'message' => 'No item_id']); exit; }
        $stmt = $conn->prepare("SELECT * FROM menu_item_images WHERE menu_item_id=? ORDER BY sort_order ASC, id ASC");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $images = [];
        while ($row = $result->fetch_assoc()) $images[] = $row;
        echo json_encode(['status' => 'success', 'data' => $images]);
        exit;
    }

    // --- GET RESERVATIONS ---
    elseif ($action === 'get_reservations') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $where_clause = ($branch_id !== 'all') ? " WHERE branch_id = " . intval($branch_id) : "";
        $sql = "SELECT * FROM reservations" . $where_clause . " ORDER BY reservation_date DESC, reservation_time DESC";
        $result = $conn->query($sql);
        $reservations = [];
        while($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $reservations]);
    }

    // --- GET ANALYTICS ---
    elseif ($action === 'get_popular_items') {
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND o.branch_id = " . intval($branch_id) : "";

        if ($start && $end) {
            $sql = "SELECT mi.name, mi.image_url, COUNT(oi.id) as order_count 
                    FROM menu_items mi
                    JOIN order_items oi ON mi.id = oi.menu_item_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND o.status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY mi.id 
                    ORDER BY order_count DESC LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start, $end);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql = "SELECT mi.name, mi.image_url, COUNT(oi.id) as order_count 
                    FROM menu_items mi
                    JOIN order_items oi ON mi.id = oi.menu_item_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY mi.id 
                    ORDER BY order_count DESC LIMIT 5";
            $result = $conn->query($sql);
        }
        $items = [];
        if ($result) while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    elseif ($action === 'get_daily_sales') {
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";

        if ($start && $end) {
            $sql = "SELECT DATE(created_at) as sale_date, COUNT(*) as total_orders, SUM(total_amount) as total_revenue
                    FROM orders
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    AND status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY DATE(created_at)
                    ORDER BY sale_date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start, $end);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql = "SELECT DATE(created_at) as sale_date, COUNT(*) as total_orders, SUM(total_amount) as total_revenue
                    FROM orders
                    WHERE status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY DATE(created_at)
                    ORDER BY sale_date DESC LIMIT 7";
            $result = $conn->query($sql);
        }
        $sales = [];
        while($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $sales]);
    }

    elseif ($action === 'get_analytics_summary') {
        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $end = $_GET['end'] ?? date('Y-m-d');
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";

        // Current Period Stats
        $sql = "SELECT 
                COUNT(*) as total_orders, 
                IFNULL(SUM(total_amount), 0) as total_revenue,
                IFNULL(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND status NOT IN ('cancelled') " . $branch_filter;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start, $end);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        
        // Previous Period Stats for Comparison
        $diff = strtotime($end) - strtotime($start);
        $prev_end = date('Y-m-d', strtotime($start) - 86400); // Day before current start
        $prev_start = date('Y-m-d', strtotime($prev_end) - $diff);
        
        $sql_prev = "SELECT 
                COUNT(*) as total_orders, 
                IFNULL(SUM(total_amount), 0) as total_revenue,
                IFNULL(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND status NOT IN ('cancelled') " . $branch_filter;
        $stmt_prev = $conn->prepare($sql_prev);
        $stmt_prev->bind_param("ss", $prev_start, $prev_end);
        $stmt_prev->execute();
        $summary['previous'] = $stmt_prev->get_result()->fetch_assoc();

        // Activity Patterns (Busiest Day & Peak Hour)
        $day_sql = "SELECT DAYNAME(created_at) as day_name, COUNT(*) as count 
                    FROM orders WHERE status != 'cancelled' 
                    " . $branch_filter . "
                    GROUP BY day_name ORDER BY count DESC LIMIT 1";
        $res_day = $conn->query($day_sql);
        $summary['busiest_day'] = ($res_day && $res_day->num_rows > 0) ? $res_day->fetch_assoc()['day_name'] : 'N/A';
        
        $hour_sql = "SELECT HOUR(created_at) as hour, COUNT(*) as count 
                     FROM orders WHERE status != 'cancelled' 
                     " . $branch_filter . "
                     GROUP BY hour ORDER BY count DESC LIMIT 1";
        $res_hour = $conn->query($hour_sql);
        $summary['peak_hour'] = ($res_hour && $res_hour->num_rows > 0) ? $res_hour->fetch_assoc()['hour'] : '0';

        // Top Category
        $cat_sql = "SELECT mi.category, COUNT(oi.id) as count 
                    FROM menu_items mi
                    JOIN order_items oi ON mi.id = oi.menu_item_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND o.status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY mi.category 
                    ORDER BY count DESC LIMIT 1";
        $cat_stmt = $conn->prepare($cat_sql);
        $cat_stmt->bind_param("ss", $start, $end);
        $cat_stmt->execute();
        $top_cat = $cat_stmt->get_result()->fetch_assoc();
        $summary['top_category'] = $top_cat ? $top_cat['category'] : 'N/A';

        echo json_encode(['status' => 'success', 'data' => $summary]);
    }

    elseif ($action === 'get_monthly_revenue') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";
        
        $sql = "SELECT 
                MONTHNAME(created_at) as month, 
                YEAR(created_at) as year,
                SUM(total_amount) as revenue 
                FROM orders 
                WHERE status != 'cancelled' 
                " . $branch_filter . "
                GROUP BY YEAR(created_at), MONTH(created_at) 
                ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC 
                LIMIT 12";
        
        $result = $conn->query($sql);
        $monthly = [];
        
        if ($result) {
            while($row = $result->fetch_assoc()) {
                $monthly[] = [
                    'month' => $row['month'] ?? 'Unknown',
                    'year' => $row['year'] ?? date('Y'),
                    'revenue' => (float)($row['revenue'] ?? 0)
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $monthly]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }

    // --- DASHBOARD: REVENUE CHART (last 7 days + last 12 months) ---
    elseif ($action === 'get_revenue_chart') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";

        // Get MySQL's current date — avoids PHP timezone mismatch
        $mysql_today = $conn->query("SELECT CURDATE() as d")->fetch_assoc()['d'];

        // Last 7 days daily revenue
        $daily_sql = "SELECT
                DATE(created_at) as sale_date,
                IFNULL(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders
            FROM orders
            WHERE DATE(created_at) >= CURDATE() - INTERVAL 6 DAY
            AND status NOT IN ('cancelled')
            $branch_filter
            GROUP BY DATE(created_at)
            ORDER BY sale_date ASC";
        $daily_result = $conn->query($daily_sql);

        // Build full 7-day array using MySQL dates (not PHP date())
        $daily_map = [];
        if ($daily_result) {
            while ($row = $daily_result->fetch_assoc()) {
                $daily_map[$row['sale_date']] = ['revenue' => (float)$row['revenue'], 'orders' => (int)$row['orders']];
            }
        }
        $daily = [];
        for ($i = 6; $i >= 0; $i--) {
            // Use MySQL to calculate the date — avoids PHP timezone issues
            $date_res = $conn->query("SELECT DATE(CURDATE() - INTERVAL $i DAY) as d");
            $date = $date_res->fetch_assoc()['d'];
            $ts = strtotime($date);
            $daily[] = [
                'date'    => $date,
                'label'   => date('D d M', $ts),
                'revenue' => $daily_map[$date]['revenue'] ?? 0,
                'orders'  => $daily_map[$date]['orders']  ?? 0,
            ];
        }

        // Last 12 months monthly revenue
        $monthly_sql = "SELECT
                YEAR(created_at) as yr,
                MONTH(created_at) as mo,
                MONTHNAME(created_at) as month_name,
                IFNULL(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders
            FROM orders
            WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')
            AND status NOT IN ('cancelled')
            $branch_filter
            GROUP BY YEAR(created_at), MONTH(created_at)
            ORDER BY yr ASC, mo ASC";
        $monthly_result = $conn->query($monthly_sql);

        // Build full 12-month array using MySQL dates
        $monthly_map = [];
        if ($monthly_result) {
            while ($row = $monthly_result->fetch_assoc()) {
                $key = $row['yr'] . '-' . str_pad($row['mo'], 2, '0', STR_PAD_LEFT);
                $monthly_map[$key] = [
                    'revenue' => (float)$row['revenue'],
                    'orders'  => (int)$row['orders'],
                    'label'   => $row['month_name'] . ' ' . $row['yr']
                ];
            }
        }
        $monthly = [];
        for ($i = 11; $i >= 0; $i--) {
            // Use MySQL for month calculation — avoids PHP timezone issues
            $m_res = $conn->query("SELECT DATE_FORMAT(CURDATE() - INTERVAL $i MONTH, '%Y-%m') as ym,
                                          DATE_FORMAT(CURDATE() - INTERVAL $i MONTH, '%b %Y') as label");
            $m_row  = $m_res->fetch_assoc();
            $key    = $m_row['ym'];
            $monthly[] = [
                'key'     => $key,
                'label'   => $m_row['label'],
                'revenue' => $monthly_map[$key]['revenue'] ?? 0,
                'orders'  => $monthly_map[$key]['orders']  ?? 0,
            ];
        }

        echo json_encode(['status' => 'success', 'daily' => $daily, 'monthly' => $monthly]);
        exit;
    }

    // --- DASHBOARD: LOW STOCK ALERT WIDGET ---
    elseif ($action === 'get_low_stock_alert') {
        $check = $conn->query("SHOW TABLES LIKE 'inventory'");
        if (!$check || $check->num_rows === 0) {
            echo json_encode(['status' => 'success', 'data' => [], 'total_items' => 0, 'low_count' => 0]);
            exit;
        }
        $total_res = $conn->query("SELECT COUNT(*) as c FROM inventory");
        $total_items = $total_res ? (int)$total_res->fetch_assoc()['c'] : 0;

        $sql = "SELECT name, stock_quantity, min_stock, unit, category
                FROM inventory
                WHERE stock_quantity <= min_stock
                ORDER BY (stock_quantity / GREATEST(min_stock, 1)) ASC
                LIMIT 8";
        $result = $conn->query($sql);
        $items = [];
        if ($result) while ($row = $result->fetch_assoc()) $items[] = $row;

        echo json_encode(['status' => 'success', 'data' => $items, 'total_items' => $total_items, 'low_count' => count($items)]);
        exit;
    }

    // --- DASHBOARD: STAFF ATTENDANCE SUMMARY (today) ---
    elseif ($action === 'get_attendance_summary') {
        $check = $conn->query("SHOW TABLES LIKE 'staff_attendance'");
        if (!$check || $check->num_rows === 0) {
            echo json_encode(['status' => 'success', 'data' => ['present' => 0, 'absent' => 0, 'half_day' => 0, 'late' => 0, 'total_staff' => 0, 'not_marked' => 0]]);
            exit;
        }

        // Use MySQL date to avoid PHP timezone mismatch
        $today = $conn->query("SELECT CURDATE() as d")->fetch_assoc()['d'];

        // Total active staff (non-customer, non-admin roles)
        $staff_res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role IN ('manager','chef','waiter','cashier') AND status = 'active'");
        $total_staff = $staff_res ? (int)$staff_res->fetch_assoc()['c'] : 0;

        // Today's attendance breakdown
        $att_sql = "SELECT status, COUNT(*) as cnt FROM staff_attendance WHERE attendance_date = '$today' GROUP BY status";
        $att_res = $conn->query($att_sql);
        $counts = ['present' => 0, 'absent' => 0, 'half_day' => 0, 'late' => 0];
        if ($att_res) {
            while ($row = $att_res->fetch_assoc()) {
                if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['cnt'];
            }
        }
        $marked = array_sum($counts);
        $counts['total_staff']  = $total_staff;
        $counts['not_marked']   = max(0, $total_staff - $marked);

        // Last 7 days attendance rate
        $rate_sql = "SELECT
                attendance_date,
                SUM(status = 'present') as present_cnt,
                SUM(status = 'late') as late_cnt,
                COUNT(*) as total_cnt
            FROM staff_attendance
            WHERE attendance_date >= CURDATE() - INTERVAL 6 DAY
            GROUP BY attendance_date
            ORDER BY attendance_date ASC";
        $rate_res = $conn->query($rate_sql);
        $weekly_trend = [];
        if ($rate_res) {
            while ($row = $rate_res->fetch_assoc()) {
                $weekly_trend[] = [
                    'date'    => $row['attendance_date'],
                    'label'   => date('D', strtotime($row['attendance_date'])),
                    'present' => (int)$row['present_cnt'] + (int)$row['late_cnt'],
                    'total'   => (int)$row['total_cnt'],
                ];
            }
        }

        echo json_encode(['status' => 'success', 'data' => $counts, 'weekly_trend' => $weekly_trend]);
        exit;
    }

    // --- DASHBOARD: TODAY vs YESTERDAY SALES COMPARISON ---
    elseif ($action === 'get_sales_comparison') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";

        // Use MySQL dates to avoid PHP timezone mismatch
        $today     = $conn->query("SELECT CURDATE() as d")->fetch_assoc()['d'];
        $yesterday = $conn->query("SELECT CURDATE() - INTERVAL 1 DAY as d")->fetch_assoc()['d'];

        $stmt = $conn->prepare("SELECT
                IFNULL(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders,
                IFNULL(AVG(total_amount), 0) as avg_order
            FROM orders
            WHERE DATE(created_at) = ?
            AND status NOT IN ('cancelled')
            $branch_filter");

        $stmt->bind_param("s", $today);
        $stmt->execute();
        $today_data = $stmt->get_result()->fetch_assoc();

        $stmt->bind_param("s", $yesterday);
        $stmt->execute();
        $yesterday_data = $stmt->get_result()->fetch_assoc();

        // Hourly breakdown for today
        $hourly_sql = "SELECT HOUR(created_at) as hr, IFNULL(SUM(total_amount),0) as rev
            FROM orders
            WHERE DATE(created_at) = '$today'
            AND status NOT IN ('cancelled')
            $branch_filter
            GROUP BY HOUR(created_at)
            ORDER BY hr ASC";
        $hourly_res = $conn->query($hourly_sql);
        $hourly_map = [];
        if ($hourly_res) while ($row = $hourly_res->fetch_assoc()) $hourly_map[(int)$row['hr']] = (float)$row['rev'];

        $hourly = [];
        $current_hour = (int)date('H');
        for ($h = 8; $h <= min(23, $current_hour); $h++) {
            $hourly[] = ['hour' => $h, 'label' => date('g A', mktime($h, 0, 0)), 'revenue' => $hourly_map[$h] ?? 0];
        }

        echo json_encode([
            'status'    => 'success',
            'today'     => ['revenue' => (float)$today_data['revenue'], 'orders' => (int)$today_data['orders'], 'avg_order' => (float)$today_data['avg_order']],
            'yesterday' => ['revenue' => (float)$yesterday_data['revenue'], 'orders' => (int)$yesterday_data['orders'], 'avg_order' => (float)$yesterday_data['avg_order']],
            'hourly'    => $hourly,
        ]);
        exit;
    }

    // --- GET INVENTORY ---
    elseif ($action === 'get_inventory') {
        $result = $conn->query("SELECT * FROM inventory ORDER BY name ASC");
        $items = [];
        if ($result) while($row = $result->fetch_assoc()) $items[] = $row;
        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    // --- GET SUPPLIERS ---
    elseif ($action === 'get_suppliers') {
        $result = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET PURCHASES ---
    elseif ($action === 'get_purchases') {
        $sql = "SELECT p.*, COALESCE(s.name, p.supplier_name) AS supplier_display 
                FROM purchases p 
                LEFT JOIN suppliers s ON p.supplier_id = s.id 
                ORDER BY p.purchase_date DESC";
        $result = $conn->query($sql);
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET COMBO MEALS ---
    elseif ($action === 'get_combos') {
        $result = $conn->query("SELECT * FROM combo_meals ORDER BY created_at DESC");
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET GALLERY ---
    elseif ($action === 'get_gallery') {
        $cat = isset($_GET['category']) && $_GET['category'] !== 'all' ? $_GET['category'] : null;
        if ($cat) {
            $stmt = $conn->prepare("SELECT * FROM gallery WHERE category=? ORDER BY created_at DESC");
            $stmt->bind_param("s", $cat);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
        }
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET EVENTS ---
    elseif ($action === 'get_events') {
        $result = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET BILLING ---
    elseif ($action === 'get_billing') {
        $sql = "SELECT id, order_id, customer_name, customer_email, total_amount, payment_method, payment_status, created_at 
                FROM orders ORDER BY created_at DESC LIMIT 100";
        $result = $conn->query($sql);
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        // Payment method breakdown
        $methods = $conn->query("SELECT payment_method, COUNT(*) as cnt, SUM(total_amount) as total FROM orders GROUP BY payment_method");
        $breakdown = [];
        if ($methods) while($row = $methods->fetch_assoc()) $breakdown[] = $row;
        // Stats
        $paid = $conn->query("SELECT IFNULL(SUM(total_amount),0) as t FROM orders WHERE payment_status='paid'");
        $paid_total = $paid ? $paid->fetch_assoc()['t'] : 0;
        $pending_cnt = $conn->query("SELECT COUNT(*) as c FROM orders WHERE payment_status='pending'");
        $pending_count = $pending_cnt ? $pending_cnt->fetch_assoc()['c'] : 0;
        $tax_res = $conn->query("SELECT setting_value FROM restaurant_settings WHERE setting_key='tax_percentage'");
        $tax_pct = ($tax_res && $tax_res->num_rows > 0) ? (float)($tax_res->fetch_assoc()['setting_value'] ?? 0) : 0;
        $tax_collected = round($paid_total * ($tax_pct / 100), 2);
        echo json_encode(['status' => 'success', 'data' => $list, 'breakdown' => $breakdown,
            'stats' => ['paid' => $paid_total, 'pending_count' => $pending_count, 'tax' => $tax_collected]]);
    }

    // --- GET KITCHEN ORDERS ---
    elseif ($action === 'get_kitchen') {
        $sql = "SELECT o.id, o.order_id, o.customer_name, o.order_type, o.status, o.created_at,
                GROUP_CONCAT(oi.menu_item_name ORDER BY oi.id SEPARATOR ', ') AS items_list,
                COUNT(oi.id) AS item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.status IN ('pending','confirmed','preparing','ready')
                GROUP BY o.id
                ORDER BY o.created_at ASC";
        $result = $conn->query($sql);
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET DELIVERY ORDERS ---
    elseif ($action === 'get_delivery') {
        $sql = "SELECT * FROM orders WHERE order_type='online' ORDER BY created_at DESC LIMIT 50";
        $result = $conn->query($sql);
        $list = [];
        if ($result) while($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET TABLES ---
    elseif ($action === 'get_tables') {
        $result = $conn->query("SELECT * FROM tables ORDER BY table_number ASC");
        $tables = [];
        if ($result) while($row = $result->fetch_assoc()) $tables[] = $row;
        echo json_encode(['status' => 'success', 'data' => $tables]);
    }

    // --- GET REVIEWS ---
    elseif ($action === 'get_reviews') {
        // Ensure reply columns exist
        $col = $conn->query("SHOW COLUMNS FROM reviews LIKE 'admin_reply'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE reviews ADD COLUMN admin_reply TEXT DEFAULT NULL");
            $conn->query("ALTER TABLE reviews ADD COLUMN replied_at DATETIME DEFAULT NULL");
        }
        $result = $conn->query("SELECT * FROM reviews ORDER BY created_at DESC");
        $reviews = [];
        if ($result) while($row = $result->fetch_assoc()) $reviews[] = $row;
        echo json_encode(['status' => 'success', 'data' => $reviews]);
    }

    // --- GET COUPONS ---
    elseif ($action === 'get_coupons') {
        $result = $conn->query("SHOW TABLES LIKE 'coupons'");
        if ($result && $result->num_rows > 0) {
            $r2 = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
            $coupons = [];
            if ($r2) while($row = $r2->fetch_assoc()) $coupons[] = $row;
            echo json_encode(['status' => 'success', 'data' => $coupons]);
        } else {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
    }

    // --- GET EXPENSES ---
    elseif ($action === 'get_expenses') {
        $result = $conn->query("SHOW TABLES LIKE 'expenses'");
        if ($result && $result->num_rows > 0) {
            $r2 = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");
            $expenses = [];
            if ($r2) while($row = $r2->fetch_assoc()) $expenses[] = $row;
            echo json_encode(['status' => 'success', 'data' => $expenses]);
        } else {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
    }

    // --- GET EXPENSES TOTAL ---
    elseif ($action === 'get_expenses_total') {
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-d');
        $result = $conn->query("SHOW TABLES LIKE 'expenses'");
        if ($result && $result->num_rows > 0) {
            $stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
            $stmt->bind_param("ss", $start, $end);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            echo json_encode(['status' => 'success', 'data' => ['total' => $total]]);
        } else {
            echo json_encode(['status' => 'success', 'data' => ['total' => 0]]);
        }
    }

    // --- GET STATS (enhanced) ---

    // --- GET WEBSITE CONTENT ---
    elseif ($action === 'get_website_content') {
        $keys = ['banner_heading','banner_subtext','about_us','terms_conditions','privacy_policy'];
        $result = $conn->query("SELECT setting_key, setting_value FROM restaurant_settings WHERE setting_key IN ('" . implode("','", $keys) . "')");
        $content = [];
        if ($result) while($row = $result->fetch_assoc()) $content[$row['setting_key']] = $row['setting_value'];
        echo json_encode(['status' => 'success', 'data' => $content]);
    }

    // --- GET RBAC PERMISSIONS ---
    elseif ($action === 'get_rbac_permissions') {
        if ($_SESSION['user_role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Admin only']);
            exit;
        }
        echo json_encode(['status' => 'success', 'data' => rbac_get_all($conn)]);
        exit;
    }

    // --- GET SETTINGS ---
    elseif ($action === 'get_settings') {        // Ensure all required keys exist with defaults
        $default_keys = [
            'restaurant_name'       => 'Feliciano Restaurant',
            'restaurant_address'    => '',
            'restaurant_phone'      => '',
            'restaurant_email'      => '',
            'restaurant_about'      => '',
            'restaurant_logo'       => '',
            'restaurant_cover'      => '',
            'google_map_url'        => '',
            'social_facebook'       => '',
            'social_instagram'      => '',
            'social_twitter'        => '',
            'social_whatsapp'       => '',
            'social_youtube'        => '',
            'social_tiktok'         => '',
            'open_monday'           => '11:00',
            'close_monday'          => '22:00',
            'open_tuesday'          => '11:00',
            'close_tuesday'         => '22:00',
            'open_wednesday'        => '11:00',
            'close_wednesday'       => '22:00',
            'open_thursday'         => '11:00',
            'close_thursday'        => '22:00',
            'open_friday'           => '11:00',
            'close_friday'          => '23:00',
            'open_saturday'         => '11:00',
            'close_saturday'        => '23:00',
            'open_sunday'           => '12:00',
            'close_sunday'          => '21:00',
            'closed_monday'         => '0',
            'closed_tuesday'        => '0',
            'closed_wednesday'      => '0',
            'closed_thursday'       => '0',
            'closed_friday'         => '0',
            'closed_saturday'       => '0',
            'closed_sunday'         => '0',
            'terms_conditions'      => '',
            'privacy_policy'        => '',
            'currency'              => 'TK',
            'tax_percentage'        => '0',
            'bkash_number'          => '',
            'nagad_number'          => '',
            'delivery_charge'       => '0',
            'timezone'              => 'Asia/Dhaka',
        ];
        $ins = $conn->prepare("INSERT IGNORE INTO restaurant_settings (setting_key, setting_value) VALUES (?,?)");
        foreach ($default_keys as $k => $v) {
            $ins->bind_param("ss", $k, $v);
            $ins->execute();
        }

        $sql = "SELECT setting_key, setting_value FROM restaurant_settings";
        $result = $conn->query($sql);
        $settings = [];
        while($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode(['status' => 'success', 'data' => $settings]);
    }

    // --- GET NOTIFICATIONS ---
    elseif ($action === 'get_notifications') {
        $sql = "SELECT * FROM admin_notifications ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $notifications = [];
        while($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $notifications]);
    }

    // --- GET CATEGORIES ---
    elseif ($action === 'get_categories') {
        // Seed from config if empty
        $countRes = $conn->query("SELECT COUNT(*) as c FROM menu_categories");
        $count = $countRes ? (int)$countRes->fetch_assoc()['c'] : 0;
        if ($count === 0) {
            // Default categories from config — import them automatically
            $defaults = [
                ['Breakfast',          'breakfast',           'fa-egg',           0],
                ['Platters',           'platter',             'fa-plate-wheat',    1],
                ['Meal Deals',         'meal-deal',           'fa-boxes-stacked',  2],
                ['Signature Dishes',   'signature',           'fa-star',           3],
                ['Pizza',              'pizza',               'fa-pizza-slice',    4],
                ['Burger',             'burger',              'fa-burger',         5],
                ['Pasta & Chowmein',   'pasta & chowmein',    'fa-bowl-food',      6],
                ['Sandwiches',         'sandwiches',          'fa-sandwich',       7],
                ['Savory Waffle',      'savory waffle',       'fa-waffle',         8],
                ['Soup & Ramen',       'soup & ramen',        'fa-bowl-hot',       9],
                ['Fresh Salad',        'fresh salad',         'fa-leaf',          10],
                ['Dessert',            'dessert',             'fa-ice-cream',     11],
                ['Sugary Waffle',      'sugary waffle',       'fa-waffle',        12],
                ['Frappuccino',        'frappuccino',         'fa-blender',       13],
                ['Mocktail',           'mocktail',            'fa-glass-water',   14],
                ['Milk Shake',         'milk shake',          'fa-glass-citrus',  15],
                ['Fresh Juice',        'fresh juice',         'fa-lemon',         16],
                ['Hot Coffee',         'hot coffee',          'fa-mug-hot',       17],
                ['Iced Coffee',        'iced coffee',         'fa-mug-saucer',    18],
                ['Side Dishes',        'side dishes',         'fa-utensils',      19],
                ['Early Meal Add-ons', 'early meal add ons',  'fa-plus-circle',   20],
            ];
            $ins = $conn->prepare("INSERT IGNORE INTO menu_categories (name, slug, icon, sort_order) VALUES (?,?,?,?)");
            foreach ($defaults as $d) {
                $ins->bind_param("sssi", $d[0], $d[1], $d[2], $d[3]);
                $ins->execute();
            }
        }

        // Fetch with menu item counts
        $sql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM menu_items m WHERE LOWER(TRIM(m.category)) = LOWER(TRIM(c.slug))) as item_count
                FROM menu_categories c
                ORDER BY c.sort_order ASC, c.name ASC";
        $result = $conn->query($sql);
        $list = [];
        if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;

        // Stats
        $active   = count(array_filter($list, fn($r) => $r['status'] === 'active'));
        $inactive = count(array_filter($list, fn($r) => $r['status'] === 'inactive'));
        $total_items = (int)($conn->query("SELECT COUNT(*) as c FROM menu_items")->fetch_assoc()['c'] ?? 0);

        echo json_encode([
            'status' => 'success',
            'data'   => $list,
            'stats'  => [
                'total'       => count($list),
                'active'      => $active,
                'inactive'    => $inactive,
                'total_items' => $total_items,
            ]
        ]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // --- UPDATE ORDER STATUS ---
    if ($action === 'update_order_status') {
        if (!rbac_can('orders')) { echo json_encode(['status'=>'error','message'=>'Access denied']); exit; }
        $order_id = $input['order_id']; // This is the DB ID
        $status = $input['status'];
        
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE ORDER DETAILS (OVERRIDE) ---
    elseif ($action === 'update_order_details') {
        $order_id = $input['order_id'];
        $customer_email = $input['customer_email'];
        $total_amount = $input['total_amount'];
        
        $stmt = $conn->prepare("UPDATE orders SET customer_email = ?, total_amount = ? WHERE id = ?");
        $stmt->bind_param("sdi", $customer_email, $total_amount, $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- DELETE ORDER ---
    elseif ($action === 'delete_order') {
        $order_id = $input['order_id'];
        
        // Items cascade delete based on DB setup, but let's delete them explicitly just in case
        $conn->query("DELETE FROM order_items WHERE order_id = " . intval($order_id));
        
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE USER ROLE ---
    elseif ($action === 'update_user_role') {
        $user_id = $input['user_id'];
        $role = $input['role'];
        
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- CREATE USER (STAFF/ADMIN) ---
    elseif ($action === 'create_user') {
        // শুধু admin এই action করতে পারবে
        if ($_SESSION['user_role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Only admin can create staff accounts.']);
            exit;
        }

        $first  = trim($_POST['first_name'] ?? '');
        $last   = trim($_POST['last_name']  ?? '');
        $email  = trim($_POST['email']      ?? '');
        $phone  = trim($_POST['phone']      ?? '');
        $role   = $_POST['role']            ?? 'waiter';
        $status = $_POST['status']          ?? 'active';
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $full_name = $first . ' ' . $last;

        $allowed_roles = ['manager','chef','waiter','cashier'];
        if (!in_array($role, $allowed_roles)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid role.']);
            exit;
        }

        if (empty($_POST['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Password is required for new staff.']);
            exit;
        }
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, full_name, email, phone, password, role, status, branch_id, terms_accepted) VALUES (?,?,?,?,?,?,?,?,?,1)");
        $stmt->bind_param("ssssssssi", $first, $last, $full_name, $email, $phone, $password, $role, $status, $branch_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Email might already exist: ' . $conn->error]);
        }
    }

    // --- UPDATE USER ---
    elseif ($action === 'update_user') {
        // শুধু admin staff account edit করতে পারবে
        if ($_SESSION['user_role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Only admin can edit staff accounts.']);
            exit;
        }

        $id = (int)$_POST['id'];
        if ($id == 1) {
            echo json_encode(['status' => 'error', 'message' => 'Main Admin account cannot be modified.']);
            exit;
        }

        $first  = trim($_POST['first_name'] ?? '');
        $last   = trim($_POST['last_name']  ?? '');
        $email  = trim($_POST['email']      ?? '');
        $phone  = trim($_POST['phone']      ?? '');
        $role   = $_POST['role']   ?? 'waiter';
        $status = $_POST['status'] ?? 'active';
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $full_name = $first . ' ' . $last;

        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET first_name=?,last_name=?,full_name=?,email=?,phone=?,role=?,status=?,branch_id=?,password=? WHERE id=?");
            $stmt->bind_param("ssssssssii", $first, $last, $full_name, $email, $phone, $role, $status, $branch_id, $password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name=?,last_name=?,full_name=?,email=?,phone=?,role=?,status=?,branch_id=? WHERE id=?");
            $stmt->bind_param("sssssssii", $first, $last, $full_name, $email, $phone, $role, $status, $branch_id, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- DELETE USER ---
    elseif ($action === 'delete_user') {
        // শুধু admin staff account delete করতে পারবে
        if ($_SESSION['user_role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Only admin can delete staff accounts.']);
            exit;
        }

        $id = $input['id'] ?? $_POST['id'];
        if(!$id) die(json_encode(['status' => 'error', 'message' => 'No ID']));
        
        if ($id == 1) { // Primary admin protection
            die(json_encode(['status' => 'error', 'message' => 'Main Admin account cannot be deleted.']));
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'"); // Protect against self-deletion/main admin
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }
    
    // --- ADD MENU ITEM ---
    elseif ($action === 'add_menu_item') {
        if (!rbac_can('menu-management')) { echo json_encode(['status'=>'error','message'=>'Access denied']); exit; }
        $name  = $_POST['name'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $description = $_POST['description'] ?? '';
        $ingredients = $_POST['ingredients'] ?? '';
        $calories  = !empty($_POST['calories'])    ? (int)$_POST['calories']    : null;
        $prep_time = !empty($_POST['prep_time'])   ? (int)$_POST['prep_time']   : null;
        $cooking_time = !empty($_POST['cooking_time']) ? (int)$_POST['cooking_time'] : null;
        $nutrition = $_POST['nutrition_info']    ?? '';
        $allergens = $_POST['allergens']         ?? '';
        $availability_time = $_POST['availability_time'] ?? '';
        $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $is_featured    = isset($_POST['is_featured'])    ? 1 : 0;
        $is_popular     = isset($_POST['is_popular'])     ? 1 : 0;
        $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
        $is_special     = isset($_POST['is_special'])     ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        $in_stock       = isset($_POST['in_stock']) ? 1 : 0;
        $stock_quantity = isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? (int)$_POST['stock_quantity'] : null;

        // Auto-add extra columns if missing
        $extra_cols = [
            "cooking_time INT DEFAULT NULL",
            "nutrition_info TEXT DEFAULT NULL",
            "allergens TEXT DEFAULT NULL",
            "availability_time VARCHAR(100) DEFAULT NULL",
            "in_stock TINYINT(1) NOT NULL DEFAULT 1",
            "stock_quantity INT DEFAULT NULL"
        ];
        foreach ($extra_cols as $col_def) {
            $col_name = explode(' ', $col_def)[0];
            $chk = $conn->query("SHOW COLUMNS FROM menu_items LIKE '$col_name'");
            if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE menu_items ADD COLUMN $col_def");
        }

        $image_url = isset($_FILES['image_file']) ? uploadAndResizeImage($_FILES['image_file']) : null;
        if (!$image_url) $image_url = 'assets/images/menu/default.jpg';

        $stmt = $conn->prepare("INSERT INTO menu_items (name, category, price, discount_price, description, image_url, ingredients, calories, prep_time, cooking_time, nutrition_info, allergens, availability_time, is_featured, is_popular, is_recommended, is_special, status, in_stock, stock_quantity) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssddsssiiisssiiiisii",
            $name, $category, $price, $discount_price, $description, $image_url,
            $ingredients, $calories, $prep_time, $cooking_time, $nutrition,
            $allergens, $availability_time,
            $is_featured, $is_popular, $is_recommended, $is_special,
            $status, $in_stock, $stock_quantity
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Menu item added successfully!', 'new_id' => $conn->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE MENU ITEM ---
    elseif ($action === 'update_menu_item') {
        if (!rbac_can('menu-management')) { echo json_encode(['status'=>'error','message'=>'Access denied']); exit; }
        $id   = (int)$_POST['id'];
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = (float)$_POST['price'];
        $description = $_POST['description'] ?? '';
        $ingredients = $_POST['ingredients'] ?? '';
        $calories  = !empty($_POST['calories'])    ? (int)$_POST['calories']    : null;
        $prep_time = !empty($_POST['prep_time'])   ? (int)$_POST['prep_time']   : null;
        $cooking_time = !empty($_POST['cooking_time']) ? (int)$_POST['cooking_time'] : null;
        $nutrition = $_POST['nutrition_info']    ?? '';
        $allergens = $_POST['allergens']         ?? '';
        $availability_time = $_POST['availability_time'] ?? '';
        $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $is_featured    = isset($_POST['is_featured'])    ? 1 : 0;
        $is_popular     = isset($_POST['is_popular'])     ? 1 : 0;
        $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
        $is_special     = isset($_POST['is_special'])     ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        $in_stock       = isset($_POST['in_stock']) ? 1 : 0;
        $stock_quantity = isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? (int)$_POST['stock_quantity'] : null;

        // Auto-add columns if missing
        $extra_cols = [
            "in_stock TINYINT(1) NOT NULL DEFAULT 1",
            "stock_quantity INT DEFAULT NULL"
        ];
        foreach ($extra_cols as $col_def) {
            $col_name = explode(' ', $col_def)[0];
            $chk = $conn->query("SHOW COLUMNS FROM menu_items LIKE '$col_name'");
            if ($chk && $chk->num_rows === 0) $conn->query("ALTER TABLE menu_items ADD COLUMN $col_def");
        }

        $image_url = isset($_FILES['image_file']) ? uploadAndResizeImage($_FILES['image_file']) : null;

        if ($image_url) {
            $stmt = $conn->prepare("UPDATE menu_items SET name=?, category=?, price=?, discount_price=?, description=?, image_url=?, ingredients=?, calories=?, prep_time=?, cooking_time=?, nutrition_info=?, allergens=?, availability_time=?, is_featured=?, is_popular=?, is_recommended=?, is_special=?, status=?, in_stock=?, stock_quantity=? WHERE id=?");
            $stmt->bind_param("ssddsssiiisssiiiisiii",
                $name, $category, $price, $discount_price, $description, $image_url,
                $ingredients, $calories, $prep_time, $cooking_time, $nutrition,
                $allergens, $availability_time,
                $is_featured, $is_popular, $is_recommended, $is_special,
                $status, $in_stock, $stock_quantity, $id
            );
        } else {
            $stmt = $conn->prepare("UPDATE menu_items SET name=?, category=?, price=?, discount_price=?, description=?, ingredients=?, calories=?, prep_time=?, cooking_time=?, nutrition_info=?, allergens=?, availability_time=?, is_featured=?, is_popular=?, is_recommended=?, is_special=?, status=?, in_stock=?, stock_quantity=? WHERE id=?");
            $stmt->bind_param("ssddssiiisssiiiisiii",
                $name, $category, $price, $discount_price, $description,
                $ingredients, $calories, $prep_time, $cooking_time, $nutrition,
                $allergens, $availability_time,
                $is_featured, $is_popular, $is_recommended, $is_special,
                $status, $in_stock, $stock_quantity, $id
            );
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Menu item updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPLOAD GALLERY IMAGE ---
    elseif ($action === 'upload_gallery_image') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if (!$item_id) { echo json_encode(['status' => 'error', 'message' => 'No item_id']); exit; }

        // Check existing count
        $cnt_res = $conn->prepare("SELECT COUNT(*) as c FROM menu_item_images WHERE menu_item_id=?");
        $cnt_res->bind_param("i", $item_id);
        $cnt_res->execute();
        $existing = (int)$cnt_res->get_result()->fetch_assoc()['c'];
        if ($existing >= 5) {
            echo json_encode(['status' => 'error', 'message' => 'Maximum 5 gallery images allowed.']);
            exit;
        }

        if (!isset($_FILES['gallery_image']) || $_FILES['gallery_image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded.']);
            exit;
        }

        $image_url = uploadAndResizeImage($_FILES['gallery_image']);
        if (!$image_url) {
            echo json_encode(['status' => 'error', 'message' => 'Image upload failed.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO menu_item_images (menu_item_id, image_url, sort_order) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $item_id, $image_url, $existing);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id, 'image_url' => $image_url]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }

    // --- DELETE GALLERY IMAGE ---
    elseif ($action === 'delete_gallery_image') {
        $img_id = (int)($input['id'] ?? 0);
        if (!$img_id) { echo json_encode(['status' => 'error', 'message' => 'No id']); exit; }

        // Get path to optionally delete file
        $row = $conn->query("SELECT image_url FROM menu_item_images WHERE id=$img_id")->fetch_assoc();
        if ($row && $row['image_url']) {
            $full_path = realpath(__DIR__ . '/../' . $row['image_url']);
            if ($full_path && file_exists($full_path)) @unlink($full_path);
        }

        $stmt = $conn->prepare("DELETE FROM menu_item_images WHERE id=?");
        $stmt->bind_param("i", $img_id);
        echo json_encode($stmt->execute() ? ['status' => 'success'] : ['status' => 'error', 'message' => $conn->error]);
        exit;
    }

    // --- DELETE MENU ITEM ---
    elseif ($action === 'delete_menu_item') {
        if (!rbac_can('menu-management')) { echo json_encode(['status'=>'error','message'=>'Access denied']); exit; }
        $id = $input['id'];
        // Delete gallery images first
        $rows = $conn->query("SELECT image_url FROM menu_item_images WHERE menu_item_id=" . intval($id));
        if ($rows) {
            while ($r = $rows->fetch_assoc()) {
                $fp = realpath(__DIR__ . '/../' . $r['image_url']);
                if ($fp && file_exists($fp)) @unlink($fp);
            }
            $conn->query("DELETE FROM menu_item_images WHERE menu_item_id=" . intval($id));
        }
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE RESERVATION STATUS ---
    elseif ($action === 'update_reservation_status') {
        $res_id = $input['id'];
        $status = $input['status'];
        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $res_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- SAVE RBAC PERMISSIONS ---
    elseif ($action === 'save_rbac_permissions') {
        if ($_SESSION['user_role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Admin only']);
            exit;
        }
        $permissions = $input['permissions'] ?? [];
        $saved = 0;
        foreach ($permissions as $perm) {
            $role    = $perm['role']    ?? '';
            $section = $perm['section'] ?? '';
            $allowed = isset($perm['allowed']) ? (int)$perm['allowed'] : 0;
            if (rbac_save_permission($conn, $role, $section, $allowed)) $saved++;
        }
        echo json_encode(['status' => 'success', 'saved' => $saved]);
        exit;
    }

    // --- RBAC RESET DEFAULTS ---
    elseif ($action === 'rbac_reset_defaults') {
        if ($_SESSION['user_role'] !== 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'Admin only']);
            exit;
        }
        $conn->query("DELETE FROM role_permissions");
        rbac_seed_defaults($conn);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // --- UPDATE SETTINGS ---
    elseif ($action === 'update_settings') {
        if (!rbac_can('settings')) { echo json_encode(['status'=>'error','message'=>'Access denied']); exit; }
        $saved = 0;
        foreach ($input as $key => $value) {
            // Whitelist: only allow safe setting keys
            if (!preg_match('/^[a-z0-9_]+$/', $key)) continue;
            $stmt = $conn->prepare("INSERT INTO restaurant_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->bind_param("sss", $key, $value, $value);
            if ($stmt->execute()) $saved++;
        }
        echo json_encode(['status' => 'success', 'saved' => $saved]);
    }

    // --- MARK NOTIFICATION AS READ ---
    elseif ($action === 'mark_notification_read') {
        $id = $input['id'];
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- MARK ALL NOTIFICATIONS AS READ ---
    elseif ($action === 'mark_all_notifications_read') {
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- DELETE NOTIFICATION ---
    elseif ($action === 'delete_notification') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- SAVE INVENTORY ITEM ---
    elseif ($action === 'save_inventory') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'];
        $category = $_POST['category'] ?? 'ingredient';
        $unit = $_POST['unit'] ?? 'kg';
        $stock = (float)($_POST['stock_quantity'] ?? 0);
        $min = (float)($_POST['min_stock'] ?? 0);
        $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        if ($id) {
            $stmt = $conn->prepare("UPDATE inventory SET name=?, category=?, unit=?, stock_quantity=?, min_stock=?, expiry_date=? WHERE id=?");
            $stmt->bind_param("sssddsi", $name, $category, $unit, $stock, $min, $expiry, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO inventory (name, category, unit, stock_quantity, min_stock, expiry_date) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssdds", $name, $category, $unit, $stock, $min, $expiry);
        }
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE INVENTORY ITEM ---
    elseif ($action === 'delete_inventory') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE SUPPLIER ---
    elseif ($action === 'save_supplier') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'];
        $contact = $_POST['contact_person'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $products = $_POST['products'] ?? '';
        $address = $_POST['address'] ?? '';
        if ($id) {
            $stmt = $conn->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, products=?, address=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $contact, $phone, $email, $products, $address, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, email, products, address) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssss", $name, $contact, $phone, $email, $products, $address);
        }
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE SUPPLIER ---
    elseif ($action === 'delete_supplier') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE PURCHASE ---
    elseif ($action === 'save_purchase') {
        $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $items = $_POST['items'] ?? '';
        $amount = (float)($_POST['total_amount'] ?? 0);
        $date = $_POST['purchase_date'] ?? date('Y-m-d');
        $payment = $_POST['payment_status'] ?? 'pending';
        $po = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $supplier_name = '';
        if ($supplier_id) {
            $s = $conn->prepare("SELECT name FROM suppliers WHERE id=?");
            $s->bind_param("i", $supplier_id);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $supplier_name = $row['name'] ?? '';
        }
        $stmt = $conn->prepare("INSERT INTO purchases (po_number, supplier_id, supplier_name, items, total_amount, purchase_date, payment_status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sissdss", $po, $supplier_id, $supplier_name, $items, $amount, $date, $payment);        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- UPDATE PURCHASE STATUS ---
    elseif ($action === 'update_purchase_status') {
        $id = $input['id'];
        $field = $input['field'] === 'payment_status' ? 'payment_status' : 'status';
        $val = $input['value'];
        $stmt = $conn->prepare("UPDATE purchases SET $field=? WHERE id=?");
        $stmt->bind_param("si", $val, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE PURCHASE ---
    elseif ($action === 'delete_purchase') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM purchases WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- UPLOAD GALLERY IMAGE ---
    elseif ($action === 'upload_gallery') {
        $target_dir = '../assets/images/gallery/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $title = $_POST['title'] ?? '';
        $category = $_POST['category'] ?? 'food';
        $uploaded = 0;
        if (isset($_FILES['images'])) {
            $files = $_FILES['images'];
            $count = is_array($files['name']) ? count($files['name']) : 1;
            for ($i = 0; $i < $count; $i++) {
                $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif'];
                if (!in_array($ext, $allowed)) continue;
                $filename = 'gallery_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmp, $target_dir . $filename)) {
                    $url = 'assets/images/gallery/' . $filename;
                    $stmt = $conn->prepare("INSERT INTO gallery (title, image_url, category) VALUES (?,?,?)");
                    $stmt->bind_param("sss", $title, $url, $category);
                    $stmt->execute();
                    $uploaded++;
                }
            }
        }
        echo json_encode(['status' => 'success', 'uploaded' => $uploaded]);
    }

    // --- DELETE GALLERY IMAGE ---
    elseif ($action === 'delete_gallery') {
        $id = $input['id'];
        $row = $conn->query("SELECT image_url FROM gallery WHERE id=$id")->fetch_assoc();
        if ($row) { $file = '../' . $row['image_url']; if (file_exists($file)) unlink($file); }
        $stmt = $conn->prepare("DELETE FROM gallery WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE EVENT ---
    elseif ($action === 'save_event') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'];
        $type = $_POST['type'] ?? 'other';
        $desc = $_POST['description'] ?? '';
        $date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
        $capacity = (int)($_POST['capacity'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        if ($id) {
            $stmt = $conn->prepare("UPDATE events SET name=?, type=?, description=?, event_date=?, capacity=?, price=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssdi", $name, $type, $desc, $date, $capacity, $price, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO events (name, type, description, event_date, capacity, price, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssids", $name, $type, $desc, $date, $capacity, $price, $status);
        }
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE EVENT ---
    elseif ($action === 'delete_event') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- UPDATE BILLING PAYMENT STATUS ---
    elseif ($action === 'update_payment_status') {
        $id = $input['order_id'];
        $status = $input['payment_status'];
        $stmt = $conn->prepare("UPDATE orders SET payment_status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE TABLE ---
    elseif ($action === 'save_table') {
        $id = $_POST['id'] ?? '';
        $table_number = $_POST['table_number'];
        $capacity = (int)$_POST['capacity'];
        $location = $_POST['location'] ?? '';
        $status = $_POST['status'] ?? 'available';
        if ($id) {
            $stmt = $conn->prepare("UPDATE tables SET table_number=?, capacity=?, location=?, status=? WHERE id=?");
            $stmt->bind_param("sissi", $table_number, $capacity, $location, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO tables (table_number, capacity, location, status) VALUES (?,?,?,?)");
            $stmt->bind_param("siss", $table_number, $capacity, $location, $status);
        }
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE TABLE ---
    elseif ($action === 'delete_table') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM tables WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- UPDATE CUSTOMER STATUS (block/unblock) ---
    elseif ($action === 'update_customer_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- UPDATE REVIEW STATUS ---
    elseif ($action === 'update_review_status') {
        $id = $input['id'];
        $status = $input['status'];
        $stmt = $conn->prepare("UPDATE reviews SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE REVIEW ---
    elseif ($action === 'delete_review') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE EXPENSE ---
    elseif ($action === 'save_expense') {
        $id = $_POST['id'] ?? '';
        $date = $_POST['expense_date'];
        $cat = $_POST['category'];
        $desc = $_POST['description'] ?? '';
        $amount = (float)$_POST['amount'];
        if ($id) {
            $stmt = $conn->prepare("UPDATE expenses SET expense_date=?, category=?, description=?, amount=? WHERE id=?");
            $stmt->bind_param("sssdi", $date, $cat, $desc, $amount, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, description, amount) VALUES (?,?,?,?)");
            $stmt->bind_param("sssd", $date, $cat, $desc, $amount);
        }
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE EXPENSE ---
    elseif ($action === 'delete_expense') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE COUPON ---
    elseif ($action === 'save_coupon') {
        $id = $_POST['id'] ?? '';
        $code = strtoupper($_POST['code']);
        $type = $_POST['discount_type'];
        $val = (float)$_POST['discount_value'];
        $min = (float)($_POST['min_order'] ?? 0);
        $limit = (int)($_POST['usage_limit'] ?? 0);
        $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $status = $_POST['status'] ?? 'active';
        if ($id) {
            $stmt = $conn->prepare("UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_order=?, usage_limit=?, expiry_date=?, status=? WHERE id=?");
            $stmt->bind_param("ssddiisi", $code, $type, $val, $min, $limit, $expiry, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order, usage_limit, expiry_date, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssddiis", $code, $type, $val, $min, $limit, $expiry, $status);
        }
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE COUPON ---
    elseif ($action === 'delete_coupon') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM coupons WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE COMBO MEAL ---
    elseif ($action === 'save_combo') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'];
        $desc = $_POST['description'] ?? '';
        $items = $_POST['items'] ?? '';
        $original_price = (float)($_POST['original_price'] ?? 0);
        $price = (float)$_POST['price'];
        $status = $_POST['status'] ?? 'active';
        if ($id) {
            $stmt = $conn->prepare("UPDATE combo_meals SET name=?, description=?, items=?, original_price=?, price=?, status=? WHERE id=?");
            $stmt->bind_param("ssssdsi", $name, $desc, $items, $original_price, $price, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO combo_meals (name, description, items, original_price, price, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssdds", $name, $desc, $items, $original_price, $price, $status);
        }
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE COMBO MEAL ---
    elseif ($action === 'delete_combo') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM combo_meals WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- CHANGE PASSWORD ---
    elseif ($action === 'change_password') {
        $user_id = $_SESSION['user_id'];
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || !password_verify($current, $row['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param("si", $hash, $user_id);
            if ($upd->execute()) echo json_encode(['status' => 'success']);
            else echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPLOAD RESTAURANT LOGO ---
    elseif ($action === 'upload_logo') {
        $target_dir = '../assets/images/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
                exit;
            }
            $filename = 'restaurant_logo.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_dir . $filename)) {
                $url = 'assets/images/' . $filename;
                $stmt = $conn->prepare("INSERT INTO restaurant_settings (setting_key, setting_value) VALUES ('restaurant_logo',?) ON DUPLICATE KEY UPDATE setting_value=?");
                $stmt->bind_param("ss", $url, $url);
                $stmt->execute();
                echo json_encode(['status' => 'success', 'url' => $url]);
            } else echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
        } else echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    }

    // --- UPLOAD RESTAURANT COVER IMAGE ---
    elseif ($action === 'upload_cover') {
        $target_dir = '../assets/images/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
                exit;
            }
            // Delete old cover if exists
            $old = $conn->query("SELECT setting_value FROM restaurant_settings WHERE setting_key='restaurant_cover'");
            if ($old && $old->num_rows > 0) {
                $old_url = $old->fetch_assoc()['setting_value'];
                $old_file = '../' . $old_url;
                if ($old_url && file_exists($old_file) && strpos($old_url, 'restaurant_cover') !== false) {
                    @unlink($old_file);
                }
            }
            $filename = 'restaurant_cover_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $target_dir . $filename)) {
                $url = 'assets/images/' . $filename;
                $stmt = $conn->prepare("INSERT INTO restaurant_settings (setting_key, setting_value) VALUES ('restaurant_cover',?) ON DUPLICATE KEY UPDATE setting_value=?");
                $stmt->bind_param("ss", $url, $url);
                $stmt->execute();
                echo json_encode(['status' => 'success', 'url' => $url]);
            } else echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
        } else echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    }

    // --- SAVE STAFF RATING ---
    elseif ($action === 'save_staff_rating') {
        $user_id = (int)$_POST['user_id'];
        $month   = $_POST['month'] ?? date('Y-m');
        $rating  = (float)$_POST['rating'];
        $review  = $conn->real_escape_string($_POST['review'] ?? '');

        $stmt = $conn->prepare("INSERT INTO staff_ratings (user_id, month, rating, review)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE rating=VALUES(rating), review=VALUES(review)");
        $stmt->bind_param("isds", $user_id, $month, $rating, $review);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE STAFF ATTENDANCE ---
    elseif ($action === 'save_attendance') {
        $user_id = (int)$_POST['user_id'];
        $date = $_POST['attendance_date'];
        $status = $_POST['status'];
        $check_in = !empty($_POST['check_in']) ? $_POST['check_in'] : null;
        $check_out = !empty($_POST['check_out']) ? $_POST['check_out'] : null;
        $notes = $_POST['notes'] ?? '';
        $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, attendance_date, status, check_in, check_out, notes) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), check_in=VALUES(check_in), check_out=VALUES(check_out), notes=VALUES(notes)");
        $stmt->bind_param("isssss", $user_id, $date, $status, $check_in, $check_out, $notes);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE STAFF SALARY ---
    elseif ($action === 'save_salary') {
        $user_id = (int)$_POST['user_id'];
        $month = $_POST['month'];
        $base = (float)$_POST['base_salary'];
        $bonus = (float)($_POST['bonus'] ?? 0);
        $deduction = (float)($_POST['deduction'] ?? 0);
        $net = $base + $bonus - $deduction;
        $pay_status = $_POST['payment_status'] ?? 'pending';
        $notes = $_POST['notes'] ?? '';
        $stmt = $conn->prepare("INSERT INTO staff_salary (user_id, month, base_salary, bonus, deduction, net_salary, payment_status, notes) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary), bonus=VALUES(bonus), deduction=VALUES(deduction), net_salary=VALUES(net_salary), payment_status=VALUES(payment_status), notes=VALUES(notes)");
        $stmt->bind_param("isddddss", $user_id, $month, $base, $bonus, $deduction, $net, $pay_status, $notes);
        if ($stmt->execute()) echo json_encode(['status' => 'success', 'net' => $net]);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- ASSIGN TABLE TO RESERVATION ---
    elseif ($action === 'assign_table') {
        $resv_id = (int)$input['reservation_id'];
        $table_id = (int)$input['table_id'];
        // Check if reservations table has table_id column
        $col = $conn->query("SHOW COLUMNS FROM reservations LIKE 'table_id'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE reservations ADD COLUMN table_id INT DEFAULT NULL");
        }
        $stmt = $conn->prepare("UPDATE reservations SET table_id=? WHERE id=?");
        $stmt->bind_param("ii", $table_id, $resv_id);
        if ($stmt->execute()) {
            // Also mark table as reserved
            $conn->prepare("UPDATE tables SET status='reserved' WHERE id=?")->bind_param("i", $table_id);
            $conn->prepare("UPDATE tables SET status='reserved' WHERE id=?")->execute();
            $ts = $conn->prepare("UPDATE tables SET status='reserved' WHERE id=?");
            $ts->bind_param("i", $table_id);
            $ts->execute();
            echo json_encode(['status' => 'success']);
        } else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- ASSIGN RIDER TO DELIVERY ---
    elseif ($action === 'assign_rider') {
        $order_id = (int)$input['order_id'];
        $rider_name = $conn->real_escape_string($input['rider_name'] ?? '');
        $col = $conn->query("SHOW COLUMNS FROM orders LIKE 'rider_name'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN rider_name VARCHAR(100) DEFAULT NULL");
        }
        $stmt = $conn->prepare("UPDATE orders SET rider_name=? WHERE id=?");
        $stmt->bind_param("si", $rider_name, $order_id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- SAVE COUPON (BOGO support) ---
    // Already handled above, updated type ENUM includes 'bogo' via save_coupon

    // --- SAVE LOYALTY POINTS ---
    elseif ($action === 'update_loyalty_points') {
        $user_id = (int)$_POST['user_id'];
        $points = (int)$_POST['points'];
        $stmt = $conn->prepare("INSERT INTO customer_loyalty (user_id, points, total_earned) VALUES (?,?,?) ON DUPLICATE KEY UPDATE points=?, total_earned=total_earned+?");
        $stmt->bind_param("iiiii", $user_id, $points, $points, $points, $points);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- ADD / UPDATE REVIEW REPLY ---
    elseif ($action === 'reply_review') {
        $id    = (int)($input['id'] ?? 0);
        $reply = trim($input['reply'] ?? '');
        if (!$id || $reply === '') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
            exit;
        }
        // Add columns if they don't exist yet
        $col = $conn->query("SHOW COLUMNS FROM reviews LIKE 'admin_reply'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE reviews ADD COLUMN admin_reply TEXT DEFAULT NULL");
            $conn->query("ALTER TABLE reviews ADD COLUMN replied_at DATETIME DEFAULT NULL");
        }
        $now  = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE reviews SET admin_reply=?, replied_at=? WHERE id=?");
        $stmt->bind_param("ssi", $reply, $now, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DATABASE BACKUP (JSON) ---
    elseif ($action === 'backup_database') {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) $tables[] = $row[0];
        $sql = "-- Feliciano Restaurant DB Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($tables as $table) {
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $r = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $r->fetch_row();
            $sql .= $row[1] . ";\n\n";
            $rows = $conn->query("SELECT * FROM `$table`");
            while ($row = $rows->fetch_row()) {
                $vals = array_map(function($v) use ($conn) {
                    return is_null($v) ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
                }, $row);
                $sql .= "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $filename = 'backup_' . date('Ymd_His') . '.sql';
        echo json_encode(['status' => 'success', 'filename' => $filename, 'sql' => base64_encode($sql)]);
    }

    // --- DATABASE BACKUP (Direct Download) ---
    elseif ($action === 'download_backup') {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) $tables[] = $row[0];
        $sql = "-- Feliciano Restaurant Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Host: localhost\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
        foreach ($tables as $table) {
            $sql .= "-- ----------------------------------------\n";
            $sql .= "-- Table: `$table`\n";
            $sql .= "-- ----------------------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $r = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $r->fetch_row();
            $sql .= $row[1] . ";\n\n";
            $rows = $conn->query("SELECT * FROM `$table`");
            if ($rows && $rows->num_rows > 0) {
                while ($row = $rows->fetch_row()) {
                    $vals = array_map(function($v) use ($conn) {
                        return is_null($v) ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
                    }, $row);
                    $sql .= "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n";
                }
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $filename = 'feliciano_backup_' . date('Ymd_His') . '.sql';
        // Override JSON header
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $sql;
        exit;
    }
}

// =========================================
// GET ENDPOINTS (MISSING)
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // --- GET STAFF ATTENDANCE ---
    if ($action === 'get_attendance') {
        $month = $_GET['month'] ?? date('Y-m');
        $sql = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as staff_name, u.role
                FROM staff_attendance a
                JOIN users u ON a.user_id = u.id
                WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
                ORDER BY a.attendance_date DESC, u.first_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $list = [];
        while ($row = $result->fetch_assoc()) $list[] = $row;
        // Summary stats
        $total = count($list);
        $present = count(array_filter($list, fn($r) => $r['status'] === 'present'));
        $absent = count(array_filter($list, fn($r) => $r['status'] === 'absent'));
        echo json_encode(['status' => 'success', 'data' => $list, 'stats' => ['total' => $total, 'present' => $present, 'absent' => $absent]]);
    }

    // --- GET SALARY RECORDS ---
    elseif ($action === 'get_salary') {
        $month = $_GET['month'] ?? date('Y-m');
        $sql = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as staff_name, u.role
                FROM staff_salary s
                JOIN users u ON s.user_id = u.id
                WHERE s.month = ?
                ORDER BY u.first_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $list = [];
        while ($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET CUSTOMER LOYALTY ---
    elseif ($action === 'get_loyalty') {
        $sql = "SELECT l.*, u.full_name, u.email FROM customer_loyalty l
                JOIN users u ON l.user_id = u.id ORDER BY l.points DESC";
        $result = $conn->query($sql);
        $list = [];
        if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET LOGIN HISTORY ---
    elseif ($action === 'get_login_history') {
        $result = $conn->query("SELECT * FROM login_history ORDER BY created_at DESC LIMIT 50");
        $list = [];
        if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- GET STAFF PERFORMANCE ---
    elseif ($action === 'get_staff_performance') {
        $month     = $_GET['month']       ?? date('Y-m');
        $role_filter = $_GET['role']      ?? 'all';

        // Staff list (non-customer)
        $role_where = ($role_filter !== 'all') ? " AND u.role = '" . $conn->real_escape_string($role_filter) . "'" : "";
        $staff_sql = "SELECT u.id, u.full_name, u.first_name, u.last_name, u.role, u.status, u.branch_id
                      FROM users u
                      WHERE u.role NOT IN ('customer') $role_where
                      ORDER BY u.full_name ASC";
        $staff_res = $conn->query($staff_sql);
        $staff_list = [];
        if ($staff_res) while ($s = $staff_res->fetch_assoc()) $staff_list[] = $s;

        $month_start = $month . '-01';
        $month_end   = date('Y-m-t', strtotime($month_start));

        foreach ($staff_list as &$s) {
            $uid = (int)$s['id'];

            // Orders handled (waiter/cashier: orders in this month)
            $ord_res = $conn->query("SELECT COUNT(*) as c FROM orders
                WHERE DATE(created_at) BETWEEN '$month_start' AND '$month_end'
                AND status NOT IN ('cancelled')");
            $s['orders_handled'] = 0;
            if (in_array($s['role'], ['waiter', 'cashier', 'manager', 'chef'])) {
                // Per-user order handling not tracked at row level yet; show branch total as reference
                $ord_res2 = $conn->query("SELECT COUNT(*) as c FROM orders
                    WHERE DATE(created_at) BETWEEN '$month_start' AND '$month_end'
                    AND status NOT IN ('cancelled')");
                // Individual tracking requires order assignment — show 0 until assigned
                $s['orders_handled'] = 0;
            }

            // Deliveries completed — not applicable for remaining roles
            $s['deliveries'] = 0;

            // Attendance % this month
            $att_res = $conn->prepare("SELECT
                COUNT(*) as total_marked,
                SUM(status IN ('present','late')) as days_present
                FROM staff_attendance
                WHERE user_id=? AND DATE_FORMAT(attendance_date,'%Y-%m')=?");
            $s['attendance_pct'] = null;
            if ($att_res) {
                $att_res->bind_param("is", $uid, $month);
                $att_res->execute();
                $att_row = $att_res->get_result()->fetch_assoc();
                if ($att_row && (int)$att_row['total_marked'] > 0) {
                    $s['attendance_pct'] = round(((int)$att_row['days_present'] / (int)$att_row['total_marked']) * 100);
                }
            }

            // Rating (from staff_ratings table if exists, else null)
            $s['rating'] = null;
            $s['rating_review'] = '';
            $rat_check = $conn->query("SHOW TABLES LIKE 'staff_ratings'");
            if ($rat_check && $rat_check->num_rows > 0) {
                $rat = $conn->prepare("SELECT rating, review FROM staff_ratings WHERE user_id=? AND month=? ORDER BY id DESC LIMIT 1");
                if ($rat) {
                    $rat->bind_param("is", $uid, $month);
                    $rat->execute();
                    $rat_row = $rat->get_result()->fetch_assoc();
                    if ($rat_row) {
                        $s['rating'] = (float)$rat_row['rating'];
                        $s['rating_review'] = $rat_row['review'] ?? '';
                    }
                }
            }
        }
        unset($s);

        // Summary stats
        $total = count($staff_list);
        $rated = count(array_filter($staff_list, fn($s) => $s['rating'] !== null));
        $avg_rating = $rated > 0
            ? round(array_sum(array_column(array_filter($staff_list, fn($s) => $s['rating'] !== null), 'rating')) / $rated, 1)
            : 0;

        echo json_encode([
            'status' => 'success',
            'data'   => $staff_list,
            'stats'  => ['total' => $total, 'rated' => $rated, 'avg_rating' => $avg_rating],
        ]);
        exit;
    }

    // --- GET STAFF PROFILE DETAIL ---
    elseif ($action === 'get_staff_detail') {
        $uid = (int)($_GET['id'] ?? 0);
        if (!$uid) { echo json_encode(['status'=>'error','message'=>'No ID']); exit; }

        $res = $conn->prepare("SELECT u.*, b.name as branch_name
            FROM users u LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.id=? LIMIT 1");
        $res->bind_param("i", $uid);
        $res->execute();
        $user = $res->get_result()->fetch_assoc();
        if (!$user) { echo json_encode(['status'=>'error','message'=>'Not found']); exit; }

        // Total orders this month
        $month = date('Y-m');
        $month_start = $month . '-01';
        $month_end   = date('Y-m-t', strtotime($month_start));

        // Attendance this month
        $att = $conn->prepare("SELECT
            COUNT(*) as total_marked,
            SUM(status IN ('present','late')) as days_present,
            SUM(status='absent') as days_absent
            FROM staff_attendance WHERE user_id=? AND DATE_FORMAT(attendance_date,'%Y-%m')=?");
        $att->bind_param("is", $uid, $month);
        $att->execute();
        $att_data = $att->get_result()->fetch_assoc();

        // Latest salary
        $sal = $conn->prepare("SELECT net_salary, month, payment_status FROM staff_salary WHERE user_id=? ORDER BY month DESC LIMIT 1");
        $sal->bind_param("i", $uid);
        $sal->execute();
        $sal_data = $sal->get_result()->fetch_assoc();

        // Latest rating
        $rating = null;
        $rat_check = $conn->query("SHOW TABLES LIKE 'staff_ratings'");
        if ($rat_check && $rat_check->num_rows > 0) {
            $rat = $conn->prepare("SELECT rating, review, month FROM staff_ratings WHERE user_id=? ORDER BY id DESC LIMIT 1");
            $rat->bind_param("i", $uid);
            $rat->execute();
            $rating = $rat->get_result()->fetch_assoc();
        }

        echo json_encode([
            'status'     => 'success',
            'user'       => $user,
            'attendance' => $att_data,
            'salary'     => $sal_data,
            'rating'     => $rating,
        ]);
        exit;
    }

    // --- GET WEEKLY SALES ---
    elseif ($action === 'get_weekly_sales') {
        $sql = "SELECT DATE(created_at) as sale_date, IFNULL(SUM(total_amount),0) as revenue, COUNT(*) as orders
                FROM orders WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at) ORDER BY sale_date ASC";
        $result = $conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        $weekly_total = array_sum(array_column($data, 'revenue'));
        echo json_encode(['status' => 'success', 'data' => $data, 'weekly_total' => $weekly_total]);
    }

    // --- GET CUSTOMER DETAIL ---
    elseif ($action === 'get_customer_detail') {
        $id = (int)$_GET['id'];
        // Orders
        $orders = [];
        $oRes = $conn->query("SELECT * FROM orders WHERE customer_email=(SELECT email FROM users WHERE id=$id) ORDER BY created_at DESC LIMIT 10");
        if ($oRes) while ($r = $oRes->fetch_assoc()) $orders[] = $r;
        // Reservations
        $resvs = [];
        $rRes = $conn->query("SELECT * FROM reservations WHERE email=(SELECT email FROM users WHERE id=$id) ORDER BY reservation_date DESC LIMIT 10");
        if ($rRes) while ($r = $rRes->fetch_assoc()) $resvs[] = $r;
        // Loyalty
        $loyalty = $conn->query("SELECT * FROM customer_loyalty WHERE user_id=$id")->fetch_assoc() ?? ['points' => 0];
        echo json_encode(['status' => 'success', 'orders' => $orders, 'reservations' => $resvs, 'loyalty' => $loyalty]);
    }

    // ============================================================
    // GET SYSTEM LOGS
    // ============================================================
    elseif ($action === 'get_system_logs') {

        // ── Seed from orders ───────────────────────────────────────
        $oSeed = $conn->query(
            "SELECT o.id, o.customer_name, o.total_amount, o.status, o.payment_status,
                    o.payment_method, o.created_at
             FROM orders o
             LEFT JOIN system_logs sl
                ON sl.category='order' AND sl.related_id=o.id
             WHERE sl.id IS NULL
             ORDER BY o.created_at DESC LIMIT 200"
        );
        if ($oSeed) {
            while ($o = $oSeed->fetch_assoc()) {
                $msg   = $conn->real_escape_string("Order #{$o['id']} placed by ".($o['customer_name']??'Guest')." — TK {$o['total_amount']}");
                $stat  = $conn->real_escape_string($o['status'] ?? 'pending');
                $name  = $conn->real_escape_string($o['customer_name'] ?? 'Guest');
                $ts    = $conn->real_escape_string($o['created_at']);
                $conn->query("INSERT INTO system_logs (category,event_type,message,related_id,related_label,status,created_at)
                              VALUES ('order','Order Placed','$msg',{$o['id']},'$name','$stat','$ts')");

                // Payment log entry
                if (!empty($o['payment_method'])) {
                    $pm   = $conn->real_escape_string($o['payment_method']);
                    $pst  = $conn->real_escape_string($o['payment_status'] ?? 'pending');
                    $pmsg = $conn->real_escape_string("Payment via {$o['payment_method']} for Order #{$o['id']} — TK {$o['total_amount']}");
                    $conn->query("INSERT INTO system_logs (category,event_type,message,related_id,related_label,status,created_at)
                                  VALUES ('payment','Payment Received','$pmsg',{$o['id']},'$pm','$pst','$ts')");
                }
            }
        }

        // ── Seed from reservations ─────────────────────────────────
        $rSeed = $conn->query(
            "SELECT r.id, r.name, r.reservation_date, r.status, r.created_at
             FROM reservations r
             LEFT JOIN system_logs sl
                ON sl.category='reservation' AND sl.related_id=r.id
             WHERE sl.id IS NULL
             ORDER BY r.created_at DESC LIMIT 200"
        );
        if ($rSeed) {
            while ($r = $rSeed->fetch_assoc()) {
                $msg  = $conn->real_escape_string("Reservation by ".($r['name']??'Guest')." on {$r['reservation_date']}");
                $stat = $conn->real_escape_string($r['status'] ?? 'pending');
                $name = $conn->real_escape_string($r['name'] ?? 'Guest');
                $ts   = $conn->real_escape_string($r['created_at']);
                $conn->query("INSERT INTO system_logs (category,event_type,message,related_id,related_label,status,created_at)
                              VALUES ('reservation','Reservation Made','$msg',{$r['id']},'$name','$stat','$ts')");
            }
        }

        // ── Seed from login_history ────────────────────────────────
        $lhTbl = $conn->query("SHOW TABLES LIKE 'login_history'");
        if ($lhTbl && $lhTbl->num_rows > 0) {
            $lSeed = $conn->query(
                "SELECT lh.id, lh.user_name, lh.ip_address, lh.status, lh.created_at
                 FROM login_history lh
                 LEFT JOIN system_logs sl
                    ON sl.category='login' AND sl.related_id=lh.id
                 WHERE sl.id IS NULL
                 ORDER BY lh.created_at DESC LIMIT 200"
            );
            if ($lSeed) {
                while ($l = $lSeed->fetch_assoc()) {
                    $uname = $conn->real_escape_string($l['user_name'] ?? 'Unknown');
                    $ip    = $conn->real_escape_string($l['ip_address'] ?? '');
                    $stat  = $conn->real_escape_string($l['status'] ?? 'success');
                    $msg   = $conn->real_escape_string("Login attempt by {$l['user_name']} from IP {$l['ip_address']}");
                    $ts    = $conn->real_escape_string($l['created_at']);
                    $conn->query("INSERT INTO system_logs (category,event_type,message,related_id,related_label,status,ip_address,created_at)
                                  VALUES ('login','Login Attempt','$msg',{$l['id']},'$uname','$stat','$ip','$ts')");
                }
            }
        }

        // ── Seed from inventory (low stock) ───────────────────────
        $invTbl = $conn->query("SHOW TABLES LIKE 'inventory'");
        if ($invTbl && $invTbl->num_rows > 0) {
            $iSeed = $conn->query(
                "SELECT i.id, i.name, i.stock_quantity, i.min_stock, i.created_at
                 FROM inventory i
                 LEFT JOIN system_logs sl
                    ON sl.category='inventory' AND sl.related_id=i.id
                 WHERE sl.id IS NULL
                 ORDER BY i.created_at DESC LIMIT 100"
            );
            if ($iSeed) {
                while ($i = $iSeed->fetch_assoc()) {
                    $iname = $conn->real_escape_string($i['name'] ?? 'Item');
                    $ts    = $conn->real_escape_string($i['created_at']);
                    $stat  = ($i['stock_quantity'] <= $i['min_stock']) ? 'warning' : 'info';
                    $msg   = $conn->real_escape_string("Inventory: {$i['name']} — stock {$i['stock_quantity']} (min: {$i['min_stock']})");
                    $conn->query("INSERT INTO system_logs (category,event_type,message,related_id,related_label,status,created_at)
                                  VALUES ('inventory','Stock Update','$msg',{$i['id']},'$iname','$stat','$ts')");
                }
            }
        }

        // ── Seed from team/staff ───────────────────────────────────
        $staffTbl = $conn->query("SHOW TABLES LIKE 'team'");
        if ($staffTbl && $staffTbl->num_rows > 0) {
            $sSeed = $conn->query(
                "SELECT t.id, t.name, t.role, t.status, t.created_at
                 FROM team t
                 LEFT JOIN system_logs sl
                    ON sl.category='staff' AND sl.related_id=t.id
                 WHERE sl.id IS NULL
                 ORDER BY t.created_at DESC LIMIT 100"
            );
            if ($sSeed) {
                while ($s = $sSeed->fetch_assoc()) {
                    $sname = $conn->real_escape_string($s['name'] ?? 'Staff');
                    $srole = $conn->real_escape_string($s['role'] ?? '');
                    $ts    = $conn->real_escape_string($s['created_at']);
                    $msg   = $conn->real_escape_string("Staff record: {$s['name']} ({$s['role']}) added/updated");
                    $conn->query("INSERT INTO system_logs (category,event_type,message,related_id,related_label,status,created_at)
                                  VALUES ('staff','Staff Updated','$msg',{$s['id']},'$sname','info','$ts')");
                }
            }
        }

        // ── Fetch logs with filters ────────────────────────────────
        $category  = $_GET['category']  ?? 'all';
        $search    = trim($_GET['search']    ?? '');
        $date_from = trim($_GET['date_from'] ?? '');
        $date_to   = trim($_GET['date_to']   ?? '');
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $per_page  = 50;
        $offset    = ($page - 1) * $per_page;

        $where = ['1=1'];
        if ($category !== 'all') {
            $cat = $conn->real_escape_string($category);
            $where[] = "category = '$cat'";
        }
        if ($search !== '') {
            $s = $conn->real_escape_string($search);
            $where[] = "(message LIKE '%$s%' OR event_type LIKE '%$s%' OR related_label LIKE '%$s%' OR user_name LIKE '%$s%')";
        }
        if ($date_from !== '') {
            $df = $conn->real_escape_string($date_from);
            $where[] = "DATE(created_at) >= '$df'";
        }
        if ($date_to !== '') {
            $dt = $conn->real_escape_string($date_to);
            $where[] = "DATE(created_at) <= '$dt'";
        }

        $whereSQL = implode(' AND ', $where);

        // Count per category for badges
        $counts = ['all' => 0, 'order' => 0, 'payment' => 0, 'reservation' => 0, 'inventory' => 0, 'staff' => 0, 'login' => 0, 'error' => 0];
        $cntRes = $conn->query("SELECT category, COUNT(*) as c FROM system_logs GROUP BY category");
        if ($cntRes) {
            while ($cr = $cntRes->fetch_assoc()) {
                if (isset($counts[$cr['category']])) $counts[$cr['category']] = (int)$cr['c'];
                $counts['all'] += (int)$cr['c'];
            }
        }

        // Total filtered
        $totalRes = $conn->query("SELECT COUNT(*) as total FROM system_logs WHERE $whereSQL");
        $total    = $totalRes ? (int)$totalRes->fetch_assoc()['total'] : 0;

        // Data
        $logs = [];
        $dataRes = $conn->query(
            "SELECT * FROM system_logs WHERE $whereSQL ORDER BY created_at DESC LIMIT $per_page OFFSET $offset"
        );
        if ($dataRes) while ($row = $dataRes->fetch_assoc()) $logs[] = $row;

        echo json_encode([
            'status'   => 'success',
            'data'     => $logs,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'counts'   => $counts
        ]);
    }

    // --- SAVE CATEGORY (Add / Edit) ---
    elseif ($action === 'save_category') {
        $id          = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $name        = trim($_POST['name'] ?? '');
        $slug        = strtolower(trim($_POST['slug'] ?? ''));
        $icon        = trim($_POST['icon'] ?? 'fa-tag');
        $description = trim($_POST['description'] ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);
        $status      = $_POST['status'] ?? 'active';

        if (!$name || !$slug) {
            echo json_encode(['status' => 'error', 'message' => 'Name and Slug are required.']);
            exit;
        }

        // Check slug uniqueness (exclude self on edit)
        $slugCheck = $conn->prepare("SELECT id FROM menu_categories WHERE slug=? AND id != ?");
        $slugCheck->bind_param("si", $slug, $id);
        $slugCheck->execute();
        if ($slugCheck->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'এই Slug ইতোমধ্যে ব্যবহার হচ্ছে। অন্য একটি দিন।']);
            exit;
        }

        if ($id > 0) {
            // Edit — if slug changed, update menu_items too
            $oldSlugRes = $conn->prepare("SELECT slug FROM menu_categories WHERE id=?");
            $oldSlugRes->bind_param("i", $id);
            $oldSlugRes->execute();
            $oldRow = $oldSlugRes->get_result()->fetch_assoc();
            $old_slug = $oldRow['slug'] ?? $slug;

            $stmt = $conn->prepare("UPDATE menu_categories SET name=?, slug=?, icon=?, description=?, sort_order=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $slug, $icon, $description, $sort_order, $status, $id);
            if ($stmt->execute()) {
                // Update menu items category value if slug changed
                if ($old_slug !== $slug) {
                    $upd = $conn->prepare("UPDATE menu_items SET category=? WHERE LOWER(TRIM(category))=LOWER(?)");
                    $upd->bind_param("ss", $slug, $old_slug);
                    $upd->execute();
                }
                echo json_encode(['status' => 'success', 'message' => 'Category updated successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $conn->error]);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO menu_categories (name, slug, icon, description, sort_order, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssss", $name, $slug, $icon, $description, $sort_order, $status);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Category added successfully.', 'id' => $conn->insert_id]);
            } else {
                echo json_encode(['status' => 'error', 'message' => $conn->error]);
            }
        }
    }

    // --- TOGGLE CATEGORY STATUS ---
    elseif ($action === 'toggle_category_status') {
        $id     = (int)($input['id'] ?? 0);
        $status = ($input['status'] === 'active') ? 'active' : 'inactive';
        $stmt = $conn->prepare("UPDATE menu_categories SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- UPDATE CATEGORY SORT ORDER ---
    elseif ($action === 'update_category_sort') {
        $id    = (int)($input['id'] ?? 0);
        $order = (int)($input['sort_order'] ?? 0);
        $stmt = $conn->prepare("UPDATE menu_categories SET sort_order=? WHERE id=?");
        $stmt->bind_param("ii", $order, $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // --- DELETE CATEGORY ---
    elseif ($action === 'delete_category') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'Invalid ID']); exit; }

        // Get the slug before deleting
        $row = $conn->query("SELECT slug FROM menu_categories WHERE id=$id")->fetch_assoc();
        if (!$row) { echo json_encode(['status' => 'error', 'message' => 'Category not found']); exit; }

        // Set menu items to 'uncategorized'
        $uncategorized = 'uncategorized';
        $upd = $conn->prepare("UPDATE menu_items SET category=? WHERE LOWER(TRIM(category))=LOWER(?)");
        $upd->bind_param("ss", $uncategorized, $row['slug']);
        $upd->execute();

        $stmt = $conn->prepare("DELETE FROM menu_categories WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }

    // ── WRITE A SYSTEM LOG ENTRY ──────────────────────────────────
    elseif ($action === 'write_log') {
        $category  = $conn->real_escape_string($_POST['category']      ?? 'system');
        $event     = $conn->real_escape_string($_POST['event_type']     ?? 'Event');
        $message   = $conn->real_escape_string($_POST['message']        ?? '');
        $relId     = (int)($_POST['related_id'] ?? 0);
        $relLabel  = $conn->real_escape_string($_POST['related_label']  ?? '');
        $status    = $conn->real_escape_string($_POST['status']         ?? 'info');
        $userName  = $conn->real_escape_string($_SESSION['user_name']   ?? 'Admin');
        $ip        = $conn->real_escape_string($_SERVER['REMOTE_ADDR']  ?? '');
        $ridSQL    = $relId > 0 ? $relId : 'NULL';
        $conn->query("INSERT INTO system_logs (category,event_type,message,related_id,related_label,status,user_name,ip_address)
                      VALUES ('$category','$event','$message',$ridSQL,'$relLabel','$status','$userName','$ip')");
        echo json_encode(['status' => 'success']);
    }

    // ─── TESTIMONIALS ─────────────────────────────────────────────────────────

    // --- GET TESTIMONIALS ---
    elseif ($action === 'get_testimonials') {
        $result = $conn->query("SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC");
        $list = [];
        if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- SAVE TESTIMONIAL ---
    elseif ($action === 'save_testimonial') {
        $id     = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $name   = trim($_POST['name'] ?? '');
        $desig  = trim($_POST['designation'] ?? '');
        $content= trim($_POST['content'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $status = $_POST['status'] ?? 'active';
        $sort   = (int)($_POST['sort_order'] ?? 0);
        if (!$name || !$content) { echo json_encode(['status'=>'error','message'=>'Name and content are required']); exit; }
        if ($id) {
            $stmt = $conn->prepare("UPDATE testimonials SET name=?, designation=?, content=?, rating=?, status=?, sort_order=? WHERE id=?");
            $stmt->bind_param("sssisii", $name, $desig, $content, $rating, $status, $sort, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO testimonials (name, designation, content, rating, status, sort_order) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssisi", $name, $desig, $content, $rating, $status, $sort);
        }
        if ($stmt->execute()) echo json_encode(['status'=>'success','id'=>$conn->insert_id]);
        else echo json_encode(['status'=>'error','message'=>$conn->error]);
    }

    // --- DELETE TESTIMONIAL ---
    elseif ($action === 'delete_testimonial') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status'=>'success']);
        else echo json_encode(['status'=>'error','message'=>$conn->error]);
    }

    // --- TOGGLE TESTIMONIAL STATUS ---
    elseif ($action === 'toggle_testimonial_status') {
        $id     = (int)($input['id'] ?? 0);
        $cur    = $input['status'] ?? 'active';
        $status = ($cur === 'active') ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE testimonials SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) echo json_encode(['status'=>'success','new_status'=>$status]);
        else echo json_encode(['status'=>'error','message'=>$conn->error]);
    }

    // ─── FAQ ──────────────────────────────────────────────────────────────────

    // --- GET FAQS ---
    elseif ($action === 'get_faqs') {
        $result = $conn->query("SELECT * FROM faqs ORDER BY sort_order ASC, id ASC");
        $list = [];
        if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }

    // --- SAVE FAQ ---
    elseif ($action === 'save_faq') {
        $id       = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $question = trim($_POST['question'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $status   = $_POST['status'] ?? 'active';
        $sort     = (int)($_POST['sort_order'] ?? 0);
        if (!$question || !$answer) { echo json_encode(['status'=>'error','message'=>'Question and answer are required']); exit; }
        if ($id) {
            $stmt = $conn->prepare("UPDATE faqs SET question=?, answer=?, status=?, sort_order=? WHERE id=?");
            $stmt->bind_param("sssii", $question, $answer, $status, $sort, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO faqs (question, answer, status, sort_order) VALUES (?,?,?,?)");
            $stmt->bind_param("sssi", $question, $answer, $status, $sort);
        }
        if ($stmt->execute()) echo json_encode(['status'=>'success','id'=>$conn->insert_id]);
        else echo json_encode(['status'=>'error','message'=>$conn->error]);
    }

    // --- DELETE FAQ ---
    elseif ($action === 'delete_faq') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM faqs WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['status'=>'success']);
        else echo json_encode(['status'=>'error','message'=>$conn->error]);
    }

    // --- TOGGLE FAQ STATUS ---
    elseif ($action === 'toggle_faq_status') {
        $id  = (int)($input['id'] ?? 0);
        $cur = $input['status'] ?? 'active';
        $status = ($cur === 'active') ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE faqs SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) echo json_encode(['status'=>'success','new_status'=>$status]);
        else echo json_encode(['status'=>'error','message'=>$conn->error]);
    }

    // ─── FEATURED FOODS PREVIEW ───────────────────────────────────────────────

    // --- GET FEATURED MENU ITEMS ---
    elseif ($action === 'get_featured_items') {
        $result = $conn->query("SELECT id, name, price, discount_price, image_url, category, is_featured, status
            FROM menu_items WHERE is_featured=1 AND status='active' ORDER BY name ASC");
        $list = [];
        if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;
        echo json_encode(['status' => 'success', 'data' => $list]);
    }
}
?>
