<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $result = $conn->query("SELECT * FROM branches ORDER BY created_at DESC");
        $branches = [];
        while ($row = $result->fetch_assoc()) {
            $branches[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $branches]);
        break;

    case 'save':
        // Admin only
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $location = $_POST['location'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($location) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }

        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE branches SET name = ?, location = ?, phone = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $location, $phone, $status, $id);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO branches (name, location, phone, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $location, $phone, $status);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Branch saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
        }
        break;

    case 'delete':
        // Admin only
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Branch deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
            }
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
