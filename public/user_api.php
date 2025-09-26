<?php
// public/user_api.php

require_once __DIR__ . '/../src/check_admin.php';
require_once __DIR__ . '/../src/database.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = get_db_connection();

    if ($method === 'GET') {
        // Get all users, ordered by display_order
        $result = $conn->query("SELECT id, name, username, role, display_order FROM employees ORDER BY display_order, name");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $response['status'] = 'success';
        $response['data'] = $users;

    } elseif ($method === 'POST') {
        // Create a new user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['name'], $data['username'], $data['password'], $data['role'])) {
            throw new Exception('Missing required fields.');
        }
        // Use provided display_order or default to 9999
        $display_order = $data['display_order'] ?? 9999;
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // must_change_password will be 1 by default from the DB schema
        $stmt = $conn->prepare("INSERT INTO employees (name, username, password, role, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $data['name'], $data['username'], $hashed_password, $data['role'], $display_order);
        
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'User created successfully.';
        } else {
            throw new Exception('Failed to create user: ' . $stmt->error);
        }

    } elseif ($method === 'PUT') {
        // Update a user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['user_id'], $data['name'], $data['username'], $data['role'], $data['display_order'])) {
            throw new Exception('Missing required fields for update.');
        }
        $display_order = $data['display_order'];

        if (!empty($data['password'])) {
            // Update with new password, and force user to change it on next login
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE employees SET name = ?, username = ?, role = ?, display_order = ?, password = ?, must_change_password = 1 WHERE id = ?");
            $stmt->bind_param("sssisi", $data['name'], $data['username'], $data['role'], $display_order, $hashed_password, $data['user_id']);
        } else {
            // Update without changing password
            $stmt = $conn->prepare("UPDATE employees SET name = ?, username = ?, role = ?, display_order = ? WHERE id = ?");
            $stmt->bind_param("sssii", $data['name'], $data['username'], $data['role'], $display_order, $data['user_id']);
        }
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'User updated successfully.';
        } else {
            throw new Exception('Failed to update user: ' . $stmt->error);
        }

    } elseif ($method === 'DELETE') {
        // (DELETE logic remains the same)
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['user_id'])) {
            throw new Exception('User ID is required for deletion.');
        }
        if ($data['user_id'] == $_SESSION['user_id']) {
             throw new Exception('Cannot delete your own account.');
        }
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->bind_param("i", $data['user_id']);
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'User deleted successfully.';
        } else {
            throw new Exception('Failed to delete user: ' . $stmt->error);
        }
    }

    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
