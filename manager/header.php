<?php
// manager/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Fetch unread notifications
$branch_id = $_SESSION['user_branch_id'] ?? null;
$where_clause = "is_read = 0";
if ($branch_id) {
    $where_clause .= " AND (branch_id = $branch_id OR branch_id IS NULL)";
}

$notifications_query = $conn->query("SELECT * FROM admin_notifications WHERE $where_clause ORDER BY created_at DESC LIMIT 5");
$notifications = [];
$unread_count = 0;
if ($notifications_query) {
    while($row = $notifications_query->fetch_assoc()) {
        $notifications[] = $row;
    }
}
$unread_query = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE $where_clause");
if ($unread_query) {
    $unread_count = $unread_query->fetch_assoc()['count'];
}

// Ensure is_active is defined for sidebar.php
if (!function_exists('is_active')) {
    function is_active($page) {
        $curr = basename($_SERVER['PHP_SELF']);
        return ($curr == $page) ? 'active' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <title>Manager Dashboard - Feliciano</title>
    <!-- Bootstrap 5.3 CSS (Kept for forms/buttons if needed) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin Dashboard CSS -->
    <link rel="stylesheet" href="../admin/admin-styles.css">
</head>
<body>
    <!-- Manager Header -->
    <header class="admin-header">
        <div class="admin-container">
            <div class="admin-logo">
                <h1><i class="fas fa-utensils"></i> Feliciano Manager</h1>
            </div>
            <div class="admin-user" style="display: flex; align-items: center;">
                <div class="dropdown" style="margin-right: 15px;">
                    <style>.dropdown-toggle.hide-arrow::after { display: none !important; }</style>
                    <a href="#" class="notification-badge dropdown-toggle hide-arrow" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer; position: relative; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-bell" style="font-size: 1.2rem; color: #fff;"></i>
                        <?php if($unread_count > 0): ?>
                        <span class="notification-count" id="notificationCount" style="position: absolute; top: -8px; right: -8px; background: #e74c3c; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.7rem; font-weight: bold;">
                            <?= $unread_count ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationDropdown" style="width: 320px; max-height: 400px; overflow-y: auto; padding: 0;">
                        <li><h6 class="dropdown-header bg-light py-2 border-bottom">Notifications</h6></li>
                        <?php if(empty($notifications)): ?>
                            <li><span class="dropdown-item py-3 text-center text-muted">No unread notifications</span></li>
                        <?php else: ?>
                            <?php foreach($notifications as $notif): ?>
                            <li>
                                <a class="dropdown-item py-2 px-3 border-bottom" href="read_single_notification.php?id=<?= $notif['id'] ?>&type=<?= urlencode($notif['type']) ?>" style="white-space: normal; background-color: #f8f9fa;">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 text-truncate" style="font-size: 0.9rem; max-width: 70%;">
                                            <i class="fas <?= $notif['type'] == 'order' ? 'fa-shopping-cart text-primary' : ($notif['type'] == 'reservation' ? 'fa-calendar-alt text-warning' : 'fa-info-circle text-info') ?> me-2"></i>
                                            <?= htmlspecialchars($notif['title']) ?>
                                        </h6>
                                        <small class="text-muted" style="font-size: 0.7rem;"><?= date('M d, H:i', strtotime($notif['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0 text-muted" style="font-size: 0.8rem; line-height: 1.3; margin-left: 24px;">
                                        <?= htmlspecialchars($notif['message']) ?>
                                    </p>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if($unread_count > 0): ?>
                        <li>
                            <a class="dropdown-item text-center text-primary py-2" href="#" onclick="markNotificationsRead(event)" style="font-size: 0.85rem; font-weight: 500;">
                                <i class="fas fa-check-double me-1"></i> Mark all as read
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <span style="margin-right: 15px;">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Manager'); ?></span>
                <button class="logout-btn" onclick="window.location.href='../auth/logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </header>

    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-main">
