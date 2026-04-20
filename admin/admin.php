<?php
session_start();
require_once '../config/database.php';
require_once '../config/categories.php';

// Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
    // Redirect non-admins to login
    header('Location: ../auth/login.php');
    exit;
}

// Initial fetch for notifications dropdown
$notifications_query = $conn->query("SELECT * FROM admin_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5");
$top_notifications = [];
if ($notifications_query) {
    while($row = $notifications_query->fetch_assoc()) {
        $top_notifications[] = $row;
    }
}
$unread_count_query = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0");
$unread_count = $unread_count_query ? $unread_count_query->fetch_assoc()['count'] : 0;

// Fetch all branches for filtering
$branches_query = $conn->query("SELECT * FROM branches ORDER BY name ASC");
$branches = [];
if ($branches_query) {
    while($row = $branches_query->fetch_assoc()) {
        $branches[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feliciano Admin Panel</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="admin-container">
            <div class="admin-logo">
                <h1><i class="fas fa-utensils"></i> Feliciano Admin</h1>
            </div>
            <div class="admin-user" style="display: flex; align-items: center;">
                <!-- Branch Selector -->
                <div class="branch-selector-container" style="margin-right: 20px; display: flex; align-items: center; gap: 10px;">
                    <label for="globalBranchSelector" style="color: #94a3b8; font-size: 0.8rem; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">Branch:</label>
                    <select id="globalBranchSelector" class="form-select form-select-sm" style="background: #2c3e50; color: #c9a74d; border: 1px solid rgba(201, 167, 77, 0.3); border-radius: 6px; padding: 5px 30px 5px 12px; cursor: pointer; min-width: 160px; font-weight: 600;" onchange="handleGlobalBranchChange(this.value)">
                        <option value="all">All Branches</option>
                        <?php foreach($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Notification Dropdown -->
                <div class="dropdown" style="margin-right: 20px;">
                    <style>.dropdown-toggle.hide-arrow::after { display: none !important; }</style>
                    <a href="#" class="notification-badge dropdown-toggle hide-arrow" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer; position: relative; text-decoration: none; display: flex; align-items: center; justify-content: center; background: #34495e; color: #c9a74d; padding: 10px 14px; border-radius: 50%; transition: all 0.3s;">
                        <i class="fas fa-bell" style="font-size: 1.2rem;"></i>
                        <span class="notification-count <?= $unread_count > 0 ? '' : 'hidden' ?>" id="notificationCount">
                            <?= $unread_count ?>
                        </span>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" id="headerNotificationsDropdown" aria-labelledby="notificationDropdown" style="width: 350px; max-height: 450px; overflow-y: auto; padding: 0; border-radius: 12px; margin-top: 10px;">
                        <li><h6 class="dropdown-header bg-light py-3 border-bottom" style="font-weight: 700; color: #2c3e50;">Recent Notifications</h6></li>
                        <div id="dropdownNotificationsList">
                            <?php if(empty($top_notifications)): ?>
                                <li id="noNotifItem"><span class="dropdown-item py-4 text-center text-muted">No unread notifications</span></li>
                            <?php else: ?>
                                <?php foreach($top_notifications as $notif): ?>
                                <li>
                                    <a class="dropdown-item py-3 px-3 border-bottom notification-dropdown-item" href="#" onclick="handleDropdownNotifClick(event, <?= $notif['id'] ?>, '<?= $notif['type'] ?>', '<?= $notif['related_id'] ?>')" style="white-space: normal; background-color: #f8f9fa; transition: all 0.2s;">
                                        <div class="d-flex w-100 justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0 text-truncate" style="font-size: 0.95rem; font-weight: 600; color: #2c3e50; max-width: 75%;">
                                                <i class="fas <?= $notif['type'] == 'order' ? 'fa-shopping-cart text-primary' : ($notif['type'] == 'reservation' ? 'fa-calendar-alt text-warning' : 'fa-info-circle text-info') ?> me-2"></i>
                                                <?= htmlspecialchars($notif['title']) ?>
                                            </h6>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime($notif['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-0 text-muted" style="font-size: 0.85rem; line-height: 1.4; margin-left: 28px;">
                                            <?= htmlspecialchars($notif['message']) ?>
                                        </p>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <li class="border-top">
                            <a class="dropdown-item text-center text-primary py-2 w-100" href="#" onclick="markAllNotificationsRead(event)" style="font-size: 0.85rem; font-weight: 600;">
                                <i class="fas fa-check-double me-1"></i> Mark All Read
                            </a>
                        </li>
                    </ul>
                </div>

                <span class="admin-welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </header>

    <!-- Admin Sidebar -->
    <aside class="admin-sidebar">
        <nav class="admin-nav">
            <ul>
                <li><a href="#dashboard" class="nav-link active" onclick="showSection('dashboard')">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="#menu-management" class="nav-link" onclick="showSection('menu-management')">
                    <i class="fas fa-utensils"></i> Menu Management
                </a></li>
                <li><a href="#orders" class="nav-link" onclick="showSection('orders')">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a></li>
                <li><a href="#customers" class="nav-link" onclick="showSection('customers')">
                    <i class="fas fa-users"></i> Customers
                </a></li>
                <li><a href="#team" class="nav-link" onclick="showSection('team')">
                    <i class="fas fa-user-shield"></i> Team Management
                </a></li>
                <li><a href="#branches" class="nav-link" onclick="showSection('branches')">
                    <i class="fas fa-store-alt"></i> Branch Management
                </a></li>
                <li><a href="#reservations" class="nav-link" onclick="showSection('reservations')">
                    <i class="fas fa-calendar-alt"></i> Reservations
                </a></li>
                <li><a href="#analytics" class="nav-link" onclick="showSection('analytics')">
                    <i class="fas fa-chart-bar"></i> Analytics
                </a></li>
                <li><a href="#settings" class="nav-link" onclick="showSection('settings')">
                    <i class="fas fa-cog"></i> Settings
                </a></li>
                <li class="nav-separator"></li>
                <li><a href="../index.php" class="nav-link view-site-link">
                    <i class="fas fa-external-link-alt"></i> View Website
                </a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <?php 
            include 'dashboard.php';
            include 'manage-menu.php';
            include 'manage-orders.php';
            include 'manage-users.php';
            include 'manage-team.php';
            include 'manage-reservations.php';
            include 'manage-branches.php';
        ?>

        <!-- Notifications Section -->

        <!-- Analytics Section -->
        <section id="analytics" class="admin-section">
            <div class="section-header">
                <h2>Analytics & Reports</h2>
                <div class="date-range-picker" style="display: flex; gap: 12px; align-items: center; background: #fff; padding: 8px 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label for="startDate" style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin: 0;">FROM</label>
                        <input type="date" id="startDate" class="form-control form-control-sm" style="border-color: #e2e8f0;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label for="endDate" style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin: 0;">TO</label>
                        <input type="date" id="endDate" class="form-control form-control-sm" style="border-color: #e2e8f0;">
                    </div>
                    <button class="btn btn-primary btn-sm px-3" onclick="generateReport()" style="font-weight: 600;">
                        <i class="fas fa-sync-alt me-1"></i> Update
                    </button>
                </div>
            </div>

            <!-- Analytics Summary Stats -->
            <div id="analyticsSummary" class="analytics-metrics-grid mb-4">
                <!-- Populated by JS -->
            </div>

            <!-- Smart Advisor & Operational Insights -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div id="smartAdvisor" class="smart-advisor-panel h-100">
                        <div class="advisor-card">
                            <div class="advisor-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div class="advisor-content">
                                <h4>Smart Advisor Insights</h4>
                                <p id="advisorText">Fetching latest performance insights...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div id="operationalInsights" class="operational-card h-100">
                        <div class="op-item">
                            <span class="op-label">Busiest Day</span>
                            <span class="op-value" id="busiestDay">Loading...</span>
                        </div>
                        <div class="op-item">
                            <span class="op-label">Peak Hour</span>
                            <span class="op-value" id="peakHour">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>Popular Items</h3>
                    <div class="popular-items" id="popularItems">
                        <!-- Popular items will be populated here -->
                    </div>
                </div>
                
                <div class="analytics-card">
                    <h3>Revenue Trends</h3>
                    <div class="revenue-chart" id="revenueChart">
                        <p>Revenue chart would be displayed here</p>
                    </div>
                </div>
            </div>

            <!-- Annual Revenue Analytics -->
            <div class="analytics-card mt-4">
                <h3><i class="fas fa-chart-area me-2" style="color: #c9a74d;"></i>Monthly Revenue Overview (Last 12 Months)</h3>
                <div id="monthlyRevenueChartContainer" class="monthly-revenue-wrapper">
                    <!-- Y-Axis Legend -->
                    <div class="chart-y-axis" id="chartYAxis">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                        <span>0</span>
                    </div>
                    
                    <div id="monthlyRevenueChart" class="monthly-revenue-chart">
                        <!-- Populated by JS -->
                        <div class="text-center py-5 w-100"><i class="fas fa-circle-notch fa-spin me-2"></i> Loading annual data...</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Settings Section -->
        <section id="settings" class="admin-section">
            <div class="section-header">
                <h2>Restaurant Settings</h2>
            </div>
            
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Restaurant Information</h3>
                    <form id="restaurantInfoForm">
                        <div class="form-group">
                            <label>Restaurant Name</label>
                            <input type="text" id="restaurantName" name="restaurant_name" value="Feliciano">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" id="restaurantAddress" name="restaurant_address" value="123 Gourmet Street, Food City">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" id="restaurantPhone" name="restaurant_phone" value="+8801772-353298">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="restaurantEmail" name="restaurant_email" value="info@feliciano.com">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
                
                <div class="settings-card">
                    <h3>Operating Hours</h3>
                    <form id="operatingHoursForm">
                        <div class="hours-grid">
                            <div class="day-hours">
                                <label>Monday - Thursday</label>
                                <input type="text" id="opening_hours_mon_thu" name="opening_hours_mon_thu" value="11:00 AM - 10:00 PM">
                            </div>
                            <div class="day-hours">
                                <label>Friday - Saturday</label>
                                <input type="text" id="opening_hours_fri_sat" name="opening_hours_fri_sat" value="11:00 AM - 11:00 PM">
                            </div>
                            <div class="day-hours">
                                <label>Sunday</label>
                                <input type="text" id="opening_hours_sun" name="opening_hours_sun" value="12:00 PM - 9:00 PM">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Hours</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Global category mapping for JS
        window.categoryLabels = <?php echo json_encode($category_labels); ?>;
    </script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin-script.js"></script>
</body>
</html>