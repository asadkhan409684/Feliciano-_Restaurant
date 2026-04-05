<?php
session_start();
require 'config/database.php';

// Prevent any PHP errors/notices from leaking into the JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

header('Content-Type: application/json');

function sendResponse($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        sendResponse(false, 'Invalid JSON input');
    }

    $customer_name = $input['customerName'] ?? 'Guest';
    $customer_email = $input['customerEmail'] ?? '';
    $customer_phone = $input['customerPhone'] ?? '';
    $order_type = $input['orderType'] ?? 'online';
    $delivery_address = $input['deliveryAddress'] ?? '';
    $delivery_time_raw = $input['deliveryTime'] ?? null;
    $delivery_time = !empty($delivery_time_raw) ? date('Y-m-d H:i:s', strtotime($delivery_time_raw)) : null;
    $special_instructions = $input['specialInstructions'] ?? '';
    $table_number = $input['tableNumber'] ?? '';
    $branch_id = !empty($input['branchId']) ? intval($input['branchId']) : null;
    
    // Resolve customer_id if user is logged in
    $customer_id = null;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $cust_stmt = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
        if ($cust_stmt) {
            $cust_stmt->bind_param("i", $user_id);
            $cust_stmt->execute();
            $cust_res = $cust_stmt->get_result();
            if ($row = $cust_res->fetch_assoc()) {
                $customer_id = $row['id'];
            }
            $cust_stmt->close();
        }
    }
    
    if (!isset($input['orders']) || empty($input['orders'])) {
        sendResponse(false, 'Cart is empty');
    }
    
    $items = $input['orders'];
    $subtotal = 0;
    $tax = 0;
    foreach ($items as $item) {
        $price = isset($item['price']) ? floatval($item['price']) : 0;
        $qty = isset($item['quantity']) ? intval($item['quantity']) : 0;
        $subtotal += ($price * $qty);
    }
    $total_amount = $subtotal + $tax;

    // Start transaction
    $conn->begin_transaction();

    try {
        // --- CUSTOMER PROFILE MANAGEMENT ---
        $customer_db_id = null;
        $user_id = $_SESSION['user_id'] ?? null;
        
        // 1. Try to find customer by user_id if logged in, otherwise by email
        if ($user_id) {
            $cust_stmt = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
            $cust_stmt->bind_param("i", $user_id);
            $cust_stmt->execute();
            $cust_res = $cust_stmt->get_result();
            if ($row = $cust_res->fetch_assoc()) {
                $customer_db_id = $row['id'];
            }
            $cust_stmt->close();
        }
        
        if (!$customer_db_id && !empty($customer_email)) {
            $cust_stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
            $cust_stmt->bind_param("s", $customer_email);
            $cust_stmt->execute();
            $cust_res = $cust_stmt->get_result();
            if ($row = $cust_res->fetch_assoc()) {
                $customer_db_id = $row['id'];
            }
            $cust_stmt->close();
        }

        // 2. Either UPDATE or INSERT customer record
        if ($customer_db_id) {
            $upd_cust = $conn->prepare("UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ?, last_order_date = NOW(), user_id = COALESCE(user_id, ?) WHERE id = ?");
            $upd_cust->bind_param("dii", $total_amount, $user_id, $customer_db_id);
            $upd_cust->execute();
            $upd_cust->close();
        } else {
            $new_cust_id_str = 'CUST-' . strtoupper(bin2hex(random_bytes(4)));
            $ins_cust = $conn->prepare("INSERT INTO customers (customer_id, user_id, full_name, email, phone, total_orders, total_spent, last_order_date) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
            $ins_cust->bind_param("sisssd", $new_cust_id_str, $user_id, $customer_name, $customer_email, $customer_phone, $total_amount);
            $ins_cust->execute();
            $customer_db_id = $conn->insert_id;
            $ins_cust->close();
        }

        // --- ORDER INSERTION ---
        $order_id_str = 'ORD-' . date('Ymd') . '-' . substr(str_shuffle("0123456789"), 0, 4);

        $sql = "INSERT INTO orders (order_id, customer_id, customer_name, customer_email, customer_phone, order_type, table_number, delivery_address, delivery_time, special_instructions, branch_id, subtotal, tax, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sissssssssiddd", 
            $order_id_str, 
            $customer_db_id, // Link to the newly created or updated customer ID
            $customer_name, 
            $customer_email, 
            $customer_phone, 
            $order_type, 
            $table_number, 
            $delivery_address, 
            $delivery_time, 
            $special_instructions, 
            $branch_id,
            $subtotal, 
            $tax, 
            $total_amount
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $db_order_id = $conn->insert_id;
        $stmt->close();

        // Prepare item insert once
        $item_sql = "INSERT INTO order_items (order_id, menu_item_id, menu_item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
        $item_stmt = $conn->prepare($item_sql);
        
        if (!$item_stmt) {
            throw new Exception("Item prepare failed: " . $conn->error);
        }
        
        foreach ($items as $item) {
            $item_name = $item['name'] ?? 'Unknown Item';
            $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $price = isset($item['price']) ? floatval($item['price']) : 0;
            $total_item_price = $price * $qty;
            $menu_item_id = $item['id'] ?? 0;

            if (empty($menu_item_id)) {
                $lookup_stmt = $conn->prepare("SELECT id FROM menu_items WHERE name = ? LIMIT 1");
                $lookup_stmt->bind_param("s", $item_name);
                $lookup_stmt->execute();
                $lookup_result = $lookup_stmt->get_result();
                if ($row = $lookup_result->fetch_assoc()) {
                    $menu_item_id = $row['id'];
                }
                $lookup_stmt->close();
            }
            
            if (!empty($menu_item_id)) {
                $item_stmt->bind_param("iisidd", $db_order_id, $menu_item_id, $item_name, $qty, $price, $total_item_price);
                if (!$item_stmt->execute()) {
                    throw new Exception("Item execute failed: " . $item_stmt->error);
                }
            }
        }
        $item_stmt->close();

        // Notification
        $notif_title = "New Order Received";
        $notif_msg = "Order $order_id_str received from $customer_name";
        $notif_sql = "INSERT INTO admin_notifications (type, title, message, related_id, branch_id) VALUES ('order', ?, ?, ?, ?)";
        $notif_stmt = $conn->prepare($notif_sql);
        if ($notif_stmt) {
            $notif_stmt->bind_param("sssi", $notif_title, $notif_msg, $order_id_str, $branch_id);
            $notif_stmt->execute();
            $notif_stmt->close();
        }

        $conn->commit();
        sendResponse(true, 'Order placed successfully', ['orderId' => $order_id_str, 'status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Order Error: " . $e->getMessage());
        sendResponse(false, 'Order processing failed: ' . $e->getMessage());
    }
} else {
    sendResponse(false, 'Invalid request method');
}
?>
