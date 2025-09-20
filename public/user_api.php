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
        // Get all users
        $result = $conn->query("SELECT id, name, username, role FROM employees ORDER BY id");
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
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO employees (name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $data['name'], $data['username'], $hashed_password, $data['role']);
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'User created successfully.';
        } else {
            throw new Exception('Failed to create user: ' . $stmt->error);
        }

    } elseif ($method === 'PUT') {
        // Update a user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['user_id'], $data['name'], $data['username'], $data['role'])) {
            throw new Exception('Missing required fields for update.');
        }

        if (!empty($data['password'])) {
            // Update with new password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE employees SET name = ?, username = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $data['name'], $data['username'], $data['role'], $hashed_password, $data['user_id']);
        } else {
            // Update without changing password
            $stmt = $conn->prepare("UPDATE employees SET name = ?, username = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $data['name'], $data['username'], $data['role'], $data['user_id']);
        }
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'User updated successfully.';
        } else {
            throw new Exception('Failed to update user: ' . $stmt->error);
        }

    } elseif ($method === 'DELETE') {
        // Delete a user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['user_id'])) {
            throw new Exception('User ID is required for deletion.');
        }
        // Prevent deleting the last admin
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