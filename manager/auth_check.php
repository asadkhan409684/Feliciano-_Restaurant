<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Check role: allow both manager and admin to access manager dashboard
$allowed_roles = ['manager', 'admin'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    // If they are logged in but not authorized, redirect to their respective index
    header("Location: ../index.php");
    exit;
}

// Helper function active menu link
$current_page = basename($_SERVER['PHP_SELF']);
function is_active($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
