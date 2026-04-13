<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}

// CSRF token generation (after session start)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if(isset($_POST['register'])){
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    include_once '../config/database.php';

    // data sanitize function
    function sanitize_input($data) {
        if(empty($data)) return '';
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    // form data sanitize and assign
    $firstName = sanitize_input($_POST['firstName']);
    $lastName = sanitize_input($_POST['lastName']);
    $name = $firstName . ' ' . $lastName;
    $email = filter_var(sanitize_input($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = sanitize_input($_POST['phone']);
    $userRole = sanitize_input($_POST['userRole']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $terms = isset($_POST['terms']) ? 1 : 0;
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    // validation checks
    $errors = [];
    
    // email validation
    if (!$email) {
        $errors[] = "Invalid email address";
    }
    
    // password match check
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // password length check
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // term and conditions check
    if (!$terms) {
        $errors[] = "You must accept the terms and conditions";
    }
    
    // Number validation
    if (!preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    if (empty($errors)) {
        // email excess check
        $checkEmail = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $result = $checkEmail->get_result();
    
        if ($result->num_rows > 0) {
            $error = "
                <div class='alert alert-danger alert-dismissible fade show' role='alert'>
                    Email already exists. Please use a different email.
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
            ";
        } else {
            // transaction start
            $conn->begin_transaction();
            
            try {
                // password hashing
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
                // users data insert
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, full_name, email, phone, role, password, terms_accepted) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssi", $firstName, $lastName, $name, $email, $phone, $userRole, $hashedPassword, $terms);
                $stmt->execute();        
                $stmt->close();
                
                // check newsletter subscription
                if ($newsletter) {
                    $stmt = $conn->prepare("INSERT INTO subscribers (email) VALUES (?)");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // commit when everything is fine
                $conn->commit();
                
                $conn->close();
                $_SESSION['success'] = "Registration successful. Please login.";
                
                // refresh CSRF token
                unset($_SESSION['csrf_token']);
                
                // redirect to login page
                header("Location: login.php");
                exit();
                
            } catch (Exception $e) {
                // any problem, rollback the transaction
                $conn->rollback();
                $error = "
                    <div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        Registration failed. Please try again.
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>
                ";
            }
        }
    } else {
        //all errors display
        $errorHtml = "";
        foreach ($errors as $err) {
            $errorHtml .= "
                <div class='alert alert-danger alert-dismissible fade show' role='alert'>
                    $err
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
            ";
        }
        $error = $errorHtml;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <title>Register - MarketPlace</title>
    
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
        
        /* .auth-container {
            background: linear-gradient(135deg, rgba(10, 10, 10, 0.95), rgba(26, 26, 26, 0.95));
        } */
        
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
        
        .form-select {
            background-color: #111;
            border-color: #333;
            color: #fff;
        }
        
        .form-select:focus {
            background-color: #111;
            border-color: #c9a74d;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(201, 167, 77, 0.25);
        }
        
        .input-group-text {
            background-color: #333;
            border-color: #333;
            color: #c9a74d;
        }
        
        .btn-primary, .btn-primary1 {
            background-color: #c9a74d;
            border-color: #c9a74d;
            color: #000;
        }
        
        .btn-primary, .btn-primary1:hover {
             background-color: #b8942e; 
            border-color: #554008ff;
             color: #000; 
        }
        
        
        .btn-outline-secondary {
            border-color: #333;
            color: #aaa;
        }
        
        .btn-outline-secondary:hover {
            background-color: #333;
            border-color: #c9a74d;
            color: #c9a74d;
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
        
        .form-check-input:checked {
            background-color: #c9a74d;
            border-color: #c9a74d;
        }
        
        .form-check-input:focus {
            border-color: #c9a74d;
            box-shadow: 0 0 0 0.25rem rgba(201, 167, 77, 0.25);
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
        
        .form-text small {
            color: #777;
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
                <div class="col-lg-6 col-md-8">
                    <div class="auth-card card p-4">
                        <div class="card-body">
                            <!-- Logo -->
                            <div class="text-center mb-4">
                                <h2 class="text-primary fw-bold">
                                    <i class="fas fa-store me-2"></i>MarketPlace
                                </h2>
                                <p class="text-muted">Create your account and start shopping</p>
                            </div>
                            <?php if (isset($error)) echo $error; ?>

                            <!-- Registration Form -->
                                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>">
                                    <!-- CSRF Token Hidden Field add -->
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">                                        
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" class="form-control" id="firstName" name="firstName" placeholder="First name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">                                        
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Last name" required>
                                        </div>
                                    </div>
                                </div>

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
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter your phone number" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    
                                    <select class="form-select" id="userRole" name="userRole" required>
                                        <option value="">Select account type</option>
                                        <option value="customer" selected>Customer</option>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Create password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', 'toggleIcon1')">
                                                <i class="fas fa-eye" id="toggleIcon1"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Password must be at least 8 characters long
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword', 'toggleIcon2')">
                                                <i class="fas fa-eye" id="toggleIcon2"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">
                                            <span>I agree to the</span><a href="#" class="text-decoration-none">Terms of Service</a> 
                                           <span> and </span><a href="#" class="text-decoration-none">Privacy Policy</a>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter">
                                        <label class="form-check-label" for="newsletter">
                                            <span>Subscribe to our newsletter for updates and offers</span>
                                        </label>
                                    </div>
                                </div>

                                <button name="register" type="submit" class="btn btn-primary1 w-100 mb-3">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </form>

                            <!-- Social Registration -->
                            <div class="text-center mb-3">
                                <p class="text-muted">Or register with</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="btn social-btn flex-fill">
                                        <i class="fab fa-google text-danger me-2"></i>Google
                                    </button>
                                    <button class="btn social-btn flex-fill">
                                        <i class="fab fa-facebook text-primary me-2"></i>Facebook
                                    </button>
                                </div>
                            </div>

                            <!-- Login Link -->
                            <div class="text-center">
                                <p class="mb-0"><span>Already have an account?</span> 
                                    <a href="login.php" class="text-decoration-none fw-semibold">Sign in here</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Password validation
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
