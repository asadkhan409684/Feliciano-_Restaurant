<?php
require_once 'auth_check.php';
require_once '../config/database.php';

// Fetch summary stats
$branch_id = $_SESSION['user_branch_id'] ?? null;
$role = $_SESSION['user_role'] ?? '';

$stats = [
    'pending_orders' => 0,
    'pending_reservations' => 0,
    'total_menu_items' => 0,
    'total_reviews' => 0
];

// Orders count (pending/preparing)
if ($role === 'manager' && !empty($branch_id)) {
    $res = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'preparing') AND branch_id = $branch_id");
} else {
    $res = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'preparing')");
}
if ($res) $stats['pending_orders'] = $res->fetch_assoc()['count'];

// Reservations count (pending)
if ($role === 'manager' && !empty($branch_id)) {
    $res = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending' AND branch_id = $branch_id");
} else {
    $res = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'");
}
if ($res) $stats['pending_reservations'] = $res->fetch_assoc()['count'];

// Total menu items
$res = $conn->query("SELECT COUNT(*) as count FROM menu_items");
if ($res) $stats['total_menu_items'] = $res->fetch_assoc()['count'];

// Total reviews 
$res = $conn->query("SELECT COUNT(*) as count FROM reviews");
if ($res) {
    $stats['total_reviews'] = $res->fetch_assoc()['count'];
}

include 'header.php';
?>

<section id="dashboard" class="admin-section active">
    <div class="section-header">
        <h2>Dashboard</h2>
        <p>Overview of your restaurant operations - <?= date('M d, Y') ?></p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="color: #4e73df;">
                <i class="fas fa-hamburger"></i>
            </div>
            <div class="stat-info">
                <h3 id="totalOrders"><?= $stats['pending_orders'] ?></h3>
                <p>Active Orders</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="color: #f6c23e;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
                <h3 id="totalReservations"><?= $stats['pending_reservations'] ?></h3>
                <p>Pending Reservations</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="color: #1cc88a;">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="stat-info">
                <h3 id="totalMenuItems"><?= $stats['total_menu_items'] ?></h3>
                <p>Menu Items</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="color: #36b9cc;">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-info">
                <h3 id="totalReviews"><?= $stats['total_reviews'] ?></h3>
                <p>Customer Feedback</p>
            </div>
        </div>
    </div>

    <div class="dashboard-charts">
        <div class="chart-container">
            <h3>Manager Responsibilities</h3>
            <div class="activity-list" id="recentActivity">
                <div class="activity-item">
                    <i class="fas fa-info-circle"></i>
                    <span>Manage Active Orders</span>
                    <small>Update order status from pending to ready</small>
                </div>
                <div class="activity-item">
                    <i class="fas fa-info-circle"></i>
                    <span>View Pending Reservations</span>
                    <small>Approve or cancel incoming table bookings</small>
                </div>
                <div class="activity-item">
                    <i class="fas fa-info-circle"></i>
                    <span>Update Menu Items</span>
                    <small>Adjust prices and availability</small>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
