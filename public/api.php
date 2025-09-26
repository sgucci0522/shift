<?php
// public/api.php

require_once __DIR__ . '/../src/check_auth.php';
require_once __DIR__ . '/../src/database.php';

header('Content-Type: application/json');

/**
 * Checks for overlapping shifts for a given employee and time range.
 *
 * @param mysqli $conn The database connection.
 * @param int $employee_id
 * @param string $shift_date
 * @param string $start_time
 * @param string $end_time
 * @param int|null $exclude_shift_id The ID of the shift to exclude from the check (for updates).
 * @return bool True if an overlapping shift exists, false otherwise.
 */
function has_overlapping_shift(mysqli $conn, int $employee_id, string $shift_date, string $start_time, string $end_time, ?int $exclude_shift_id = null): bool {
    $sql = "
        SELECT id FROM shifts 
        WHERE employee_id = ? 
          AND shift_date = ? 
          AND start_time < ? 
          AND end_time > ?
    ";
    $params = [$employee_id, $shift_date, $end_time, $start_time];
    $types = "isss";

    if ($exclude_shift_id !== null) {
        $sql .= " AND id != ?";
        $params[] = $exclude_shift_id;
        $types .= "i";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}


$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

try {
    $conn = get_db_connection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST' || $method === 'PUT') {
        // Admin check for POST and PUT
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403); // Forbidden
            $response['message'] = '権限がありません。';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        // --- Validation ---
        $required_fields = ['employee_id', 'shift_date', 'start_time', 'end_time'];
        if ($method === 'PUT') {
            $required_fields[] = 'shift_id';
        }
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        if ($data['start_time'] >= $data['end_time']) {
            http_response_code(400); // Bad Request
            $response['message'] = '開始時刻は終了時刻より前に設定してください。';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- Overlap Check ---
        $exclude_id = ($method === 'PUT') ? (int)$data['shift_id'] : null;
        if (has_overlapping_shift($conn, (int)$data['employee_id'], $data['shift_date'], $data['start_time'], $data['end_time'], $exclude_id)) {
            http_response_code(409); // Conflict
            $response['message'] = '指定された時間帯は、同じ従業員の他のシフトと重複しています。';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- DB Operation ---
        if ($method === 'POST') {
            $stmt = $conn->prepare("INSERT INTO shifts (employee_id, shift_date, start_time, end_time) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $data['employee_id'], $data['shift_date'], $data['start_time'], $data['end_time']);
            $success_message = 'Shift added successfully.';
        } else { // PUT
            $stmt = $conn->prepare("UPDATE shifts SET employee_id = ?, shift_date = ?, start_time = ?, end_time = ? WHERE id = ?");
            $stmt->bind_param("isssi", $data['employee_id'], $data['shift_date'], $data['start_time'], $data['end_time'], $data['shift_id']);
            $success_message = 'Shift updated successfully.';
        }

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = $success_message;
        } else {
            throw new Exception("Database operation failed: " . $stmt->error);
        }
        $stmt->close();

    } else { // GET request
        // (GET logic remains the same as before)
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
    http_response_code(500); // Internal Server Error
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
