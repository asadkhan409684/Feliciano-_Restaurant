<?php
session_start();
require_once '../config/database.php';
require_once '../config/categories.php';
require_once '../config/settings_helper.php';
require_once '../config/rbac.php';

$site_settings = get_restaurant_settings($conn);

// ── Access Control ─────────────────────────────────────────────────────────
// Allowed roles: admin + all staff roles (manager uses separate panel)
$allowed_panel_roles = ['admin','chef','waiter','cashier'];
if (!isset($_SESSION['user_logged_in']) || !in_array($_SESSION['user_role'] ?? '', $allowed_panel_roles)) {
    header('Location: ../auth/login.php');
    exit;
}

// Manager goes to manager panel
if ($_SESSION['user_role'] === 'manager') {
    header('Location: ../manager/index.php');
    exit;
}

// Initialize RBAC (loads DB permissions into cache)
rbac_init($conn);

// Build allowed sections list for this user
$allowed_sections = rbac_allowed_sections();
$user_role = $_SESSION['user_role'] ?? 'staff';
$is_admin  = ($user_role === 'admin');

// Fetch unread notifications count
$unread_count = 0;
$top_notifications = [];
$notif_query = $conn->query("SELECT * FROM admin_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5");
if ($notif_query) {
    while($row = $notif_query->fetch_assoc()) $top_notifications[] = $row;
}
$cnt_q = $conn->query("SELECT COUNT(*) as c FROM admin_notifications WHERE is_read = 0");
if ($cnt_q) $unread_count = $cnt_q->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — Feliciano Restaurant</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>

<!-- ===== HEADER ===== -->
<header class="admin-header">
    <div class="admin-container">
        <div class="admin-logo">
            <div class="logo-brand">
                    <?php $admin_logo = get_logo_url($site_settings, '../'); ?>
                    <img src="<?php echo $admin_logo; ?>" alt="<?php echo htmlspecialchars($site_settings['restaurant_name']); ?>" class="logo-favicon" onerror="this.src='../assets/images/favicon.png'">
                    <div class="logo-text">
                        <span class="logo-name"><?php echo htmlspecialchars($site_settings['restaurant_name']); ?></span>
                        <span class="logo-role"><i class="fas fa-shield-halved"></i> Super Admin</span>
                    </div>
                </div>
            <button class="sidebar-toggle-btn" id="sidebarToggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="admin-user">
            <!-- View Site -->
            <a href="../index.php" class="header-icon-btn" title="View Website" target="_blank">
                <i class="fas fa-globe"></i>
            </a>
            <!-- Notification Bell -->
            <div class="dropdown">
                <a href="#" class="header-icon-btn dropdown-toggle hide-arrow" id="notifDropdown"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge <?= $unread_count > 0 ? '' : 'd-none' ?>" id="notificationCount"><?= $unread_count ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end notif-dropdown" aria-labelledby="notifDropdown">
                    <li><h6 class="dropdown-header fw-bold py-3">Notifications</h6></li>
                    <div id="dropdownNotificationsList">
                        <?php if(empty($top_notifications)): ?>
                            <li><span class="dropdown-item text-center text-muted py-3">No unread notifications</span></li>
                        <?php else: foreach($top_notifications as $n): ?>
                            <li>
                                <a class="dropdown-item notif-item" href="#"
                                   onclick="handleDropdownNotifClick(event,<?=$n['id']?>,'<?=$n['type']?>','<?=$n['related_id']?>')">
                                    <div class="notif-title"><i class="fas <?= $n['type']=='order'?'fa-cart-shopping text-primary':($n['type']=='reservation'?'fa-calendar text-warning':'fa-circle-info text-info') ?>"></i> <?= htmlspecialchars($n['title']) ?></div>
                                    <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                                    <div class="notif-time"><?= date('M d, H:i', strtotime($n['created_at'])) ?></div>
                                </a>
                            </li>
                        <?php endforeach; endif; ?>
                    </div>
                    <li class="border-top"><a class="dropdown-item text-center text-primary fw-bold py-2" href="#" onclick="markAllNotificationsRead(event)"><i class="fas fa-check-double me-1"></i> Mark All Read</a></li>
                </ul>
            </div>
            <!-- Admin Info Dropdown -->
            <div class="dropdown">
                <a href="#" class="admin-info-btn dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                    <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
                    <div class="admin-info-text">
                        <span class="admin-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                        <span class="admin-role-text"><?php
                            $role_labels = ['admin'=>'Super Admin','chef'=>'Chef','waiter'=>'Waiter','cashier'=>'Cashier'];
                            echo $role_labels[$user_role] ?? ucfirst($user_role);
                        ?></span>
                    </div>
                    <i class="fas fa-chevron-down ms-1" style="font-size:.65rem;color:#94a3b8"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:10px;min-width:180px">
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="showSection('settings',null)"><i class="fas fa-cog me-2 text-muted"></i>Settings</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>

<!-- ===== SIDEBAR ===== -->
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Sidebar Brand (collapsed state) -->
    <div class="sidebar-brand-mini">
        <img src="../assets/images/favicon.png" alt="F" onerror="this.outerHTML='<span>F</span>'">
    </div>

    <nav class="admin-nav">
        <!-- MAIN -->
        <div class="nav-section-label">Main</div>
        <ul>
            <li><a href="javascript:void(0)" class="nav-link active" onclick="showSection('dashboard',this)">
                <i class="fas fa-gauge-high"></i><span>Dashboard</span>
            </a></li>
        </ul>

        <!-- RESTAURANT OPERATIONS -->
        <?php
        $ops_items = [
            ['category-management', 'fa-tags',         'Categories'],
            ['menu-management',     'fa-utensils',     'Menu Management'],
            ['combo-meals',         'fa-layer-group',  'Combo Meals'],
            ['orders',              'fa-shopping-cart','Orders'],
            ['reservations',        'fa-calendar-check','Reservations'],
            ['tables',              'fa-chair',        'Table Management'],
            ['kitchen',             'fa-fire-burner',  'Kitchen'],
            ['delivery',            'fa-motorcycle',   'Delivery'],
        ];
        $visible_ops = array_filter($ops_items, fn($i) => rbac_can($i[0]));
        if (!empty($visible_ops)):
        ?>
        <div class="nav-section-label">Operations</div>
        <ul>
            <?php foreach ($visible_ops as $item): ?>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showSection('<?= $item[0] ?>',this)">
                <i class="fas <?= $item[1] ?>"></i><span><?= $item[2] ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- FINANCE -->
        <?php
        $fin_items = [
            ['coupons',  'fa-ticket',              'Coupons & Offers'],
            ['billing',  'fa-file-invoice-dollar', 'Billing & Payment'],
            ['expenses', 'fa-wallet',              'Expenses'],
        ];
        $visible_fin = array_filter($fin_items, fn($i) => rbac_can($i[0]));
        if (!empty($visible_fin)):
        ?>
        <div class="nav-section-label">Finance</div>
        <ul>
            <?php foreach ($visible_fin as $item): ?>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showSection('<?= $item[0] ?>',this)">
                <i class="fas <?= $item[1] ?>"></i><span><?= $item[2] ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- INVENTORY -->
        <?php
        $inv_items = [
            ['inventory',  'fa-boxes-stacked', 'Inventory'],
            ['suppliers',  'fa-truck-field',   'Suppliers'],
            ['purchases',  'fa-cart-flatbed',  'Purchases'],
        ];
        $visible_inv = array_filter($inv_items, fn($i) => rbac_can($i[0]));
        if (!empty($visible_inv)):
        ?>
        <div class="nav-section-label">Inventory</div>
        <ul>
            <?php foreach ($visible_inv as $item): ?>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showSection('<?= $item[0] ?>',this)">
                <i class="fas <?= $item[1] ?>"></i><span><?= $item[2] ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- PEOPLE -->
        <?php
        $ppl_items = [
            ['customers', 'fa-users',    'Customers'],
            ['team',      'fa-id-badge', 'Staff Management'],
            ['branches',  'fa-store',    'Branches'],
        ];
        $visible_ppl = array_filter($ppl_items, fn($i) => rbac_can($i[0]));
        if (!empty($visible_ppl)):
        ?>
        <div class="nav-section-label">People</div>
        <ul>
            <?php foreach ($visible_ppl as $item): ?>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showSection('<?= $item[0] ?>',this)">
                <i class="fas <?= $item[1] ?>"></i><span><?= $item[2] ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- CONTENT -->
        <?php
        $cnt_items = [
            ['website-content', 'fa-globe',            'Website Content'],
            ['gallery',         'fa-images',           'Gallery'],
            ['events',          'fa-calendar-week',    'Events'],
            ['reviews',         'fa-star-half-stroke', 'Reviews'],
        ];
        $visible_cnt = array_filter($cnt_items, fn($i) => rbac_can($i[0]));
        if (!empty($visible_cnt)):
        ?>
        <div class="nav-section-label">Content</div>
        <ul>
            <?php foreach ($visible_cnt as $item): ?>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showSection('<?= $item[0] ?>',this)">
                <i class="fas <?= $item[1] ?>"></i><span><?= $item[2] ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- REPORTS & SYSTEM -->
        <?php
        $sys_items = [
            ['analytics',    'fa-chart-bar',          'Reports & Analytics'],
            ['notifications','fa-bell',               'Notifications'],
            ['activity-log', 'fa-clock-rotate-left',  'System Logs'],
            ['settings',     'fa-sliders',            'Settings'],
            ['rbac',         'fa-shield-halved',      'Role Permissions'],
        ];
        $visible_sys = array_filter($sys_items, fn($i) => rbac_can($i[0]));
        if (!empty($visible_sys)):
        ?>
        <div class="nav-section-label">Reports & System</div>
        <ul>
            <?php foreach ($visible_sys as $item): ?>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showSection('<?= $item[0] ?>',this)">
                <i class="fas <?= $item[1] ?>"></i><span><?= $item[2] ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

    </nav>
</aside>

<!-- ===== MAIN CONTENT ===== -->
<main class="admin-main" id="adminMain">

<?php include 'dashboard.php'; ?>
<?php include 'manage-categories.php'; ?>
<?php include 'manage-menu.php'; ?>
<?php include 'manage-orders.php'; ?>
<?php include 'manage-reservations.php'; ?>
<?php include 'manage-users.php'; ?>
<?php include 'manage-team.php'; ?>
<?php include 'manage-branches.php'; ?>
<?php if ($is_admin) include 'manage-rbac.php'; ?>

<!-- ===== TABLE MANAGEMENT ===== -->
<section id="tables" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-chair me-2"></i>Table Management</h2><p>Manage restaurant tables and their status</p></div>
        <button class="btn btn-primary" onclick="showTableModal()"><i class="fas fa-plus"></i> Add Table</button>
    </div>
    <div class="stats-grid mb-4" id="tableStatsGrid"></div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Table No.</th><th>Capacity</th><th>Location/Floor</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="tablesTableBody"><tr><td colspan="5" class="text-center py-4 text-muted">Loading tables...</td></tr></tbody>
        </table>
    </div>
</section>
<div id="tableModal" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="modal-header"><h2 id="tableModalTitle">Add Table</h2><button class="close-btn" onclick="closeTableModal()">&times;</button></div>
        <form id="tableForm" style="padding:20px">
            <input type="hidden" id="tableId" name="id">
            <div class="form-group"><label>Table Number</label><input type="text" id="tableNumber" name="table_number" required placeholder="e.g. T-01"></div>
            <div class="form-group"><label>Capacity (seats)</label><input type="number" id="tableCapacity" name="capacity" required min="1" placeholder="e.g. 4"></div>
            <div class="form-group"><label>Location / Floor</label><input type="text" id="tableLocation" name="location" placeholder="e.g. Ground Floor, Window Side"></div>
            <div class="form-group"><label>Status</label>
                <select id="tableStatus" name="status">
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="reserved">Reserved</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeTableModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Table</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== COUPONS & OFFERS ===== -->
<section id="coupons" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-tags me-2"></i>Coupons & Offers</h2><p>Create and manage discount coupons</p></div>
        <button class="btn btn-primary" onclick="showCouponModal()"><i class="fas fa-plus"></i> New Coupon</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Usage</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="couponsTableBody"><tr><td colspan="7" class="text-center py-4 text-muted">Loading coupons...</td></tr></tbody>
        </table>
    </div>
</section>
<div id="couponModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header"><h2 id="couponModalTitle">New Coupon</h2><button class="close-btn" onclick="closeCouponModal()">&times;</button></div>
        <form id="couponForm" style="padding:20px">
            <input type="hidden" id="couponId" name="id">
            <div class="form-group"><label>Coupon Code</label><input type="text" id="couponCode" name="code" required placeholder="e.g. SAVE20" style="text-transform:uppercase"></div>
            <div class="form-group"><label>Discount Type</label>
                <select id="couponType" name="discount_type">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount (TK)</option>
                    <option value="free_delivery">Free Delivery</option>
                    <option value="bogo">Buy One Get One (BOGO)</option>
                </select>
            </div>
            <div class="form-group"><label>Discount Value</label><input type="number" id="couponValue" name="discount_value" required min="0" placeholder="e.g. 10"></div>
            <div class="form-group"><label>Minimum Order (TK)</label><input type="number" id="couponMinOrder" name="min_order" min="0" placeholder="0 = no minimum" value="0"></div>
            <div class="form-group"><label>Usage Limit</label><input type="number" id="couponUsageLimit" name="usage_limit" min="0" placeholder="0 = unlimited" value="0"></div>
            <div class="form-group"><label>Expiry Date</label><input type="date" id="couponExpiry" name="expiry_date"></div>
            <div class="form-group"><label>Status</label>
                <select id="couponStatus" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeCouponModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Coupon</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== COMBO MEALS ===== -->
<section id="combo-meals" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-layer-group me-2"></i>Combo Meal Management</h2><p>Create and manage combo meal packages</p></div>
        <button class="btn btn-primary" onclick="showComboModal()"><i class="fas fa-plus"></i> New Combo</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Combo Name</th><th>Items Included</th><th>Original Price</th><th>Combo Price</th><th>Discount</th><th>Status</th><th style="text-align:center">Actions</th></tr></thead>
            <tbody id="combosTableBody"><tr><td colspan="7" class="text-center py-4 text-muted">No combos yet. Create your first combo meal.</td></tr></tbody>
        </table>
    </div>
</section>
<div id="comboModal" class="modal">
    <div class="modal-content" style="max-width:560px">
        <div class="modal-header"><h2 id="comboModalTitle">New Combo Meal</h2><button class="close-btn" onclick="closeComboModal()">&times;</button></div>
        <form id="comboForm" style="padding:20px">
            <input type="hidden" id="comboId" name="id">
            <div class="form-group"><label>Combo Name <span class="text-danger">*</span></label><input type="text" id="comboName" name="name" required></div>
            <div class="form-group"><label>Description</label><textarea id="comboDesc" name="description" rows="2"></textarea></div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Original Price (TK)</label><input type="number" id="comboOriginalPrice" name="original_price" min="0" step="0.01"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Combo Price (TK) <span class="text-danger">*</span></label><input type="number" id="comboPrice" name="price" required min="0" step="0.01"></div></div>
            </div>
            <div class="form-group"><label>Include Items</label><textarea id="comboItems" name="items" rows="2" placeholder="Chicken BBQ Pizza, Beef Steak, Drinks..."></textarea></div>
            <div class="form-group"><label>Status</label><select id="comboStatus" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeComboModal()">Cancel</button><button type="submit" class="btn btn-primary">Save Combo</button></div>
        </form>
    </div>
</div>

<!-- ===== KITCHEN MANAGEMENT ===== -->
<section id="kitchen" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-fire-burner me-2"></i>Kitchen Management</h2><p>Monitor food preparation in real time</p></div>
        <button class="btn btn-warning" onclick="loadKitchenOrders()"><i class="fas fa-sync-alt me-1"></i>Refresh</button>
    </div>
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="stat-card" style="border-left:4px solid #e67e22"><div class="stat-icon" style="background:#e67e22"><i class="fas fa-hourglass-start"></i></div><div class="stat-info"><h3 id="kitchenPending">0</h3><p>Pending</p></div></div>
        <div class="stat-card" style="border-left:4px solid #3498db"><div class="stat-icon" style="background:#3498db"><i class="fas fa-fire"></i></div><div class="stat-info"><h3 id="kitchenPreparing">0</h3><p>Preparing</p></div></div>
        <div class="stat-card" style="border-left:4px solid #27ae60"><div class="stat-icon" style="background:#27ae60"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3 id="kitchenReady">0</h3><p>Ready</p></div></div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Order ID</th><th>Items</th><th>Order Type</th><th>Ordered At</th><th>Status</th><th style="text-align:center">Update</th></tr></thead>
            <tbody id="kitchenTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr></tbody>
        </table>
    </div>
</section>

<!-- ===== DELIVERY MANAGEMENT ===== -->
<section id="delivery" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-motorcycle me-2"></i>Delivery Management</h2><p>Track riders and manage delivery orders</p></div>
    </div>
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="stat-card" style="border-left:4px solid #e74c3c"><div class="stat-icon" style="background:#e74c3c"><i class="fas fa-clock"></i></div><div class="stat-info"><h3 id="deliveryPending">0</h3><p>Awaiting Rider</p></div></div>
        <div class="stat-card" style="border-left:4px solid #f39c12"><div class="stat-icon" style="background:#f39c12"><i class="fas fa-motorcycle"></i></div><div class="stat-info"><h3 id="deliveryActive">0</h3><p>Out for Delivery</p></div></div>
        <div class="stat-card" style="border-left:4px solid #27ae60"><div class="stat-icon" style="background:#27ae60"><i class="fas fa-house-circle-check"></i></div><div class="stat-info"><h3 id="deliveryDone">0</h3><p>Delivered Today</p></div></div>
    </div>
    <div class="analytics-card mb-4">
        <h5 class="mb-3"><i class="fas fa-sliders me-2 text-primary"></i>Delivery Settings</h5>
        <form id="deliverySettingsForm" class="row g-3">
            <div class="col-md-4"><label class="form-label fw-semibold">Delivery Charge (TK)</label><input type="number" class="form-control" name="delivery_charge" placeholder="e.g. 50"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Free Delivery Above (TK)</label><input type="number" class="form-control" name="free_delivery_above" placeholder="e.g. 500"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Est. Delivery Time (min)</label><input type="number" class="form-control" name="delivery_time_est" placeholder="e.g. 45"></div>
            <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save Settings</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Order ID</th><th>Customer</th><th>Delivery Address</th><th>Total</th><th>Rider</th><th>Status</th><th style="text-align:center">Actions</th></tr></thead>
            <tbody id="deliveryTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr></tbody>
        </table>
    </div>
</section>

<!-- Rider Assignment Modal -->
<div id="riderModal" class="modal">
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header"><h2><i class="fas fa-motorcycle me-2"></i>Assign Rider</h2><button class="close-btn" onclick="document.getElementById('riderModal').classList.remove('active')">&times;</button></div>
        <div style="padding:20px">
            <input type="hidden" id="riderOrderId">
            <div class="form-group"><label>Rider Name</label><input type="text" id="riderNameInput" class="form-control" placeholder="Enter rider name"></div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="document.getElementById('riderModal').classList.remove('active')">Cancel</button>
                <button class="btn btn-primary" onclick="saveRiderAssignment()"><i class="fas fa-save me-1"></i>Assign</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== BILLING & PAYMENT ===== -->
<section id="billing" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-file-invoice-dollar me-2"></i>Billing & Payment</h2><p>Invoices, payment methods and refunds</p></div>
    </div>
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="stat-card" style="border-left:4px solid #27ae60"><div class="stat-icon" style="background:#27ae60"><i class="fas fa-circle-check"></i></div><div class="stat-info"><h3 id="billPaid">TK 0</h3><p>Paid Revenue</p></div></div>
        <div class="stat-card" style="border-left:4px solid #e74c3c"><div class="stat-icon" style="background:#e74c3c"><i class="fas fa-hourglass-half"></i></div><div class="stat-info"><h3 id="billPendingCount">0</h3><p>Pending Payments</p></div></div>
        <div class="stat-card" style="border-left:4px solid #3498db"><div class="stat-icon" style="background:#3498db"><i class="fas fa-money-bill-wave"></i></div><div class="stat-info"><h3 id="billTax">TK 0</h3><p>Tax Collected</p></div></div>
    </div>
    <div class="analytics-card mb-4">
        <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-success"></i>Payment Method Breakdown</h5>
        <div id="paymentMethodsChart" class="d-flex flex-wrap gap-3"><p class="text-muted">Loading...</p></div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Invoice #</th><th>Customer</th><th>Amount (TK)</th><th>Method</th><th>Date</th><th>Payment Status</th><th style="text-align:center">Action</th></tr></thead>
            <tbody id="billingTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr></tbody>
        </table>
    </div>
</section>

<!-- Invoice Print Modal -->
<div id="invoiceModal" class="modal">
    <div class="modal-content" style="max-width:600px">
        <div class="modal-header">
            <h2><i class="fas fa-file-invoice me-2"></i>Invoice</h2>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-primary" onclick="printInvoice()"><i class="fas fa-print me-1"></i>Print</button>
                <button class="close-btn" onclick="document.getElementById('invoiceModal').classList.remove('active')">&times;</button>
            </div>
        </div>
        <div id="invoicePrintArea" style="padding:25px">
            <!-- Dynamic invoice content -->
        </div>
    </div>
</div>

<!-- ===== INVENTORY MANAGEMENT ===== -->
<section id="inventory" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-boxes-stacked me-2"></i>Inventory Management</h2><p>Stock monitoring and low stock alerts</p></div>
        <button class="btn btn-primary" onclick="showInventoryModal()"><i class="fas fa-plus"></i> Add Item</button>
    </div>
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="stat-card" style="border-left:4px solid #27ae60"><div class="stat-icon" style="background:#27ae60"><i class="fas fa-boxes-stacked"></i></div><div class="stat-info"><h3 id="invTotal">0</h3><p>Total Items</p></div></div>
        <div class="stat-card" style="border-left:4px solid #e74c3c"><div class="stat-icon" style="background:#e74c3c"><i class="fas fa-triangle-exclamation"></i></div><div class="stat-info"><h3 id="invLowStock">0</h3><p>Low Stock Alert</p></div></div>
        <div class="stat-card" style="border-left:4px solid #f39c12"><div class="stat-icon" style="background:#f39c12"><i class="fas fa-calendar-xmark"></i></div><div class="stat-info"><h3 id="invExpiring">0</h3><p>Expiring Soon</p></div></div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Item Name</th><th>Category</th><th>Stock</th><th>Unit</th><th>Min Level</th><th>Expiry</th><th>Status</th><th style="text-align:center">Actions</th></tr></thead>
            <tbody id="inventoryTableBody"><tr><td colspan="8" class="text-center py-4 text-muted">No inventory items. Add your first item.</td></tr></tbody>
        </table>
    </div>
</section>
<div id="inventoryModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header"><h2 id="inventoryModalTitle">Add Inventory Item</h2><button class="close-btn" onclick="closeInventoryModal()">&times;</button></div>
        <form id="inventoryForm" style="padding:20px">
            <input type="hidden" id="inventoryId" name="id">
            <div class="form-group"><label>Item Name <span class="text-danger">*</span></label><input type="text" id="invName" name="name" required></div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Category</label><select id="invCategory" name="category"><option value="ingredient">Ingredient</option><option value="beverage">Beverage</option><option value="packaging">Packaging</option><option value="cleaning">Cleaning</option><option value="other">Other</option></select></div></div>
                <div class="col-md-6"><div class="form-group"><label>Unit</label><select id="invUnit" name="unit"><option value="kg">kg</option><option value="g">g</option><option value="liter">Liter</option><option value="ml">ml</option><option value="piece">Piece</option><option value="box">Box</option><option value="packet">Packet</option></select></div></div>
            </div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Current Stock</label><input type="number" id="invStock" name="stock_quantity" min="0" step="0.01" value="0"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Minimum Stock Level</label><input type="number" id="invMinStock" name="min_stock" min="0" step="0.01" value="0"></div></div>
            </div>
            <div class="form-group"><label>Expiry Date</label><input type="date" id="invExpiry" name="expiry_date"></div>
            <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeInventoryModal()">Cancel</button><button type="submit" class="btn btn-primary">Save Item</button></div>
        </form>
    </div>
</div>

<!-- ===== SUPPLIERS ===== -->
<section id="suppliers" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-truck-field me-2"></i>Supplier Management</h2><p>Manage suppliers and purchase history</p></div>
        <button class="btn btn-primary" onclick="showSupplierModal()"><i class="fas fa-plus"></i> Add Supplier</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Supplier Name</th><th>Contact Person</th><th>Phone</th><th>Email</th><th>Products</th><th>Payment Due</th><th style="text-align:center">Actions</th></tr></thead>
            <tbody id="suppliersTableBody"><tr><td colspan="7" class="text-center py-4 text-muted">No suppliers found.</td></tr></tbody>
        </table>
    </div>
</section>
<div id="supplierModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header"><h2 id="supplierModalTitle">Add Supplier</h2><button class="close-btn" onclick="closeSupplierModal()">&times;</button></div>
        <form id="supplierForm" style="padding:20px">
            <input type="hidden" id="supplierId" name="id">
            <div class="form-group"><label>Supplier Name <span class="text-danger">*</span></label><input type="text" id="supplierName" name="name" required></div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Contact Person</label><input type="text" id="supplierContact" name="contact_person"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Phone</label><input type="text" id="supplierPhone" name="phone"></div></div>
            </div>
            <div class="form-group"><label>Email</label><input type="email" id="supplierEmail" name="email"></div>
            <div class="form-group"><label>Products Supplied</label><textarea id="supplierProducts" name="products" rows="2" placeholder="Rice, Oil, Spices..."></textarea></div>
            <div class="form-group"><label>Address</label><textarea id="supplierAddress" name="address" rows="2"></textarea></div>
            <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeSupplierModal()">Cancel</button><button type="submit" class="btn btn-primary">Save Supplier</button></div>
        </form>
    </div>
</div>

<!-- ===== PURCHASES ===== -->
<section id="purchases" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-cart-flatbed me-2"></i>Purchase Management</h2><p>Track purchase orders and supplier invoices</p></div>
        <button class="btn btn-primary" onclick="showPurchaseModal()"><i class="fas fa-plus"></i> New Purchase</button>
    </div>
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="stat-card" style="border-left:4px solid #3498db"><div class="stat-icon" style="background:#3498db"><i class="fas fa-file-invoice"></i></div><div class="stat-info"><h3 id="purchaseTotal">0</h3><p>Total Orders</p></div></div>
        <div class="stat-card" style="border-left:4px solid #e74c3c"><div class="stat-icon" style="background:#e74c3c"><i class="fas fa-money-bill"></i></div><div class="stat-info"><h3 id="purchaseDue">TK 0</h3><p>Payment Due</p></div></div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>PO #</th><th>Supplier</th><th>Items</th><th>Total (TK)</th><th>Date</th><th>Payment</th><th>Status</th><th style="text-align:center">Actions</th></tr></thead>
            <tbody id="purchasesTableBody"><tr><td colspan="8" class="text-center py-4 text-muted">No purchase orders found.</td></tr></tbody>
        </table>
    </div>
</section>
<div id="purchaseModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header"><h2>New Purchase Order</h2><button class="close-btn" onclick="closePurchaseModal()">&times;</button></div>
        <form id="purchaseForm" style="padding:20px">
            <div class="form-group"><label>Supplier <span class="text-danger">*</span></label><select id="purchaseSupplier" name="supplier_id"><option value="">Select Supplier</option></select></div>
            <div class="form-group"><label>Items / Description</label><textarea id="purchaseItems" name="items" rows="3" placeholder="Item list..."></textarea></div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Total Amount (TK)</label><input type="number" id="purchaseAmount" name="total_amount" min="0" step="0.01"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Date</label><input type="date" id="purchaseDate" name="purchase_date"></div></div>
            </div>
            <div class="form-group"><label>Payment Status</label><select name="payment_status"><option value="pending">Pending</option><option value="paid">Paid</option><option value="partial">Partial</option></select></div>
            <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closePurchaseModal()">Cancel</button><button type="submit" class="btn btn-primary">Save Order</button></div>
        </form>
    </div>
</div>

<!-- ===== WEBSITE CONTENT ===== -->
<section id="website-content" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-globe me-2"></i>Website Content</h2><p>Manage homepage banners, featured items and site content</p></div>
    </div>
    <div class="settings-grid">
        <div class="settings-card">
            <h3><i class="fas fa-image me-2 text-primary"></i>Hero Banner / Slider</h3>
            <p class="text-muted mb-3">Upload or update the homepage hero banner image and text.</p>
            <form id="heroBannerForm">
                <div class="form-group"><label>Banner Heading</label><input type="text" name="banner_heading" id="bannerHeading" placeholder="e.g. Taste the Finest Cuisine"></div>
                <div class="form-group"><label>Banner Subtext</label><textarea name="banner_subtext" id="bannerSubtext" rows="2" placeholder="A short tagline..."></textarea></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
            </form>
        </div>
        <div class="settings-card" style="grid-column:1/-1">
            <!-- Tab switcher -->
            <div class="d-flex gap-2 mb-3 border-bottom pb-2 flex-wrap align-items-center">
                <h3 class="mb-0 me-3"><i class="fas fa-layer-group me-2 text-warning"></i>Testimonials & FAQ</h3>
                <button class="btn btn-sm btn-primary" id="tabTestimonialsBtn" onclick="switchCmsTab('testimonials')">
                    <i class="fas fa-comment-dots me-1"></i>Testimonials
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="tabFaqBtn" onclick="switchCmsTab('faq')">
                    <i class="fas fa-question-circle me-1"></i>FAQ
                </button>
                <button class="btn btn-sm btn-outline-success ms-auto" id="cmsAddBtn" onclick="cmsAddItem()">
                    <i class="fas fa-plus me-1"></i><span id="cmsAddBtnLabel">Add Testimonial</span>
                </button>
            </div>

            <!-- Testimonials Panel -->
            <div id="cmsTestimonialsPanel">
                <p class="text-muted mb-3" style="font-size:.84rem"><i class="fas fa-info-circle me-1"></i>Testimonials appear on the homepage. Toggle active/inactive to show or hide on the site.</p>
                <div id="testimonialsGrid" class="row g-3">
                    <div class="text-center py-4 text-muted w-100"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
                </div>
            </div>

            <!-- FAQ Panel (hidden by default) -->
            <div id="cmsFaqPanel" style="display:none">
                <p class="text-muted mb-3" style="font-size:.84rem"><i class="fas fa-info-circle me-1"></i>FAQs appear on the homepage and contact page.</p>
                <div id="faqList" class="d-flex flex-column gap-2">
                    <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
                </div>
            </div>
        </div>

        <!-- Featured Foods -->
        <div class="settings-card" style="grid-column:1/-1">
            <h3 class="mb-1"><i class="fas fa-star me-2 text-warning"></i>Featured Foods on Homepage</h3>
            <p class="text-muted mb-3" style="font-size:.84rem">Select which menu items appear in the "Featured" section on the homepage. Mark items as Featured from <strong>Menu Management</strong> → toggle the ⭐ Featured flag.</p>
            <div id="featuredFoodsPreview" class="row g-2">
                <div class="text-center py-3 text-muted w-100"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
            </div>
            <div class="mt-3">
                <a href="javascript:void(0)" class="btn btn-outline-primary btn-sm" onclick="showSection('menu-management',null)">
                    <i class="fas fa-utensils me-1"></i>Go to Menu Management
                </a>
            </div>
        </div>
        <div class="settings-card">
            <h3><i class="fas fa-info-circle me-2 text-success"></i>About Us</h3>
            <form id="aboutUsForm">
                <div class="form-group"><label>About Us Content</label><textarea name="about_us" id="aboutUsContent" rows="5" placeholder="Tell your restaurant story..."></textarea></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
            </form>
        </div>
        <div class="settings-card">
            <h3><i class="fas fa-shield-alt me-2 text-danger"></i>Legal Pages</h3>
            <form id="legalForm">
                <div class="form-group"><label>Terms & Conditions</label><textarea name="terms_conditions" rows="3" placeholder="Terms & conditions text..."></textarea></div>
                <div class="form-group"><label>Privacy Policy</label><textarea name="privacy_policy" rows="3" placeholder="Privacy policy text..."></textarea></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
            </form>
        </div>
    </div>
</section>

<!-- Testimonial Modal -->
<div id="testimonialModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header"><h2 id="testimonialModalTitle"><i class="fas fa-comment-dots me-2"></i>Add Testimonial</h2><button class="close-btn" onclick="closeTestimonialModal()">&times;</button></div>
        <form id="testimonialForm" style="padding:20px">
            <input type="hidden" id="testimonialId">
            <div class="form-group"><label>Customer Name <span class="text-danger">*</span></label><input type="text" id="testimonialName" required placeholder="e.g. John Smith"></div>
            <div class="form-group"><label>Rating (1-5)</label>
                <select id="testimonialRating">
                    <option value="5">★★★★★ (5)</option>
                    <option value="4">★★★★☆ (4)</option>
                    <option value="3">★★★☆☆ (3)</option>
                    <option value="2">★★☆☆☆ (2)</option>
                    <option value="1">★☆☆☆☆ (1)</option>
                </select>
            </div>
            <div class="form-group"><label>Testimonial Text <span class="text-danger">*</span></label><textarea id="testimonialContent" rows="4" required placeholder="What the customer said..."></textarea></div>
            <div class="form-group"><label>Designation / Description</label><input type="text" id="testimonialDesig" placeholder="e.g. Regular Customer"></div>
            <div class="form-group"><label>Status</label>
                <select id="testimonialStatus">
                    <option value="active">Active (show on site)</option>
                    <option value="inactive">Inactive (hidden)</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeTestimonialModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<!-- FAQ Modal -->
<div id="faqModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header"><h2 id="faqModalTitle"><i class="fas fa-question-circle me-2"></i>Add FAQ</h2><button class="close-btn" onclick="closeFaqModal()">&times;</button></div>
        <form id="faqForm" style="padding:20px">
            <input type="hidden" id="faqId">
            <div class="form-group"><label>Question <span class="text-danger">*</span></label><input type="text" id="faqQuestion" required placeholder="e.g. What are your opening hours?"></div>
            <div class="form-group"><label>Answer <span class="text-danger">*</span></label><textarea id="faqAnswer" rows="4" required placeholder="Detailed answer..."></textarea></div>
            <div class="form-group"><label>Sort Order</label><input type="number" id="faqSortOrder" value="0" min="0"></div>
            <div class="form-group"><label>Status</label>
                <select id="faqStatus">
                    <option value="active">Active (show on site)</option>
                    <option value="inactive">Inactive (hidden)</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeFaqModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== GALLERY ===== -->
<section id="gallery" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-images me-2"></i>Gallery Management</h2><p>Manage food and restaurant photos</p></div>
        <label class="btn btn-primary" style="cursor:pointer"><i class="fas fa-upload me-1"></i>Upload Photos<input type="file" id="galleryUpload" name="gallery_images[]" multiple accept="image/*" style="display:none" onchange="uploadGalleryImages(this)"></label>
    </div>
    <div class="row mb-3">
        <div class="col-auto"><button class="btn btn-outline-secondary btn-sm active" onclick="filterGallery('all',this)">All</button></div>
        <div class="col-auto"><button class="btn btn-outline-secondary btn-sm" onclick="filterGallery('food',this)">Food Photos</button></div>
        <div class="col-auto"><button class="btn btn-outline-secondary btn-sm" onclick="filterGallery('restaurant',this)">Restaurant Photos</button></div>
    </div>
    <div id="galleryGrid" class="gallery-grid">
        <div class="text-center py-5 text-muted w-100"><i class="fas fa-images fa-3x mb-3 d-block opacity-25"></i>No gallery images found. Upload some photos!</div>
    </div>
</section>

<!-- ===== EVENTS ===== -->
<section id="events" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-calendar-week me-2"></i>Event Management</h2><p>Birthday, corporate, private and festival events</p></div>
        <button class="btn btn-primary" onclick="showEventModal()"><i class="fas fa-plus"></i> Add Event</button>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Event Name</th><th>Type</th><th>Date</th><th>Capacity</th><th>Price (TK)</th><th>Status</th><th style="text-align:center">Actions</th></tr></thead>
            <tbody id="eventsTableBody"><tr><td colspan="7" class="text-center py-4 text-muted">No events found.</td></tr></tbody>
        </table>
    </div>
</section>
<div id="eventModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header"><h2 id="eventModalTitle">Add Event</h2><button class="close-btn" onclick="closeEventModal()">&times;</button></div>
        <form id="eventForm" style="padding:20px">
            <input type="hidden" id="eventId" name="id">
            <div class="form-group"><label>Event Name <span class="text-danger">*</span></label><input type="text" id="eventName" name="name" required></div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Event Type</label><select id="eventType" name="type"><option value="birthday">Birthday Party</option><option value="corporate">Corporate Event</option><option value="private">Private Event</option><option value="live_music">Live Music</option><option value="festival">Festival Offer</option><option value="other">Other</option></select></div></div>
                <div class="col-md-6"><div class="form-group"><label>Event Date</label><input type="date" id="eventDate" name="event_date"></div></div>
            </div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Capacity (guests)</label><input type="number" id="eventCapacity" name="capacity" min="1"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Price (TK)</label><input type="number" id="eventPrice" name="price" min="0" step="0.01"></div></div>
            </div>
            <div class="form-group"><label>Description</label><textarea id="eventDesc" name="description" rows="3"></textarea></div>
            <div class="form-group"><label>Status</label><select id="eventStatus" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancel</button><button type="submit" class="btn btn-primary">Save Event</button></div>
        </form>
    </div>
</div>

<!-- ===== REVIEW MANAGEMENT ===== -->
<section id="reviews" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-star me-2"></i>Review Management</h2><p>Approve, reject and reply to customer reviews</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <input type="text" id="reviewSearchInput" class="form-control form-control-sm"
                   placeholder="Search by name or comment..." style="width:220px"
                   oninput="filterReviews()">
            <select id="reviewRatingFilter" class="form-select form-select-sm" onchange="filterReviews()" style="width:130px">
                <option value="all">All Ratings</option>
                <option value="5">★★★★★ (5)</option>
                <option value="4">★★★★☆ (4)</option>
                <option value="3">★★★☆☆ (3)</option>
                <option value="2">★★☆☆☆ (2)</option>
                <option value="1">★☆☆☆☆ (1)</option>
            </select>
            <select id="reviewStatusFilter" class="form-select form-select-sm" onchange="filterReviews()" style="width:140px">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>

    <!-- Stats + Rating Distribution side by side -->
    <div class="row mb-4 g-3">
        <div class="col-lg-7">
            <div class="stats-grid" id="reviewStatsGrid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))"></div>
        </div>
        <div class="col-lg-5">
            <div class="analytics-card h-100">
                <h5 class="mb-3"><i class="fas fa-chart-bar me-2 text-warning"></i>Rating Distribution</h5>
                <div id="ratingDistributionChart"></div>
            </div>
        </div>
    </div>

    <!-- Reviews Grid -->
    <div id="reviewsContainer">
        <div class="text-center py-5" style="color:#475569;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Loading reviews...</p>
        </div>
    </div>
</section>

<!-- Reply Modal -->
<div id="reviewReplyModal" class="modal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header">
            <h2><i class="fas fa-reply me-2"></i>Reply to Review</h2>
            <button class="close-btn" onclick="closeReplyModal()">&times;</button>
        </div>
        <div style="padding:20px">
            <input type="hidden" id="replyReviewId">
            <!-- Original review preview -->
            <div id="replyOriginalReview" class="p-3 rounded mb-3"
                 style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;"></div>
            <div class="form-group">
                <label style="color:#0f172a;font-weight:700;">Your Reply</label>
                <textarea id="replyText" rows="4"
                          placeholder="Write a professional reply to this review..."
                          style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.92rem;color:#0f172a;resize:vertical;"></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeReplyModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitReply()">
                    <i class="fas fa-paper-plane me-1"></i>Send Reply
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== EXPENSE MANAGEMENT ===== -->
<section id="expenses" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-wallet me-2"></i>Expense Management</h2><p>Track and manage restaurant expenses</p></div>
        <button class="btn btn-primary" onclick="showExpenseModal()"><i class="fas fa-plus"></i> Add Expense</button>
    </div>
    <div class="stats-grid mb-4" id="expenseStatsGrid"></div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount (TK)</th><th>Actions</th></tr></thead>
            <tbody id="expensesTableBody"><tr><td colspan="5" class="text-center py-4 text-muted">Loading expenses...</td></tr></tbody>
        </table>
    </div>
</section>
<div id="expenseModal" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="modal-header"><h2 id="expenseModalTitle">Add Expense</h2><button class="close-btn" onclick="closeExpenseModal()">&times;</button></div>
        <form id="expenseForm" style="padding:20px">
            <input type="hidden" id="expenseId" name="id">
            <div class="form-group"><label>Date</label><input type="date" id="expenseDate" name="expense_date" required></div>
            <div class="form-group"><label>Category</label>
                <select id="expenseCategory" name="category">
                    <option value="salary">Salary</option>
                    <option value="electricity">Electricity</option>
                    <option value="gas">Gas</option>
                    <option value="water">Water</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="marketing">Marketing</option>
                    <option value="rent">Rent</option>
                    <option value="supplies">Supplies</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group"><label>Description</label><textarea id="expenseDesc" name="description" rows="3" placeholder="Brief description..."></textarea></div>
            <div class="form-group"><label>Amount (TK)</label><input type="number" id="expenseAmount" name="amount" required min="0" step="0.01"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== ANALYTICS & REPORTS ===== -->
<section id="analytics" class="admin-section">

    <!-- Section Header -->
    <div class="rpt-header">
        <div class="rpt-header-left">
            <div class="rpt-title-icon"><i class="fas fa-chart-bar"></i></div>
            <div>
                <h2 class="rpt-title">Reports & Analytics</h2>
                <p class="rpt-subtitle">Business intelligence overview — track performance, trends & insights</p>
            </div>
        </div>
        <div class="rpt-header-right">
            <div class="rpt-date-group">
                <div class="rpt-date-field">
                    <label><i class="fas fa-calendar-alt me-1"></i>From</label>
                    <input type="date" id="startDate" class="rpt-date-input">
                </div>
                <div class="rpt-date-sep"><i class="fas fa-arrow-right"></i></div>
                <div class="rpt-date-field">
                    <label><i class="fas fa-calendar-alt me-1"></i>To</label>
                    <input type="date" id="endDate" class="rpt-date-input">
                </div>
                <button class="rpt-update-btn" onclick="generateReport()">
                    <i class="fas fa-sync-alt"></i> Generate
                </button>
            </div>
        </div>
    </div>

    <!-- ── Export Toolbar ── -->
    <div class="rpt-export-toolbar">
        <div class="rpt-export-label">
            <i class="fas fa-download me-2 text-primary"></i>
            <strong>Export Reports</strong>
            <span class="text-muted ms-2" style="font-size:.8rem">uses selected date range above</span>
        </div>
        <div class="rpt-export-groups">

            <!-- Sales Report -->
            <div class="rpt-export-group">
                <div class="rpt-export-group-label">📊 Sales</div>
                <div class="d-flex gap-1">
                    <button class="rpt-export-btn rpt-btn-csv" onclick="exportReport('sales','csv')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="rpt-export-btn rpt-btn-print" onclick="exportReport('sales','print')">
                        <i class="fas fa-print"></i> Print/PDF
                    </button>
                </div>
            </div>

            <!-- Orders Report -->
            <div class="rpt-export-group">
                <div class="rpt-export-group-label">🛒 Orders</div>
                <div class="d-flex gap-1">
                    <button class="rpt-export-btn rpt-btn-csv" onclick="exportReport('orders','csv')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="rpt-export-btn rpt-btn-print" onclick="exportReport('orders','print')">
                        <i class="fas fa-print"></i> Print/PDF
                    </button>
                </div>
            </div>

            <!-- Customers Report -->
            <div class="rpt-export-group">
                <div class="rpt-export-group-label">👥 Customers</div>
                <div class="d-flex gap-1">
                    <button class="rpt-export-btn rpt-btn-csv" onclick="exportReport('customers','csv')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="rpt-export-btn rpt-btn-print" onclick="exportReport('customers','print')">
                        <i class="fas fa-print"></i> Print/PDF
                    </button>
                </div>
            </div>

            <!-- Reservations Report -->
            <div class="rpt-export-group">
                <div class="rpt-export-group-label">📅 Reservations</div>
                <div class="d-flex gap-1">
                    <button class="rpt-export-btn rpt-btn-csv" onclick="exportReport('reservations','csv')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="rpt-export-btn rpt-btn-print" onclick="exportReport('reservations','print')">
                        <i class="fas fa-print"></i> Print/PDF
                    </button>
                </div>
            </div>

            <!-- Inventory Report -->
            <div class="rpt-export-group">
                <div class="rpt-export-group-label">📦 Inventory</div>
                <div class="d-flex gap-1">
                    <button class="rpt-export-btn rpt-btn-csv" onclick="exportReport('inventory','csv')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="rpt-export-btn rpt-btn-print" onclick="exportReport('inventory','print')">
                        <i class="fas fa-print"></i> Print/PDF
                    </button>
                </div>
            </div>

            <!-- Salary Report -->
            <div class="rpt-export-group">
                <div class="rpt-export-group-label">💰 Salary</div>
                <div class="d-flex gap-1">
                    <button class="rpt-export-btn rpt-btn-csv" onclick="exportReport('salary','csv')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="rpt-export-btn rpt-btn-print" onclick="exportReport('salary','print')">
                        <i class="fas fa-print"></i> Print/PDF
                    </button>
                </div>
            </div>

            <!-- Tax / VAT Report -->
            <div class="rpt-export-group">
                <div class="rpt-export-group-label">🧾 Tax/VAT</div>
                <div class="d-flex gap-1">
                    <button class="rpt-export-btn rpt-btn-csv" onclick="exportReport('tax','csv')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                    <button class="rpt-export-btn rpt-btn-print" onclick="exportReport('tax','print')">
                        <i class="fas fa-print"></i> Print/PDF
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div id="analyticsSummary" class="rpt-kpi-grid mb-4"></div>

    <!-- Row 1: Smart Advisor + Peak Stats -->
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="rpt-card rpt-advisor-card">
                <div class="rpt-advisor-glow"></div>
                <div class="rpt-advisor-inner">
                    <div class="rpt-advisor-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="rpt-advisor-content">
                        <div class="rpt-advisor-label">
                            <span class="rpt-badge-ai">AI Insights</span>
                            Smart Business Advisor
                        </div>
                        <p id="advisorText" class="rpt-advisor-text">Fetching insights...</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="rpt-card rpt-peak-card">
                <h6 class="rpt-card-label"><i class="fas fa-bolt me-2 text-warning"></i>Activity Peaks</h6>
                <div class="rpt-peak-item">
                    <div class="rpt-peak-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="rpt-peak-label">Busiest Day</div>
                        <div class="rpt-peak-value" id="busiestDay">—</div>
                    </div>
                </div>
                <div class="rpt-peak-divider"></div>
                <div class="rpt-peak-item">
                    <div class="rpt-peak-icon" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="rpt-peak-label">Peak Hour</div>
                        <div class="rpt-peak-value" id="peakHour">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Monthly Revenue Chart (full width) -->
    <div class="rpt-card mb-3">
        <div class="rpt-card-header">
            <div class="rpt-card-title">
                <i class="fas fa-chart-area rpt-icon-warning"></i>
                Monthly Revenue
                <span class="rpt-card-subtitle">Last 12 months</span>
            </div>
            <div class="rpt-chart-legend">
                <span class="rpt-legend-dot" style="background:#c9a74d"></span> Revenue (TK)
            </div>
        </div>
        <div id="monthlyRevenueChartContainer" class="rpt-chart-wrapper">
            <div class="rpt-chart-yaxis" id="chartYAxis">
                <span></span><span></span><span></span><span></span><span>0</span>
            </div>
            <div id="monthlyRevenueChart" class="rpt-chart-bars">
                <div class="text-center py-5 w-100 text-muted"><i class="fas fa-circle-notch fa-spin me-2"></i>Loading chart...</div>
            </div>
        </div>
    </div>

    <!-- Row 3: Popular Items + Revenue Trends -->
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="rpt-card h-100">
                <div class="rpt-card-header">
                    <div class="rpt-card-title">
                        <i class="fas fa-fire rpt-icon-danger"></i> Top Selling Items
                    </div>
                </div>
                <div id="popularItems" class="rpt-popular-list"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="rpt-card h-100">
                <div class="rpt-card-header">
                    <div class="rpt-card-title">
                        <i class="fas fa-chart-line rpt-icon-success"></i> Daily Revenue Trends
                        <span class="rpt-card-subtitle">Last 7 days</span>
                    </div>
                </div>
                <div id="revenueChart" class="rpt-trends-list"></div>
            </div>
        </div>
    </div>

    <!-- Row 4: Profit & Loss -->
    <div class="rpt-card rpt-pl-card">
        <div class="rpt-card-header">
            <div class="rpt-card-title">
                <i class="fas fa-scale-balanced rpt-icon-primary"></i> Profit & Loss Summary
            </div>
        </div>
        <div class="rpt-pl-grid" id="profitLossContainer">
            <div class="rpt-pl-item rpt-pl-revenue">
                <div class="rpt-pl-icon"><i class="fas fa-arrow-trend-up"></i></div>
                <div class="rpt-pl-data">
                    <span class="rpt-pl-label">Total Revenue</span>
                    <span class="rpt-pl-value" id="plRevenue">TK 0</span>
                </div>
            </div>
            <div class="rpt-pl-operator">−</div>
            <div class="rpt-pl-item rpt-pl-expense">
                <div class="rpt-pl-icon"><i class="fas fa-arrow-trend-down"></i></div>
                <div class="rpt-pl-data">
                    <span class="rpt-pl-label">Total Expenses</span>
                    <span class="rpt-pl-value" id="plExpenses">TK 0</span>
                </div>
            </div>
            <div class="rpt-pl-operator">=</div>
            <div class="rpt-pl-item rpt-pl-profit">
                <div class="rpt-pl-icon"><i class="fas fa-coins"></i></div>
                <div class="rpt-pl-data">
                    <span class="rpt-pl-label">Net Profit</span>
                    <span class="rpt-pl-value" id="plProfit">TK 0</span>
                </div>
            </div>
        </div>
    </div>

</section>

<!-- ===== NOTIFICATIONS ===== -->
<section id="notifications" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-bell me-2"></i>Notifications</h2><p>All system notifications</p></div>
        <button class="btn btn-secondary btn-sm" onclick="markAllNotificationsRead(event)"><i class="fas fa-check-double me-1"></i>Mark All Read</button>
    </div>
    <div id="notificationsListContainer"><div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i></div></div>
</section>

<!-- ===== SYSTEM LOGS ===== -->
<section id="activity-log" class="admin-section">
    <div class="section-header">
        <div>
            <h2><i class="fas fa-list-check me-2"></i>System Logs</h2>
            <p>Complete audit trail — Orders, Payments, Users, Inventory, Staff & more</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <input type="text" id="logSearchInput" class="form-control form-control-sm"
                   placeholder="Search logs..." style="width:200px" oninput="loadActivityLog()">
            <input type="date" id="logDateFrom" class="form-control form-control-sm" style="width:140px" onchange="loadActivityLog()">
            <input type="date" id="logDateTo" class="form-control form-control-sm" style="width:140px" onchange="loadActivityLog()">
            <button class="btn btn-outline-secondary btn-sm" onclick="clearLogFilters()">
                <i class="fas fa-times me-1"></i>Clear
            </button>
        </div>
    </div>

    <!-- Log Category Tabs -->
    <div class="syslog-tabs mb-4">
        <button class="syslog-tab-btn active" onclick="switchLogTab('all',this)">
            <i class="fas fa-layer-group me-1"></i>All Logs
            <span class="syslog-count" id="cnt-all">0</span>
        </button>
        <button class="syslog-tab-btn" onclick="switchLogTab('order',this)">
            <i class="fas fa-cart-shopping me-1"></i>Order Log
            <span class="syslog-count" id="cnt-order">0</span>
        </button>
        <button class="syslog-tab-btn" onclick="switchLogTab('payment',this)">
            <i class="fas fa-credit-card me-1"></i>Payment Log
            <span class="syslog-count" id="cnt-payment">0</span>
        </button>
        <button class="syslog-tab-btn" onclick="switchLogTab('reservation',this)">
            <i class="fas fa-calendar me-1"></i>Reservation Log
            <span class="syslog-count" id="cnt-reservation">0</span>
        </button>
        <button class="syslog-tab-btn" onclick="switchLogTab('inventory',this)">
            <i class="fas fa-boxes-stacked me-1"></i>Inventory Log
            <span class="syslog-count" id="cnt-inventory">0</span>
        </button>
        <button class="syslog-tab-btn" onclick="switchLogTab('staff',this)">
            <i class="fas fa-id-badge me-1"></i>Staff Log
            <span class="syslog-count" id="cnt-staff">0</span>
        </button>
        <button class="syslog-tab-btn" onclick="switchLogTab('login',this)">
            <i class="fas fa-right-to-bracket me-1"></i>Login Log
            <span class="syslog-count" id="cnt-login">0</span>
        </button>
        <button class="syslog-tab-btn" onclick="switchLogTab('error',this)">
            <i class="fas fa-triangle-exclamation me-1"></i>Error Log
            <span class="syslog-count" id="cnt-error">0</span>
        </button>
    </div>

    <!-- Stats row -->
    <div class="stats-grid mb-4" id="logStatsGrid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))"></div>

    <!-- Log Table -->
    <div class="analytics-card p-0" style="overflow:hidden;">
        <div class="d-flex justify-content-between align-items-center p-3" style="border-bottom:1px solid #f1f5f9;">
            <span style="font-weight:700;color:#0f172a;" id="logTableTitle">All Logs</span>
            <div class="d-flex gap-2 align-items-center">
                <span id="logTableCount" style="font-size:.82rem;color:#64748b;"></span>
                <button class="btn btn-outline-secondary btn-sm" onclick="loadActivityLog()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportLogCSV()">
                    <i class="fas fa-download me-1"></i>Export CSV
                </button>
            </div>
        </div>
        <div style="overflow-x:auto;max-height:520px;overflow-y:auto;">
            <table class="admin-table" id="logTable">
                <thead>
                    <tr>
                        <th style="width:155px;">Date & Time</th>
                        <th style="width:110px;">Category</th>
                        <th style="width:110px;">Type</th>
                        <th>Event / Message</th>
                        <th style="width:90px;">Related</th>
                        <th style="width:80px;">Status</th>
                    </tr>
                </thead>
                <tbody id="activityLogBody">
                    <tr><td colspan="6" class="text-center py-5" style="color:#475569;">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 d-block"></i>Loading logs...
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3" style="border-top:1px solid #f1f5f9;">
            <span id="logPaginationInfo" style="font-size:.82rem;color:#64748b;"></span>
            <div class="d-flex gap-2" id="logPaginationBtns"></div>
        </div>
    </div>
</section>

<!-- ===== SETTINGS ===== -->
<section id="settings" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-cog me-2"></i>Settings</h2><p>Restaurant configuration and system settings</p></div>
    </div>
    <!-- Settings Tabs -->
    <div class="settings-tabs mb-4">
        <button class="settings-tab-btn active" onclick="switchSettingsTab('general',this)"><i class="fas fa-store me-1"></i>Restaurant Info</button>
        <button class="settings-tab-btn" onclick="switchSettingsTab('hours',this)"><i class="fas fa-clock me-1"></i>Hours</button>
        <button class="settings-tab-btn" onclick="switchSettingsTab('payment',this)"><i class="fas fa-credit-card me-1"></i>Payment</button>
        <button class="settings-tab-btn" onclick="switchSettingsTab('security',this)"><i class="fas fa-shield-alt me-1"></i>Security</button>
        <button class="settings-tab-btn" onclick="switchSettingsTab('system',this)"><i class="fas fa-server me-1"></i>System</button>
    </div>

    <!-- General Info -->
    <div id="settings-general" class="settings-tab-pane active">

        <div class="ri-layout">

            <!-- Col 1: Basic Information -->
            <div class="settings-card">
                <h3><i class="fas fa-store me-2 text-primary"></i>Basic Information</h3>
                <form id="restaurantInfoForm">
                    <div class="form-group">
                        <label>Restaurant Name <span class="text-danger">*</span></label>
                        <input type="text" id="restaurantName" name="restaurant_name" placeholder="e.g. Feliciano Restaurant">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" id="restaurantPhone" name="restaurant_phone" placeholder="+88017XXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="restaurantEmail" name="restaurant_email" placeholder="info@restaurant.com">
                    </div>
                    <div class="form-group">
                        <label>Full Address</label>
                        <textarea id="restaurantAddress" name="restaurant_address" rows="2" placeholder="House #, Road #, Area, City..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>About Us</label>
                        <textarea id="restaurantAbout" name="restaurant_about" rows="4" placeholder="Brief description about your restaurant..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save Basic Info</button>
                </form>
            </div>

            <!-- Col 2: Logo + Cover + Google Map -->
            <div class="settings-card">
                <h3><i class="fas fa-image me-2 text-warning"></i>Logo & Cover Image</h3>

                <!-- Logo -->
                <div class="ri-upload-block">
                    <div class="ri-upload-label">RESTAURANT LOGO</div>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img id="currentLogoPreview" src="../assets/images/favicon.png" alt="Logo"
                             class="ri-logo-preview"
                             onerror="this.src='../assets/images/favicon.png'">
                        <div>
                            <div id="logoFileName" class="ri-file-name">No file selected</div>
                            <div class="ri-file-hint">Recommended: 200×200px, PNG/JPG</div>
                        </div>
                    </div>
                    <form id="logoUploadForm" enctype="multipart/form-data">
                        <div class="d-flex gap-2">
                            <label class="btn btn-outline-secondary btn-sm mb-0 flex-grow-1" style="cursor:pointer;text-align:center;">
                                <i class="fas fa-folder-open me-1"></i>Choose Logo
                                <input type="file" id="logoFile" name="logo" accept="image/*" style="display:none" onchange="previewLogoUpload(this)">
                            </label>
                            <button type="submit" class="btn btn-warning btn-sm px-3"><i class="fas fa-upload me-1"></i>Upload</button>
                        </div>
                    </form>
                </div>

                <hr class="ri-divider">

                <!-- Cover Image -->
                <div class="ri-upload-block">
                    <div class="ri-upload-label">COVER / BANNER IMAGE</div>
                    <div class="ri-cover-preview-wrap mb-2">
                        <img id="currentCoverPreview" src="" alt="Cover" class="ri-cover-preview" style="display:none">
                        <span id="coverPlaceholder" class="ri-cover-placeholder"><i class="fas fa-panorama me-2"></i>No cover image</span>
                    </div>
                    <form id="coverUploadForm" enctype="multipart/form-data">
                        <div class="d-flex gap-2">
                            <label class="btn btn-outline-secondary btn-sm mb-0 flex-grow-1" style="cursor:pointer;text-align:center;">
                                <i class="fas fa-folder-open me-1"></i>Choose Cover
                                <input type="file" id="coverFile" name="cover" accept="image/*" style="display:none" onchange="previewCoverUpload(this)">
                            </label>
                            <button type="submit" class="btn btn-info btn-sm text-white px-3"><i class="fas fa-upload me-1"></i>Upload</button>
                        </div>
                    </form>
                </div>

                <hr class="ri-divider">

                <!-- Google Map -->
                <div class="ri-upload-block">
                    <div class="ri-upload-label"><i class="fas fa-map-marker-alt me-1 text-danger"></i>GOOGLE MAP EMBED URL</div>
                    <form id="googleMapForm">
                        <div class="d-flex gap-2 mb-2">
                            <input type="text" id="googleMapUrl" name="google_map_url"
                                   placeholder="https://maps.google.com/maps?q=..."
                                   class="form-control form-control-sm" style="flex:1;color:#0f172a;">
                            <button type="submit" class="btn btn-danger btn-sm px-3"><i class="fas fa-save me-1"></i>Save</button>
                        </div>
                        <div id="googleMapPreviewWrap" style="display:none;">
                            <iframe id="googleMapPreviewFrame" width="100%" height="110"
                                    style="border:0;border-radius:8px;" allowfullscreen loading="lazy"></iframe>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Col 1: Social Media Links -->
            <div class="settings-card">
                <h3><i class="fas fa-share-alt me-2 text-success"></i>Social Media Links</h3>
                <form id="socialLinksForm">
                    <div class="form-group">
                        <label><i class="fab fa-facebook me-2 text-primary"></i>Facebook</label>
                        <input type="url" id="settingFacebook" name="social_facebook" placeholder="https://facebook.com/yourpage">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-instagram me-2" style="color:#e1306c"></i>Instagram</label>
                        <input type="url" id="settingInstagram" name="social_instagram" placeholder="https://instagram.com/yourpage">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-twitter me-2 text-info"></i>Twitter / X</label>
                        <input type="url" id="settingTwitter" name="social_twitter" placeholder="https://twitter.com/yourhandle">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp</label>
                        <input type="text" id="settingWhatsapp" name="social_whatsapp" placeholder="+88017XXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-youtube me-2 text-danger"></i>YouTube</label>
                        <input type="url" id="settingYoutube" name="social_youtube" placeholder="https://youtube.com/yourchannel">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-tiktok me-2"></i>TikTok</label>
                        <input type="url" id="settingTiktok" name="social_tiktok" placeholder="https://tiktok.com/@yourhandle">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save Social Links</button>
                </form>
            </div>

            <!-- Col 2: Legal Content -->
            <div class="settings-card">
                <h3><i class="fas fa-file-contract me-2" style="color:#8b5cf6"></i>Legal Content</h3>
                <ul class="nav nav-tabs mb-3" id="legalTabs" style="font-size:.83rem;">
                    <li class="nav-item">
                        <a class="nav-link active" href="javascript:void(0)" onclick="switchLegalTab('terms',this,event)">
                            <i class="fas fa-file-alt me-1"></i>Terms & Conditions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="javascript:void(0)" onclick="switchLegalTab('privacy',this,event)">
                            <i class="fas fa-shield-alt me-1"></i>Privacy Policy
                        </a>
                    </li>
                </ul>
                <div id="legalTabTerms">
                    <form id="termsForm">
                        <div class="form-group">
                            <textarea id="termsConditions" name="terms_conditions" rows="13"
                                      placeholder="Write your Terms & Conditions here..."
                                      style="font-size:.84rem;resize:vertical;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-save me-1"></i>Save Terms</button>
                    </form>
                </div>
                <div id="legalTabPrivacy" style="display:none;">
                    <form id="privacyForm">
                        <div class="form-group">
                            <textarea id="privacyPolicy" name="privacy_policy" rows="13"
                                      placeholder="Write your Privacy Policy here..."
                                      style="font-size:.84rem;resize:vertical;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-save me-1"></i>Save Privacy Policy</button>
                    </form>
                </div>
            </div>

        </div><!-- /.ri-layout -->
    </div>

    <!-- Operating Hours -->
    <div id="settings-hours" class="settings-tab-pane" style="display:none">
        <div class="settings-card">
            <h3><i class="fas fa-clock me-2 text-warning"></i>Operating Hours</h3>
            <p style="font-size:.875rem;color:#334155;font-weight:500;margin-bottom:1.2rem;">Set opening and closing times for each day. Leave blank if closed that day.</p>
            <form id="operatingHoursForm">
                <div class="hours-grid">
                    <?php
                    $days = [
                        'monday'    => 'Monday',
                        'tuesday'   => 'Tuesday',
                        'wednesday' => 'Wednesday',
                        'thursday'  => 'Thursday',
                        'friday'    => 'Friday',
                        'saturday'  => 'Saturday',
                        'sunday'    => 'Sunday',
                    ];
                    foreach ($days as $key => $label): ?>
                    <div class="day-hours" style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;margin-bottom:6px;">
                        <div style="width:95px;font-weight:700;color:#0f172a;font-size:.875rem;"><?= $label ?></div>
                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                            <input type="time" id="open_<?= $key ?>" name="open_<?= $key ?>"
                                   class="form-control form-control-sm" style="width:120px;color:#0f172a;font-weight:600;" title="Opening Time">
                            <span style="font-size:.85rem;color:#334155;font-weight:600;">to</span>
                            <input type="time" id="close_<?= $key ?>" name="close_<?= $key ?>"
                                   class="form-control form-control-sm" style="width:120px;color:#0f172a;font-weight:600;" title="Closing Time">
                            <div class="form-check form-switch ms-2 mb-0">
                                <input class="form-check-input" type="checkbox" id="closed_<?= $key ?>" name="closed_<?= $key ?>"
                                       onchange="toggleDayClosed('<?= $key ?>')">
                                <label class="form-check-label text-danger fw-bold" for="closed_<?= $key ?>" style="font-size:.82rem;">Closed</label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Operating Hours</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillAllHours()">
                        <i class="fas fa-copy me-1"></i>Copy Mon to All
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Settings -->
    <div id="settings-payment" class="settings-tab-pane" style="display:none">
        <div class="settings-card">
            <h3><i class="fas fa-credit-card me-2 text-success"></i>Payment Methods</h3>
            <form id="paymentSettingsForm">
                <div class="form-group"><label>Currency</label><select id="settingCurrency" name="currency"><option value="BDT">BDT – Bangladeshi Taka</option><option value="USD">USD – US Dollar</option></select></div>
                <div class="form-group"><label>Tax/VAT (%)</label><input type="number" id="settingTax" name="tax_percentage" min="0" max="100" step="0.1" placeholder="0"></div>
                <div class="form-group"><label>bKash Number</label><input type="text" id="settingBkash" name="bkash_number" placeholder="+88017XXXXXXXX"></div>
                <div class="form-group"><label>Nagad Number</label><input type="text" id="settingNagad" name="nagad_number" placeholder="+88017XXXXXXXX"></div>
                <div class="form-group"><label>Delivery Charge (TK)</label><input type="number" id="settingDelivery" name="delivery_charge" min="0" placeholder="0"></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Payment Settings</button>
            </form>
        </div>
    </div>

    <!-- Security -->
    <div id="settings-security" class="settings-tab-pane" style="display:none">
        <div class="settings-grid">

            <!-- Card 1: Change Password -->
            <div class="settings-card">
                <h3><i class="fas fa-key me-2 text-danger"></i>Change Password</h3>
                <form id="securityForm">
                    <div class="form-group">
                        <label>Current Password</label>
                        <div class="sec-input-wrap">
                            <input type="password" id="currentPassword" name="current_password" placeholder="Enter current password">
                            <button type="button" class="sec-eye-btn" onclick="toggleSecPwd('currentPassword',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="sec-input-wrap">
                            <input type="password" id="newPassword" name="new_password" placeholder="Min. 8 characters">
                            <button type="button" class="sec-eye-btn" onclick="toggleSecPwd('newPassword',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="pwdStrengthBar" class="pwd-strength-bar mt-1" style="display:none">
                            <div id="pwdStrengthFill" class="pwd-strength-fill"></div>
                        </div>
                        <small id="pwdStrengthText" style="color:#64748b;"></small>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="sec-input-wrap">
                            <input type="password" id="confirmPassword" name="confirm_password" placeholder="Repeat new password">
                            <button type="button" class="sec-eye-btn" onclick="toggleSecPwd('confirmPassword',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100"><i class="fas fa-lock me-1"></i>Update Password</button>
                </form>

                <hr class="my-4">

                <!-- Session Info -->
                <h5 style="color:#0f172a;font-weight:700;margin-bottom:12px;"><i class="fas fa-user-shield me-2 text-primary"></i>Current Session</h5>
                <div class="p-3 rounded" style="background:#f1f5f9;border:1px solid #e2e8f0;">
                    <div class="d-flex justify-content-between mb-2">
                        <span style="color:#475569;font-weight:600;">Logged in as</span>
                        <span style="color:#0f172a;font-weight:700;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span style="color:#475569;font-weight:600;">Role</span>
                        <span style="color:#0f172a;font-weight:700;">Super Administrator</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span style="color:#475569;font-weight:600;">Session Since</span>
                        <span style="color:#0f172a;font-weight:700;"><?= date('d M Y, H:i') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span style="color:#475569;font-weight:600;">IP Address</span>
                        <span style="color:#0f172a;font-weight:700;"><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'N/A') ?></span>
                    </div>
                </div>
                <button class="btn btn-outline-danger btn-sm w-100 mt-3" onclick="logout()">
                    <i class="fas fa-sign-out-alt me-1"></i>Terminate Current Session
                </button>
            </div>

            <!-- Card 2: Login History -->
            <div class="settings-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0"><i class="fas fa-clock-rotate-left me-2 text-info"></i>Login History</h3>
                    <button class="btn btn-outline-secondary btn-sm" onclick="loadLoginHistory()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
                <!-- Filter -->
                <div class="d-flex gap-2 mb-3">
                    <select id="loginHistoryFilter" class="form-select form-select-sm" onchange="loadLoginHistory()" style="width:130px">
                        <option value="all">All</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                    </select>
                    <input type="text" id="loginHistorySearch" class="form-control form-control-sm"
                           placeholder="Search IP / user..." oninput="loadLoginHistory()" style="flex:1">
                </div>
                <div id="loginHistoryList" style="max-height:420px;overflow-y:auto">
                    <div class="text-center py-4" style="color:#475569;"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
                </div>
                <div id="loginHistoryStats" class="d-flex gap-3 mt-3 pt-3" style="border-top:1px solid #e2e8f0;font-size:.82rem;"></div>
            </div>

        </div>

        <!-- Row 2: Activity Log + Backup -->
        <div class="settings-grid mt-4">

            <!-- Card 3: Activity Log (real data) -->
            <div class="settings-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0"><i class="fas fa-list-check me-2 text-warning"></i>Activity Log</h3>
                    <div class="d-flex gap-2">
                        <select id="secLogTypeFilter" class="form-select form-select-sm" onchange="loadSecActivityLog()" style="width:140px">
                            <option value="all">All Types</option>
                            <option value="order">Orders</option>
                            <option value="payment">Payments</option>
                            <option value="reservation">Reservations</option>
                            <option value="inventory">Inventory</option>
                            <option value="staff">Staff</option>
                            <option value="login">Login</option>
                            <option value="error">Errors</option>
                            <option value="system">System</option>
                        </select>
                        <button class="btn btn-outline-secondary btn-sm" onclick="loadSecActivityLog()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div style="max-height:350px;overflow-y:auto;">
                    <table class="admin-table" id="secActivityTable">
                        <thead>
                            <tr><th style="width:140px">Time</th><th style="width:90px">Type</th><th>Event</th><th style="width:80px">Status</th></tr>
                        </thead>
                        <tbody id="secActivityBody">
                            <tr><td colspan="4" class="text-center py-4" style="color:#475569;"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 4: Backup & Restore -->
            <div class="settings-card">
                <h3><i class="fas fa-database me-2 text-success"></i>Backup & Restore</h3>

                <!-- Backup -->
                <div class="sec-backup-block">
                    <div class="sec-backup-icon"><i class="fas fa-cloud-arrow-down"></i></div>
                    <div>
                        <div style="font-weight:700;color:#0f172a;margin-bottom:2px;">Database Backup</div>
                        <div style="font-size:.82rem;color:#475569;">Download a full SQL backup of all restaurant data.</div>
                    </div>
                    <button class="btn btn-success btn-sm ms-auto" onclick="downloadBackup()">
                        <i class="fas fa-download me-1"></i>Download
                    </button>
                </div>

                <hr style="border-color:#e2e8f0;margin:16px 0;">

                <!-- Security Stats -->
                <h5 style="color:#0f172a;font-weight:700;margin-bottom:12px;"><i class="fas fa-shield-check me-2 text-success"></i>Security Overview</h5>
                <div id="securityStats">
                    <div class="text-center py-3" style="color:#475569;"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
                </div>

                <hr style="border-color:#e2e8f0;margin:16px 0;">

                <!-- Quick Security Checklist -->
                <h5 style="color:#0f172a;font-weight:700;margin-bottom:10px;"><i class="fas fa-list-check me-2 text-info"></i>Security Checklist</h5>
                <div class="sec-checklist">
                    <div class="sec-check-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>CSRF Protection — Active</span>
                    </div>
                    <div class="sec-check-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Password Hashing (bcrypt) — Active</span>
                    </div>
                    <div class="sec-check-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Session Management — Active</span>
                    </div>
                    <div class="sec-check-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>SQL Injection Protection — Active</span>
                    </div>
                    <div class="sec-check-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Login History Logging — Active</span>
                    </div>
                    <div class="sec-check-item">
                        <i class="fas fa-info-circle text-warning"></i>
                        <span>2FA — Optional (not configured)</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- System Tab -->
    <div id="settings-system" class="settings-tab-pane" style="display:none">
        <div class="settings-grid">
            <div class="settings-card">
                <h3><i class="fas fa-database me-2 text-success"></i>Database Backup</h3>
                <p style="color:#334155;font-weight:500;margin-bottom:.75rem;">Download a full backup of the restaurant database.</p>
                <button class="btn btn-success" onclick="downloadBackup()"><i class="fas fa-download me-2"></i>Download Backup (.sql)</button>
                <hr class="my-3">
                <h5 style="color:#0f172a;font-weight:700;margin-bottom:.75rem;"><i class="fas fa-bell me-2 text-warning"></i>Notification Settings</h5>
                <form id="notifSettingsForm">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="notifEmail" name="notif_email" checked>
                        <label class="form-check-label fw-semibold" for="notifEmail" style="color:#1e293b;">Email Notifications</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="notifSMS" name="notif_sms">
                        <label class="form-check-label fw-semibold" for="notifSMS" style="color:#1e293b;">SMS Notifications</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="notifPush" name="notif_push" checked>
                        <label class="form-check-label fw-semibold" for="notifPush" style="color:#1e293b;">Push Notifications</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save Preferences</button>
                </form>
            </div>
            <div class="settings-card">
                <h3><i class="fas fa-info-circle me-2 text-primary"></i>System Information</h3>
                <table class="table table-sm table-borderless">
                    <tr><td style="color:#475569;font-weight:600;">PHP Version</td><td><strong style="color:#0f172a;"><?= phpversion() ?></strong></td></tr>
                    <tr><td style="color:#475569;font-weight:600;">Server</td><td><strong style="color:#0f172a;"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></strong></td></tr>
                    <tr><td style="color:#475569;font-weight:600;">Database</td><td><strong style="color:#0f172a;">MySQL</strong></td></tr>
                    <tr><td style="color:#475569;font-weight:600;">Current Time</td><td><strong style="color:#0f172a;"><?= date('d M Y H:i:s') ?></strong></td></tr>
                    <tr><td style="color:#475569;font-weight:600;">Timezone</td><td><strong style="color:#0f172a;"><?= date_default_timezone_get() ?></strong></td></tr>
                </table>
                <hr>
                <h5 style="color:#0f172a;font-weight:700;margin-bottom:.75rem;"><i class="fas fa-language me-2"></i>Language & Currency</h5>
                <form id="localeSettingsForm">
                    <div class="form-group"><label>Currency Symbol</label>
                        <select name="currency" id="settingCurrencySelect">
                            <option value="TK">TK – Bangladeshi Taka</option>
                            <option value="BDT">BDT</option>
                            <option value="USD">USD – US Dollar</option>
                            <option value="EUR">EUR – Euro</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Timezone</label>
                        <select name="timezone" id="settingTimezone">
                            <option value="Asia/Dhaka">Asia/Dhaka (GMT+6)</option>
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">America/New_York</option>
                            <option value="Europe/London">Europe/London</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save</button>
                </form>
            </div>
        </div>
    </div>
</section>

</main><!-- /.admin-main -->

<script>window.categoryLabels = <?php echo json_encode($category_labels); ?>;</script>
<script>
// Dynamic category cache — populated on page load
window.dynamicCategories = [];

// ── RBAC: allowed sections for current user (PHP → JS) ──
const RBAC_ALLOWED_SECTIONS = <?php echo json_encode(array_values($allowed_sections)); ?>;
const RBAC_IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
const RBAC_USER_ROLE = <?php echo json_encode($user_role); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="admin-script.js"></script>
</body>
</html>
