<?php
// manager/read_single_notification.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Check authorization
if (!isset($_SESSION['user_logged_in']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'manager')) {
    header('Location: ../auth/login.php');
    exit;
}

if (isset($_GET['id']) && isset($_GET['type'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type'];

    // Mark as read - only if it belongs to the manager's branch
    $branch_id = $_SESSION['user_branch_id'] ?? null;
    $sql = "UPDATE admin_notifications SET is_read = 1 WHERE id = ?";
    
    if ($_SESSION['user_role'] === 'manager' && $branch_id) {
        $sql .= " AND (branch_id = ? OR branch_id IS NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $branch_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
    }
    
    $stmt->execute();
    $stmt->close();

    // Redirect based on type
    if ($type === 'order') {
        header('Location: orders.php');
    } elseif ($type === 'reservation') {
        header('Location: reservations.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

// Fallback redirect
header('Location: index.php');
exit;
?>
