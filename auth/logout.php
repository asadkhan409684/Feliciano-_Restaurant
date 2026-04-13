<?php
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
session_start();

include_once '../config/database.php';

// If user is logged in, clean up their remember token from DB
if (isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
}

// CSRF token delete session variable
unset($_SESSION['csrf_token']);
unset($_SESSION['login_attempts']);
unset($_SESSION['lockout_time']);

// all session data clear
$_SESSION = array();

// session cookie delete
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// session destroy
session_destroy();

// user-related cookies delete
$cookies = ['user_id', 'user_name', 'user_email', 'user_role', 'remember_token'];

foreach ($cookies as $cookie){
    if (isset($_COOKIE[$cookie])){
        // all cookie parameters set to delete
        setcookie($cookie, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        unset($_COOKIE[$cookie]);
    }
}

// redirect to login page
header("Location: login.php");
exit();
?>
