<?php
// public/api.php

require_once __DIR__ . '/../src/check_auth.php';

header('Content-Type: application/json');
require_once __DIR__ . '/../src/database.php';

$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

try {
    $conn = get_db_connection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        // Only allow admin to add shifts
        if ($_SESSION['user_role'] !== 'admin') {
            $response['message'] = '権限がありません。シフトを追加するには管理者としてログインしてください。';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Add a new shift
        $data = json_decode(file_get_contents('php://input'), true);

        // Basic validation
        if (!isset($data['employee_id'], $data['shift_date'], $data['start_time'], $data['end_time'])) {
            throw new Exception('Missing required fields.');
        }

        $stmt = $conn->prepare("INSERT INTO shifts (employee_id, shift_date, start_time, end_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $data['employee_id'], $data['shift_date'], $data['start_time'], $data['end_time']);
        
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Shift added successfully.';
        } else {
            throw new Exception('Failed to add shift: ' . $stmt->error);
        }
        $stmt->close();

    } elseif ($method === 'PUT') {
        // Only allow admin to update shifts
        if ($_SESSION['user_role'] !== 'admin') {
            $response['message'] = '権限がありません。シフトを更新するには管理者としてログインしてください。';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Update an existing shift
        $data = json_decode(file_get_contents('php://input'), true);

        // Basic validation
        if (!isset($data['shift_id'], $data['employee_id'], $data['shift_date'], $data['start_time'], $data['end_time'])) {
            throw new Exception('Missing required fields for update.');
        }

        $stmt = $conn->prepare("UPDATE shifts SET employee_id = ?, shift_date = ?, start_time = ?, end_time = ? WHERE id = ?");
        $stmt->bind_param("isssi", $data['employee_id'], $data['shift_date'], $data['start_time'], $data['end_time'], $data['shift_id']);
        
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Shift updated successfully.';
        } else {
            throw new Exception('Failed to update shift: ' . $stmt->error);
        }
        $stmt->close();

    } else { // GET request
        $action = $_GET['action'] ?? 'get_shifts';

        if ($action === 'get_shifts') {
            $sql = "
                SELECT 
                    s.id,
                    s.employee_id,
                    s.shift_date,
                    s.start_time,
                    s.end_time,
                    e.name as employee_name
                FROM shifts s
                JOIN employees e ON s.employee_id = e.id
                ORDER BY s.shift_date, s.start_time;
            ";
            $result = $conn->query($sql);
            $data = [];
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $response['status'] = 'success';
            $response['data'] = $data;

        } elseif ($action === 'get_employees') {
            $sql = "SELECT id, name FROM employees ORDER BY name;";
            $result = $conn->query($sql);
            $data = [];
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $response['status'] = 'success';
            $response['data'] = $data;
        } else {
            throw new Exception('Invalid action.');
        }
    }
    
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>