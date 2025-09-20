<?php
session_start();
require_once __DIR__ . '/../src/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: login.php?error=ユーザー名とパスワードを入力してください。');
    exit;
}

$conn = get_db_connection();

$stmt = $conn->prepare("SELECT id, name, username, password, role FROM employees WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify the password
    if (password_verify($password, $user['password'])) {
        // Password is correct, regenerate session ID for security
        session_regenerate_id(true);

        // Store user info in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        
        // Redirect to the main application page
        header('Location: index.php');
        exit;
    }
}

// If we reach here, it means login failed (user not found or password incorrect)
header('Location: login.php?error=ユーザー名またはパスワードが間違っています。');
exit;
?>