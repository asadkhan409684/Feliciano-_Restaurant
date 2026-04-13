<?php
// manager/mark_notifications_read.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_logged_in']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'manager')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = $_SESSION['user_branch_id'] ?? null;
    $where_clause = "is_read = 0";
    
    // If manager, only mark notifications for their branch as read
    if ($_SESSION['user_role'] === 'manager' && $branch_id) {
        $where_clause .= " AND (branch_id = $branch_id OR branch_id IS NULL)";
    }
    
    $update = $conn->query("UPDATE admin_notifications SET is_read = 1 WHERE $where_clause");
    
    if ($update) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
