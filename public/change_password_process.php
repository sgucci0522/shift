<?php
session_start();
require_once __DIR__ . '/../src/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: change_password.php');
    exit;
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// --- Validation ---
if (empty($new_password) || empty($confirm_password)) {
    header('Location: change_password.php?error=すべてのフィールドを入力してください。');
    exit;
}

if (strlen($new_password) < 8) {
    header('Location: change_password.php?error=パスワードは8文字以上で設定してください。');
    exit;
}

if ($new_password !== $confirm_password) {
    header('Location: change_password.php?error=新しいパスワードが一致しません。');
    exit;
}

// --- Update Password ---
try {
    $conn = get_db_connection();
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $employee_id = $_SESSION['user_id'];

    // Update password and reset the must_change_password flag
    $stmt = $conn->prepare(
        "UPDATE employees SET password = ?, must_change_password = 0 WHERE id = ?"
    );
    $stmt->bind_param("si", $hashed_password, $employee_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Update session flag
        $_SESSION['must_change_password'] = false;

        // Redirect to index page with a success message
        // We use a session flash message for this
        $_SESSION['flash_message'] = "パスワードが正常に更新されました。";
        header('Location: index.php');
        exit;
    } else {
        header('Location: change_password.php?error=パスワードの更新に失敗しました。データベースで問題が発生した可能性があります。');
        exit;
    }

} catch (Exception $e) {
    // error_log('Password change failed: ' . $e->getMessage());
    header('Location: change_password.php?error=データベースエラーが発生しました。');
    exit;
}
