<?php
session_start();
require '../config/database.php';

header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// --- HELPER: IMAGE UPLOAD & RESIZE ---
function uploadAndResizeImage($file, $target_dir = '../assets/images/menu/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('menu_') . '.jpg';
    $target_file = $target_dir . $new_filename;

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // fallback if GD is not loaded
    if (!extension_loaded('gd')) {
        $final_filename = uniqid('menu_') . '.' . $file_extension;
        if (move_uploaded_file($file['tmp_name'], $target_dir . $final_filename)) {
            return 'assets/images/menu/' . $final_filename;
        }
        return null;
    }

    switch ($file_extension) {
        case 'jpeg': case 'jpg': $src = imagecreatefromjpeg($file['tmp_name']); break;
        case 'png': $src = imagecreatefrompng($file['tmp_name']); break;
        case 'webp': $src = imagecreatefromwebp($file['tmp_name']); break;
        default: return null;
    }

    if (!$src) return null;

    $w = imagesx($src); $h = imagesy($src);
    $tw = 800; $th = 600; // Standard size

    $dst = imagecreatetruecolor($tw, $th);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagejpeg($dst, $target_file, 85);

    imagedestroy($src);
    imagedestroy($dst);

    return 'assets/images/menu/' . $new_filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // --- DASHBOARD STATS ---
    if ($action === 'get_stats') {
        $stats = [];
        $branch_id = $_GET['branch_id'] ?? 'all';
        $where_clause = ($branch_id !== 'all') ? " WHERE branch_id = " . intval($branch_id) : "";
        
        // Total Orders
        $res = $conn->query("SELECT COUNT(*) as cnt FROM orders" . $where_clause);
        $stats['total_orders'] = $res->fetch_assoc()['cnt'];

        // Total Revenue (Completed orders only)
        $rev_where = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";
        $res = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'" . $rev_where);
        $stats['total_revenue'] = $res->fetch_assoc()['total'] ?? 0;

        // Menu Items (Usually global, but keeping it if they want branch specific menu later)
        $res = $conn->query("SELECT COUNT(*) as cnt FROM menu_items");
        $stats['menu_items'] = $res->fetch_assoc()['cnt'];

        // Customers
        $res = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'customer'");
        $stats['total_customers'] = $res->fetch_assoc()['cnt'];

        echo json_encode(['status' => 'success', 'data' => $stats]);
    }

    // --- GET ORDERS ---
    elseif ($action === 'get_orders') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $where_clause = ($branch_id !== 'all') ? " WHERE branch_id = " . intval($branch_id) : "";
        $sql = "SELECT * FROM orders" . $where_clause . " ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $orders = [];
        
        while($row = $result->fetch_assoc()) {
            $order_id = $row['id'];
            // Fetch items for this order
            $item_sql = "SELECT * FROM order_items WHERE order_id = $order_id";
            $item_res = $conn->query($item_sql);
            $items = [];
            while($item = $item_res->fetch_assoc()) {
                $items[] = [
                    'name' => $item['menu_item_name'],
                    'qty' => $item['quantity'],
                    'price' => $item['unit_price']
                ];
            }
            
            $orders[] = [
                'id' => $row['order_id'], // Display ID (e.g., ORD-123)
                'db_id' => $row['id'],    // Database ID
                'customer' => $row['customer_email'],
                'customer_name' => $row['customer_name'],
                'customer_phone' => $row['customer_phone'],
                'order_type' => $row['order_type'],
                'date' => $row['created_at'],
                'total' => $row['total_amount'],
                'status' => $row['status'],
                'items' => $items
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $orders]);
    }

    // --- GET USERS ---
    elseif ($action === 'get_users') {
        $sql = "SELECT id, first_name, last_name, full_name, email, phone, role, status, branch_id, created_at FROM users ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $users = [];
        while($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $users]);
    }

    // --- GET CUSTOMERS ---
    elseif ($action === 'get_customers') {
        $sql = "SELECT 
                    u.id, 
                    u.full_name, 
                    u.email, 
                    u.phone, 
                    COALESCE(c.customer_id, CONCAT('CUST-', u.id)) as customer_id,
                    COALESCE(c.total_orders, 0) as total_orders, 
                    COALESCE(c.total_spent, '0.00') as total_spent, 
                    c.last_order_date 
                FROM users u 
                LEFT JOIN customers c ON LOWER(TRIM(u.email)) = LOWER(TRIM(c.email)) 
                WHERE u.role = 'customer' 
                ORDER BY total_orders DESC";
        $result = $conn->query($sql);
        $customers = [];
        while($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $customers]);
    }

    // --- GET MENU ---
    elseif ($action === 'get_menu') {
        $sql = "SELECT * FROM menu_items ORDER BY id DESC";
        $result = $conn->query($sql);
        $menu = [];
        while($row = $result->fetch_assoc()) {
            $menu[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $menu]);
    }

    // --- GET RESERVATIONS ---
    elseif ($action === 'get_reservations') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $where_clause = ($branch_id !== 'all') ? " WHERE branch_id = " . intval($branch_id) : "";
        $sql = "SELECT * FROM reservations" . $where_clause . " ORDER BY reservation_date DESC, reservation_time DESC";
        $result = $conn->query($sql);
        $reservations = [];
        while($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $reservations]);
    }

    // --- GET ANALYTICS ---
    elseif ($action === 'get_popular_items') {
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND o.branch_id = " . intval($branch_id) : "";

        if ($start && $end) {
            $sql = "SELECT mi.name, COUNT(oi.id) as order_count 
                    FROM menu_items mi
                    JOIN order_items oi ON mi.id = oi.menu_item_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND o.status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY mi.id 
                    ORDER BY order_count DESC LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start, $end);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            // If branch filter is active, we can't use the simple view if it doesn't have branch_id
            // Let's fallback to daily logic or similar
            $sql = "SELECT mi.name, COUNT(oi.id) as order_count 
                    FROM menu_items mi
                    JOIN order_items oi ON mi.id = oi.menu_item_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY mi.id 
                    ORDER BY order_count DESC LIMIT 5";
            $result = $conn->query($sql);
        }
        $items = [];
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    elseif ($action === 'get_daily_sales') {
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";

        if ($start && $end) {
            $sql = "SELECT DATE(created_at) as sale_date, COUNT(*) as total_orders, SUM(total_amount) as total_revenue
                    FROM orders
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    AND status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY DATE(created_at)
                    ORDER BY sale_date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start, $end);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql = "SELECT DATE(created_at) as sale_date, COUNT(*) as total_orders, SUM(total_amount) as total_revenue
                    FROM orders
                    WHERE status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY DATE(created_at)
                    ORDER BY sale_date DESC LIMIT 7";
            $result = $conn->query($sql);
        }
        $sales = [];
        while($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $sales]);
    }

    elseif ($action === 'get_analytics_summary') {
        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $end = $_GET['end'] ?? date('Y-m-d');
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";

        // Current Period Stats
        $sql = "SELECT 
                COUNT(*) as total_orders, 
                IFNULL(SUM(total_amount), 0) as total_revenue,
                IFNULL(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND status NOT IN ('cancelled') " . $branch_filter;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $start, $end);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        
        // Previous Period Stats for Comparison
        $diff = strtotime($end) - strtotime($start);
        $prev_end = date('Y-m-d', strtotime($start) - 86400); // Day before current start
        $prev_start = date('Y-m-d', strtotime($prev_end) - $diff);
        
        $sql_prev = "SELECT 
                COUNT(*) as total_orders, 
                IFNULL(SUM(total_amount), 0) as total_revenue,
                IFNULL(AVG(total_amount), 0) as avg_order_value
                FROM orders 
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND status NOT IN ('cancelled') " . $branch_filter;
        $stmt_prev = $conn->prepare($sql_prev);
        $stmt_prev->bind_param("ss", $prev_start, $prev_end);
        $stmt_prev->execute();
        $summary['previous'] = $stmt_prev->get_result()->fetch_assoc();

        // Activity Patterns (Busiest Day & Peak Hour)
        $day_sql = "SELECT DAYNAME(created_at) as day_name, COUNT(*) as count 
                    FROM orders WHERE status != 'cancelled' 
                    " . $branch_filter . "
                    GROUP BY day_name ORDER BY count DESC LIMIT 1";
        $res_day = $conn->query($day_sql);
        $summary['busiest_day'] = ($res_day && $res_day->num_rows > 0) ? $res_day->fetch_assoc()['day_name'] : 'N/A';
        
        $hour_sql = "SELECT HOUR(created_at) as hour, COUNT(*) as count 
                     FROM orders WHERE status != 'cancelled' 
                     " . $branch_filter . "
                     GROUP BY hour ORDER BY count DESC LIMIT 1";
        $res_hour = $conn->query($hour_sql);
        $summary['peak_hour'] = ($res_hour && $res_hour->num_rows > 0) ? $res_hour->fetch_assoc()['hour'] : '0';

        // Top Category
        $cat_sql = "SELECT mi.category, COUNT(oi.id) as count 
                    FROM menu_items mi
                    JOIN order_items oi ON mi.id = oi.menu_item_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND o.status NOT IN ('cancelled')
                    " . $branch_filter . "
                    GROUP BY mi.category 
                    ORDER BY count DESC LIMIT 1";
        $cat_stmt = $conn->prepare($cat_sql);
        $cat_stmt->bind_param("ss", $start, $end);
        $cat_stmt->execute();
        $top_cat = $cat_stmt->get_result()->fetch_assoc();
        $summary['top_category'] = $top_cat ? $top_cat['category'] : 'N/A';

        echo json_encode(['status' => 'success', 'data' => $summary]);
    }

    elseif ($action === 'get_monthly_revenue') {
        $branch_id = $_GET['branch_id'] ?? 'all';
        $branch_filter = ($branch_id !== 'all') ? " AND branch_id = " . intval($branch_id) : "";
        
        $sql = "SELECT 
                MONTHNAME(created_at) as month, 
                YEAR(created_at) as year,
                SUM(total_amount) as revenue 
                FROM orders 
                WHERE status != 'cancelled' 
                " . $branch_filter . "
                GROUP BY YEAR(created_at), MONTH(created_at) 
                ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC 
                LIMIT 12";
        
        $result = $conn->query($sql);
        $monthly = [];
        
        if ($result) {
            while($row = $result->fetch_assoc()) {
                $monthly[] = [
                    'month' => $row['month'] ?? 'Unknown',
                    'year' => $row['year'] ?? date('Y'),
                    'revenue' => (float)($row['revenue'] ?? 0)
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $monthly]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }

    // --- GET SETTINGS ---
    elseif ($action === 'get_settings') {
        $sql = "SELECT * FROM restaurant_settings";
        $result = $conn->query($sql);
        $settings = [];
        while($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode(['status' => 'success', 'data' => $settings]);
    }

    // --- GET NOTIFICATIONS ---
    elseif ($action === 'get_notifications') {
        $sql = "SELECT * FROM admin_notifications ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $notifications = [];
        while($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $notifications]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // --- UPDATE ORDER STATUS ---
    if ($action === 'update_order_status') {
        $order_id = $input['order_id']; // This is the DB ID
        $status = $input['status'];
        
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE ORDER DETAILS (OVERRIDE) ---
    elseif ($action === 'update_order_details') {
        $order_id = $input['order_id'];
        $customer_email = $input['customer_email'];
        $total_amount = $input['total_amount'];
        
        $stmt = $conn->prepare("UPDATE orders SET customer_email = ?, total_amount = ? WHERE id = ?");
        $stmt->bind_param("sdi", $customer_email, $total_amount, $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- DELETE ORDER ---
    elseif ($action === 'delete_order') {
        $order_id = $input['order_id'];
        
        // Items cascade delete based on DB setup, but let's delete them explicitly just in case
        $conn->query("DELETE FROM order_items WHERE order_id = " . intval($order_id));
        
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE USER ROLE ---
    elseif ($action === 'update_user_role') {
        $user_id = $input['user_id'];
        $role = $input['role'];
        
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- CREATE USER (STAFF/ADMIN) ---
    elseif ($action === 'create_user') {
        // Form data from FormData
        $first = $_POST['first_name'];
        $last = $_POST['last_name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];

        if ($role === 'admin') {
            die(json_encode(['status' => 'error', 'message' => 'Only one admin account is allowed.']));
        }

        $status = $_POST['status'] ?? 'active';
        $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
        $full_name = $first . ' ' . $last;

        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, full_name, email, password, role, status, branch_id, terms_accepted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssssssi", $first, $last, $full_name, $email, $password, $role, $status, $branch_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Email might already exist or DB error: ' . $conn->error]);
        }
    }

    // --- UPDATE USER ---
    elseif ($action === 'update_user') {
        $id = $_POST['id'];

        if ($id == 1) { // Primary admin protection
            die(json_encode(['status' => 'error', 'message' => 'Main Admin account cannot be modified.']));
        }

        $first = $_POST['first_name'];
        $last = $_POST['last_name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
        $full_name = $first . ' ' . $last;

        // Optionally update password if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, full_name=?, email=?, role=?, status=?, branch_id=?, password=? WHERE id=?");
            $stmt->bind_param("ssssssis i", $first, $last, $full_name, $email, $role, $status, $branch_id, $password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, full_name=?, email=?, role=?, status=?, branch_id=? WHERE id=?");
            $stmt->bind_param("ssssssii", $first, $last, $full_name, $email, $role, $status, $branch_id, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- DELETE USER ---
    elseif ($action === 'delete_user') {
        $id = $input['id'] ?? $_POST['id'];
        if(!$id) die(json_encode(['status' => 'error', 'message' => 'No ID']));
        
        if ($id == 1) { // Primary admin protection
            die(json_encode(['status' => 'error', 'message' => 'Main Admin account cannot be deleted.']));
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'"); // Protect against self-deletion/main admin
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }
    
    // --- ADD MENU ITEM ---
    elseif ($action === 'add_menu_item') {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $description = $_POST['description'] ?? '';
        $ingredients = $_POST['ingredients'] ?? '';
        
        $image_url = isset($_FILES['image_file']) ? uploadAndResizeImage($_FILES['image_file']) : null;
        if (!$image_url) $image_url = 'assets/images/menu/default.jpg';
        
        $stmt = $conn->prepare("INSERT INTO menu_items (name, category, price, description, image_url, ingredients) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsss", $name, $category, $price, $description, $image_url, $ingredients);
        
        if ($stmt->execute()) {
            $msg = "Menu item added successfully!";
            if (!extension_loaded('gd')) $msg .= " (Note: GD library missing, image not resized)";
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE MENU ITEM ---
    elseif ($action === 'update_menu_item') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $description = $_POST['description'] ?? '';
        $ingredients = $_POST['ingredients'] ?? '';
        
        // Handle image update
        $image_url = isset($_FILES['image_file']) ? uploadAndResizeImage($_FILES['image_file']) : null;
        
        if ($image_url) {
            $stmt = $conn->prepare("UPDATE menu_items SET name = ?, category = ?, price = ?, description = ?, image_url = ?, ingredients = ? WHERE id = ?");
            $stmt->bind_param("ssdsssi", $name, $category, $price, $description, $image_url, $ingredients, $id);
        } else {
            $stmt = $conn->prepare("UPDATE menu_items SET name = ?, category = ?, price = ?, description = ?, ingredients = ? WHERE id = ?");
            $stmt->bind_param("ssdssi", $name, $category, $price, $description, $ingredients, $id);
        }
        
        if ($stmt->execute()) {
            $msg = "Menu item updated successfully!";
            if (!extension_loaded('gd')) $msg .= " (Note: GD library missing, image not resized)";
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- DELETE MENU ITEM ---
    elseif ($action === 'delete_menu_item') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE RESERVATION STATUS ---
    elseif ($action === 'update_reservation_status') {
        $res_id = $input['id'];
        $status = $input['status'];
        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $res_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- UPDATE SETTINGS ---
    elseif ($action === 'update_settings') {
        foreach ($input as $key => $value) {
            $stmt = $conn->prepare("UPDATE restaurant_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
        }
        echo json_encode(['status' => 'success']);
    }

    // --- MARK NOTIFICATION AS READ ---
    elseif ($action === 'mark_notification_read') {
        $id = $input['id'];
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- MARK ALL NOTIFICATIONS AS READ ---
    elseif ($action === 'mark_all_notifications_read') {
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // --- DELETE NOTIFICATION ---
    elseif ($action === 'delete_notification') {
        $id = $input['id'];
        $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }
}
?>
