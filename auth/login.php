<?php
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
session_start();

// CSRF token generation (after session start)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include_once '../config/database.php';

// check for existing session or remember me cookies
if (!isset($_SESSION['user_logged_in']) && isset($_COOKIE['remember_token'])) {
    // Secure Remember Me Check
    $token = $_COOKIE['remember_token'];
    
    // Check token in database
    $stmt = $conn->prepare("SELECT u.id, u.full_name, u.email, u.role, u.branch_id 
                          FROM user_sessions s 
                          JOIN users u ON s.user_id = u.id 
                          WHERE s.session_token = ? AND s.expires_at > NOW() AND u.status = 'active'");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_branch_id'] = $user['branch_id'];
        
        // redirect based on role
        if ($_SESSION['user_role'] == 'admin') {
            header("Location: ../admin/admin.php");
        } elseif ($_SESSION['user_role'] == 'manager') {
            header("Location: ../manager/index.php");
        } else {
            header("Location: ../index.php");
        }
        exit();
    } else {
        // Invalid token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

if(isset($_POST['login'])){
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if(empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        // Simple select without status check in query to avoid confusion
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Password verification
            if (password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $error = "Your account is currently " . $user['status'] . ". Please contact support.";
                } else {
                    // Successful login
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_branch_id'] = $user['branch_id'];
                    
                    // Reset login attempts
                    $resetStmt = $conn->prepare("UPDATE users SET login_attempts = 0, account_locked = 0 WHERE id = ?");
                    $resetStmt->bind_param("i", $user['id']);
                    $resetStmt->execute();
                    
                    // Role based redirection
                    if ($user['role'] == 'admin') {
                        header("Location: ../admin/admin.php");
                    } elseif ($user['role'] == 'manager') {
                        header("Location: ../manager/index.php");
                    } else {
                        header("Location: ../index.php");
                    }
                    exit();
                }
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <title>Login - Feliciano</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Color customization to match website theme */
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.1)), url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            color: #fff;
        }
        
        .auth-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
        }
        
        .text-primary {
            color: #c9a74d !important;
        }
        
        .text-muted {
            color: #aaa !important;
        }
        
        .form-control {
            background-color: #111;
            border-color: #333;
            color: #fff;
        }
        
        .form-control:focus {
            background-color: #111;
            border-color: #c9a74d;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(201, 167, 77, 0.25);
        }
        
        .form-control::placeholder {
            color: #777;
        }
        
        .input-group-text {
            background-color: #333;
            border-color: #333;
            color: #c9a74d;
        }
        
        .btn-primary {
            background-color: #c9a74d;
            border-color: #c9a74d;
            color: #000;
        }
        
        .btn-primary:hover {
            background-color: #b8942e;
            border-color: #b8942e;
            color: #000;
        }
        
        .social-btn {
            background-color: #333;
            border-color: #444;
            color: #fff;
        }
        
        .social-btn:hover {
            background-color: #444;
            border-color: #c9a74d;
        }
        
        .password-toggle {
            border-color: #333;
            background-color: #111;
            color: #777;
        }
        
        .password-toggle:hover {
            border-color: #c9a74d !important;
            background-color: #333 !important;
            color: #c9a74d !important;
        }
        
        .password-toggle:focus {
            box-shadow: none;
            border-color: #c9a74d;
        }
        
        .text-decoration-none {
            color: #c9a74d !important;
        }
        
        .text-decoration-none:hover {
            color: #b8942e !important;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
            color: #ff6b6b;
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        span {
            color: #fff !important;
        }
    </style>
</head>
<body>
    <div class="auth-container d-flex align-items-center justify-content-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="auth-card card p-4">
                        <div class="card-body">
                            <!-- Logo -->
                            <div class="text-center mb-4">
                                <h2 class="text-primary fw-bold">
                                    <i class="fas fa-store me-2"></i>Feliciano<span>.</span>
                                </h2>
                                <p class="text-muted">Welcome back! Please sign in to your account</p>
                            </div>
                            
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= $error ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <!-- Login Form -->
                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                        <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                        <label class="form-check-label" for="remember">
                                            Remember me
                                        </label>
                                    </div>
                                    <a href="#" class="text-decoration-none">Forgot password?</a>
                                </div>

                                <button name="login" type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>
                            </form>

                            <!-- Social Login -->
                            <div class="text-center mb-3">
                                <p class="text-muted">Or sign in with</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="btn social-btn flex-fill">
                                        <i class="fab fa-google text-danger me-2"></i>Google
                                    </button>
                                    <button class="btn social-btn flex-fill">
                                        <i class="fab fa-facebook text-primary me-2"></i>Facebook
                                    </button>
                                </div>
                            </div>

                            <!-- Register Link -->
                            <div class="text-center">
                                <p class="mb-0">Don't have an account? 
                                    <a href="register.php" class="text-decoration-none fw-semibold">Create one here</a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Back to Home -->
                    <div class="text-center mt-3">
                        <a href="../index.php" class="text-white text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password Visibility Toggle
        document.getElementById('togglePassword').addEventListener('click', function (e) {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // toggle the eye / eye-slash icon
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Set session storage when login is successful
        document.querySelector('form').addEventListener('submit', function() {
            sessionStorage.setItem('userLoggedIn', 'true');
        });
    </script>
</body>
</html>
