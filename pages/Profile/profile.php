<?php
session_start();
require '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Update Password if provided
    $password_query = "";
    $types = "ss"; // string types for name, phone
    $params = [$full_name, $phone];
    
    if (!empty($_POST['new_password'])) {
        $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $password_query = ", password = ?";
        $types .= "s";
        $params[] = $password;
    }
    
    $avatar_query = "";
    $upload_dir = '../../assets/images/profiles/';
    if (isset($_FILES['profile_avatar']) && $_FILES['profile_avatar']['error'] === UPLOAD_ERR_OK) {
        $avatar_name = time() . '_avatar_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['profile_avatar']['name']));
        if (move_uploaded_file($_FILES['profile_avatar']['tmp_name'], $upload_dir . $avatar_name)) {
            $avatar_query = ", profile_avatar = ?";
            $types .= "s";
            $params[] = $avatar_name;
        }
    }

    $hero_query = "";
    if (isset($_FILES['profile_hero']) && $_FILES['profile_hero']['error'] === UPLOAD_ERR_OK) {
        $hero_name = time() . '_hero_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['profile_hero']['name']));
        if (move_uploaded_file($_FILES['profile_hero']['tmp_name'], $upload_dir . $hero_name)) {
            $hero_query = ", profile_hero = ?";
            $types .= "s";
            $params[] = $hero_name;
        }
    }
    
    // Add user_id to params
    $params[] = $user_id;
    $types .= "i";

    $sql = "UPDATE users SET full_name = ?, phone = ? $password_query $avatar_query $hero_query WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $_SESSION['user_name'] = $full_name; // Update session name
        
        // SYNC WITH CUSTOMERS TABLE
        $check_cust = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
        $check_cust->bind_param("i", $user_id);
        $check_cust->execute();
        $cust_result = $check_cust->get_result();

        if ($cust_result->num_rows > 0) {
            $update_cust = $conn->prepare("UPDATE customers SET full_name = ?, phone = ?, address = ? WHERE user_id = ?");
            $update_cust->bind_param("sssi", $full_name, $phone, $address, $user_id);
            $update_cust->execute();
        } else {
            $cust_id_str = 'CUST-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $email = $_SESSION['user_email'] ?? $user['email'];
            if(empty($email)) {
                 $fetch_u = $conn->prepare("SELECT email FROM users WHERE id = ?");
                 $fetch_u->bind_param("i", $user_id);
                 $fetch_u->execute();
                 $email = $fetch_u->get_result()->fetch_assoc()['email'];
            }
            $insert_cust = $conn->prepare("INSERT INTO customers (user_id, customer_id, full_name, email, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_cust->bind_param("isssss", $user_id, $cust_id_str, $full_name, $email, $phone, $address);
            $insert_cust->execute();
        }

        $success_msg = "Profile updated successfully!";
    } else {
        $error_msg = "Error updating profile.";
    }
}

// Fetch User Data
$stmt = $conn->prepare("SELECT full_name, email, phone, role, created_at, profile_avatar, profile_hero FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch Customer ID and Address for the user
$customerId = 0;
$customer_address = '';
$custStmt = $conn->prepare("SELECT id, address FROM customers WHERE user_id = ?");
$custStmt->bind_param("i", $user_id);
$custStmt->execute();
$custRes = $custStmt->get_result();
if ($custRow = $custRes->fetch_assoc()) {
    $customerId = $custRow['id'];
    $customer_address = $custRow['address'];
}
$custStmt->close();

$customer_email = $user['email'];

// Total Orders & Spent
$stats_sql = "SELECT COUNT(*) as total_orders, SUM(total_amount) as total_spent FROM orders WHERE (customer_id = ? OR customer_email = ?) AND status != 'cancelled'";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("is", $customerId, $customer_email);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$total_orders = $stats['total_orders'] ?? 0;
$total_spent = $stats['total_spent'] ?? 0.00;

// Running Orders
$running_sql = "SELECT * FROM orders WHERE (customer_id = ? OR customer_email = ?) AND status IN ('pending', 'preparing', 'ready') ORDER BY created_at DESC";
$stmt = $conn->prepare($running_sql);
$stmt->bind_param("is", $customerId, $customer_email);
$stmt->execute();
$running_orders = $stmt->get_result();

// Order History
$history_sql = "SELECT * FROM orders WHERE (customer_id = ? OR customer_email = ?) AND status IN ('completed', 'cancelled') ORDER BY created_at DESC";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("is", $customerId, $customer_email);
$stmt->execute();
$order_history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Feliciano Restaurant</title>
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Modern Profile Design */
        :root {
            --primary-gold: #c9a74d;
            --gold-hover: #e0bb55;
            --dark-surface: #1e1e1e;
            --darking-surface: #141414;
            --page-bg: #0b0b0b;
        }

        body {
            /* Premium Dark Gradient Background */
            background: radial-gradient(circle at top right, #1f1f1f, #0b0b0b);
            color: #f4f4f4;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }

        /* Hero Profile Section */
        .profile-hero {
            position: relative;
            background: linear-gradient(to bottom, rgba(0,0,0,0), var(--page-bg)), url('https://images.unsplash.com/photo-1559339352-11d035aa65de?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            margin-top: 80px; /* Start below desktop header */
            height: 370px; /* Adjusted height to keep visual proportions */
            display: flex;
            align-items: flex-end;
            padding-bottom: 50px;
            margin-bottom: 50px;
        }

        .profile-info {
            background: rgba(20, 20, 20, 0.85);
            backdrop-filter: blur(10px);
            padding: 30px 50px;
            border-radius: 15px;
            border-left: 5px solid var(--primary-gold);
            display: flex;
            align-items: center;
            gap: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            max-width: 800px;
            margin: 0 auto;
            /* Animation */
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .profile-info:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            border-left: 5px solid #fff; /* Slight highlight change */
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: var(--primary-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #000;
            font-weight: bold;
        }

        .profile-details h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            margin: 0;
            color: var(--primary-gold);
        }

        .profile-details p {
            margin: 5px 0 0;
            color: #ccc;
            font-size: 0.95rem;
        }

        /* Tab Navigation */
        .profile-nav {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
            border-bottom: 1px solid #333;
            padding-bottom: 1px;
        }

        .nav-item {
            padding: 15px 30px;
            cursor: pointer;
            color: #888;
            font-weight: 600;
            position: relative;
            transition: all 0.3s;
            font-size: 1.1rem;
            background: transparent;
            border: none;
        }

        .nav-item:hover {
            color: var(--primary-gold);
        }

        .nav-item.active {
            color: var(--primary-gold);
        }

        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-gold);
            box-shadow: 0 -2px 10px rgba(201, 167, 77, 0.5);
        }

        /* Tab Content */
        .tab-pane {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(145deg, #1a1a1a, #141414);
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            border-color: var(--primary-gold);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(201, 167, 77, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-gold);
        }

        .stat-info h3 {
            font-size: 2rem;
            margin: 0;
            font-weight: 700;
        }

        .stat-info p {
            margin: 0;
            color: #888;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Tables & Lists */
        .premium-card {
            background: var(--dark-surface);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid #333;
            margin-bottom: 30px;
        }

        .card-header-styled {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #333;
        }

        .card-header-styled h2 {
            margin: 0;
            color: var(--primary-gold);
            font-family: 'Playfair Display', serif;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .custom-table th {
            color: #888;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            padding: 15px;
            text-align: left;
        }

        .custom-table td {
            background: #1a1a1a;
            padding: 20px 15px;
            border-top: 1px solid #222;
            border-bottom: 1px solid #222;
        }

        .custom-table tr td:first-child {
            border-left: 1px solid #222;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            font-weight: bold;
            color: var(--primary-gold);
        }

        .custom-table tr td:last-child {
            border-right: 1px solid #222;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .custom-table tr:hover td {
            background: #222;
            border-color: var(--primary-gold);
        }

        /* Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid #ffc107; }
        .status-preparing { background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid #17a2b8; }
        .status-ready { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .status-completed { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-cancelled { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        .modern-input {
            width: 100%;
            background: #141414;
            border: 1px solid #333;
            padding: 15px;
            border-radius: 8px;
            color: #fff;
            transition: all 0.3s;
        }

        .modern-input:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 2px rgba(201, 167, 77, 0.2);
            outline: none;
        }

        .modern-label {
            display: block;
            margin-bottom: 8px;
            color: #aaa;
            font-size: 0.9rem;
        }

        .save-btn {
            background: var(--primary-gold);
            color: #000;
            padding: 15px 40px;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }

        .save-btn:hover {
            background: var(--gold-hover);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(201, 167, 77, 0.3);
        }

        @media (max-width: 768px) {
            .profile-hero { height: auto; margin-top: 140px; padding: 40px 0 30px; }
            .profile-info { 
                flex-direction: column; 
                text-align: center; 
                margin: 0 15px;
                padding: 25px 20px;
                gap: 15px;
            }
            .profile-avatar { width: 100px; height: 100px; font-size: 3rem; }
            .profile-details h1 { font-size: 1.8rem; }
            .profile-details p { 
                font-size: 0.85rem; 
                display: flex; 
                flex-direction: column; 
                gap: 5px; 
            }
            
            .profile-nav { 
                overflow-x: auto; 
                justify-content: flex-start; 
                padding-bottom: 5px; 
                white-space: nowrap; 
                margin-bottom: 25px;
                scrollbar-width: none; /* Firefox */
            }
            .profile-nav::-webkit-scrollbar { display: none; } /* Chrome */
            
            .nav-item { padding: 10px 15px; font-size: 0.95rem; }
            
            .stats-grid { grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px; }
            .stat-card { padding: 20px; }
            .stat-icon { width: 50px; height: 50px; font-size: 1.2rem; }
            .stat-info h3 { font-size: 1.6rem; }
            
            .premium-card { padding: 20px 15px; margin-bottom: 20px; }
            .card-header-styled h2 { font-size: 1.3rem; }
            
            .table-container { 
                overflow-x: auto; 
                -webkit-overflow-scrolling: touch; 
                margin: 0 -15px; 
                padding: 0 15px; 
            }
            .custom-table th, .custom-table td { 
                padding: 12px 10px; 
                font-size: 0.85rem; 
                white-space: nowrap; 
            }
            
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .save-btn { width: 100%; padding: 15px; font-size: 1rem; }
            .alert-success { font-size: 0.9rem; padding: 10px; }
        }
    </style>
</head>
<body>
    <script>
        const currentUserEmail = "<?php echo isset($_SESSION['user_email']) ? $_SESSION['user_email'] : ''; ?>";
    </script>

    <!-- Navigation -->
    <header>
        <div class="container header-container">
            <a href="../../index.php" class="logo">Feliciano<span style="color: var(--primary-gold);">.</span></a>
            <nav>
                <ul class="nav-links">
                    <li><a href="../../index.php">Home</a></li>
                    <li><a href="../menu.php">Menu</a></li>
                    <li><a href="../about.php">About</a></li>
                    <li><a href="../contact.php">Contact</a></li>
                </ul>
            </nav>
            <div class="header-actions justify-content-end">
                <button id="orderBtn" class="order-badge-btn" style="margin-right: 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor"
                        class="bi bi-card-checklist" viewBox="0 0 16 16">
                        <path
                            d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z" />
                        <path
                            d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0M7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0" />
                    </svg>
                    <span id="orderCount" class="order-count-badge">0</span>
                </button>
                <a href="../contact.php" class="reservation-btn">Reserve a Table</a>

                <?php if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                    <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <a href="../../admin/admin.php" class="auth-btn admin-btn" style="margin-right: 15px;"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <?php elseif(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager'): ?>
                        <a href="../../manager/index.php" class="auth-btn admin-btn" style="margin-right: 15px;"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <?php endif; ?>
                    <a href="../../auth/logout.php" class="auth-btn logout-btn" style="margin-left: 0;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="../../auth/login.php" class="auth-btn login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <?php $hero_bg = !empty($user['profile_hero']) ? '../../assets/images/profiles/' . htmlspecialchars($user['profile_hero']) : 'https://images.unsplash.com/photo-1559339352-11d035aa65de?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80'; ?>
    <div class="profile-hero" style="background: linear-gradient(to bottom, rgba(0,0,0,0), var(--page-bg)), url('<?php echo $hero_bg; ?>'); background-size: cover; background-position: center;">
        <div class="container">
            <div class="profile-info">
                <div class="profile-avatar" style="overflow: hidden;">
                    <?php if(!empty($user['profile_avatar'])): ?>
                        <img src="../../assets/images/profiles/<?php echo htmlspecialchars($user['profile_avatar']); ?>" alt="Profile Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-details">
                    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?> &nbsp;|&nbsp; <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container" style="padding-bottom: 80px;">
        
        <?php if($success_msg): ?>
            <div class="alert-success" style="text-align: center; margin-bottom: 30px;"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert-success" style="background: rgba(220,53,69,0.2); color: #dc3545; border-color: #dc3545; text-align: center; margin-bottom: 30px;"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="profile-nav">
            <button class="nav-item active" onclick="switchTab('dashboard')">Dashboard</button>
            <button class="nav-item" onclick="switchTab('orders')">Order History</button>
            <button class="nav-item" onclick="switchTab('settings')">Settings</button>
        </div>

        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-pane active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $total_orders; ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($total_spent); ?> <small style="font-size: 1rem; color: #888;">TK</small></h3>
                        <p>Total Spent</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $running_orders->num_rows; ?></h3>
                        <p>Active Orders</p>
                    </div>
                </div>
            </div>

            <div class="premium-card">
                <div class="card-header-styled">
                    <h2>Active Orders</h2>
                    <button onclick="switchTab('orders')" style="background: none; border: none; color: var(--primary-gold); cursor: pointer;">View All <i class="fas fa-arrow-right"></i></button>
                </div>
                <div class="table-container">
                    <?php if($running_orders->num_rows > 0): ?>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date Ordered</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $running_orders->data_seek(0); // Reset pointer ?>
                                <?php while($order = $running_orders->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></td>
                                    <td>TK <?php echo number_format($order['total_amount']); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-utensils" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>No active orders being prepared right now.</p>
                            <a href="../menu.php" class="save-btn" style="padding: 10px 20px; font-size: 0.9rem; text-decoration: none; display: inline-block; margin-top: 15px;">Order Now</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Orders Tab -->
        <div id="orders" class="tab-pane">
            <div class="premium-card">
                <div class="card-header-styled">
                    <h2>Order History</h2>
                </div>
                <div class="table-container">
                    <?php if($order_history->num_rows > 0): ?>
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Review</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($order = $order_history->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td><small style="color: #888;">Order #<?php echo $order['id']; ?></small></td>
                                <td>-</td>
                                <td>TK <?php echo number_format($order['total_amount']); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                     <?php else: ?>
                        <div style="text-align: center; padding: 50px; color: #666;">
                            <p>You haven't placed any orders yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-pane">
            <div class="premium-card">
                <div class="card-header-styled">
                    <h2>Account Settings</h2>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="modern-label">Full Name</label>
                            <input type="text" name="full_name" class="modern-input" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="modern-label">Phone Number</label>
                            <input type="tel" name="phone" class="modern-input" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <div class="form-group form-full">
                            <label class="modern-label">Email Address</label>
                            <input type="email" class="modern-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                        </div>
                        <div class="form-group form-full">
                            <label class="modern-label">Default Delivery Address</label>
                            <textarea name="address" class="modern-input" rows="3" placeholder="Enter your full street address"><?php echo htmlspecialchars($customer_address ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="modern-label">Profile Avatar</label>
                            <input type="file" name="profile_avatar" class="modern-input" accept="image/*" style="padding: 12px; background: #1a1a1a;">
                        </div>
                        <div class="form-group">
                            <label class="modern-label">Profile Hero Photo</label>
                            <input type="file" name="profile_hero" class="modern-input" accept="image/*" style="padding: 12px; background: #1a1a1a;">
                        </div>
                        <div class="form-group form-full" style="margin-top: 20px; border-top: 1px solid #333; padding-top: 20px;">
                            <h3 style="color: #fff; font-size: 1.2rem; margin-bottom: 20px;">Change Password</h3>
                        </div>
                        <div class="form-group">
                            <label class="modern-label">New Password</label>
                            <input type="password" name="new_password" class="modern-input" placeholder="Leave blank to keep current">
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <button type="submit" name="update_profile" class="save-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="text-center" style="padding: 20px 0; border-top: 1px solid #333;">
                <p>&copy; 2023 Feliciano Restaurant. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Order Card Modal -->
    <div id="orderModal" class="order-modal">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Your Order</h2>
                <button class="close-btn" id="closeOrderBtn">&times;</button>
            </div>
            <div class="order-modal-body" id="orderList">
                <p class="empty-order">No items in your order yet</p>
            </div>
            <div class="order-modal-footer">
                <div class="order-total">
                    <strong>Total: </strong>
                    <span id="orderTotal">TK 0</span>
                </div>
                <button class="btn btn-primary" id="checkoutBtn">Proceed to Checkout</button>
                <button class="btn btn-secondary" id="clearOrderBtn">Clear Order</button>
            </div>
        </div>
    </div>

    <!-- Online Order Modal -->
    <div id="onlineOrderModal" class="order-modal">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Online Order</h2>
                <button class="close-btn" id="closeOnlineOrderBtn">&times;</button>
            </div>
            <div class="order-modal-body">
                <form id="onlineOrderForm">
                    <div class="form-group">
                        <label for="customerName">Full Name</label>
                        <input type="text" id="customerName" name="customerName" required>
                    </div>
                    <div class="form-group">
                        <label for="customerPhone">Phone Number</label>
                        <input type="tel" id="customerPhone" name="customerPhone" required>
                    </div>
                    <div class="form-group">
                        <label for="customerEmail">Email</label>
                        <input type="email" id="customerEmail" name="customerEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="deliveryAddress">Delivery Address</label>
                        <textarea id="deliveryAddress" name="deliveryAddress" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="deliveryTime">Preferred Delivery Time</label>
                        <input type="datetime-local" id="deliveryTime" name="deliveryTime">
                    </div>
                    <div class="form-group">
                        <label for="specialInstructions">Special Instructions</label>
                        <textarea id="specialInstructions" name="specialInstructions" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="order-modal-footer">
                <div class="order-total">
                    <strong>Total: </strong>
                    <span id="onlineOrderTotal">TK 0</span>
                </div>
                <button class="btn btn-primary" id="confirmOnlineOrderBtn">Confirm Order</button>
                <button class="btn btn-secondary" id="cancelOnlineOrderBtn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Offline Order Modal -->
    <div id="offlineOrderModal" class="order-modal">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Offline Order</h2>
                <button class="close-btn" id="closeOfflineOrderBtn">&times;</button>
            </div>
            <div class="order-modal-body">
                <form id="offlineOrderForm">
                    <div class="form-group">
                        <label for="tableName">Table Number</label>
                        <input type="text" id="tableName" name="tableName" placeholder="Enter table number" required>
                    </div>
                    <div class="form-group">
                        <label for="personCount">Number of People</label>
                        <input type="number" id="personCount" name="personCount" min="1" max="20" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="customerNameOffline">Full Name</label>
                        <input type="text" id="customerNameOffline" name="customerNameOffline" required>
                    </div>
                    <div class="form-group">
                        <label for="customerPhoneOffline">Phone Number</label>
                        <input type="tel" id="customerPhoneOffline" name="customerPhoneOffline" required>
                    </div>
                    <div class="form-group">
                         <label for="customerEmailOffline">Email</label>
                         <input type="email" id="customerEmailOffline" name="customerEmailOffline" placeholder="Optional for guests" value="<?php echo isset($_SESSION['user_email']) ? $_SESSION['user_email'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="specialInstructionsOffline">Special Instructions</label>
                        <textarea id="specialInstructionsOffline" name="specialInstructionsOffline" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="order-modal-footer">
                <div class="order-total">
                    <strong>Total: </strong>
                    <span id="offlineOrderTotal">TK 0</span>
                </div>
                <button class="btn btn-primary" id="confirmOfflineOrderBtn">Confirm Order</button>
                <button class="btn btn-secondary" id="cancelOfflineOrderBtn">Cancel</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
    <script>
        function switchTab(tabId) {
            // Remove active class from all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(btn => btn.classList.remove('active')); // Fixed selector

            // Add active class to clicked tab and button
            document.getElementById(tabId).classList.add('active');
            
            // Find the button that called this function - simple way to highlight
            const buttons = document.querySelectorAll('.nav-item');
            if(tabId === 'dashboard') buttons[0].classList.add('active');
            if(tabId === 'orders') buttons[1].classList.add('active');
            if(tabId === 'settings') buttons[2].classList.add('active');
        }
    </script>
</body>
</html>
