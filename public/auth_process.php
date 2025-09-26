<?php
session_start();
require_once __DIR__ . '/../src/database.php';

// --- Helper Functions for Persistent Login ---

/**
 * Generates and stores a new token for persistent login.
 *
 * @param mysqli $conn The database connection.
 * @param int $employee_id The ID of the logged-in employee.
 * @return array An array containing the selector and the validator for the cookie.
 */
function generate_and_store_token(mysqli $conn, int $employee_id): array {
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hashed_validator = hash('sha256', $validator);
    $expires = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days from now

    $stmt = $conn->prepare(
        "INSERT INTO auth_tokens (selector, hashed_validator, employee_id, expires) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssis", $selector, $hashed_validator, $employee_id, $expires);
    $stmt->execute();

    return ['selector' => $selector, 'validator' => $validator];
}

/**
 * Sets the persistent login cookie.
 *
 * @param string $selector The selector part of the token.
 * @param string $validator The validator part of the token.
 */
function set_persistent_cookie(string $selector, string $validator): void {
    $cookie_value = $selector . ':' . $validator;
    // Set cookie to last for 30 days, httpOnly, and secure (if on HTTPS)
    setcookie(
        'remember_me',
        $cookie_value,
        [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'domain' => '', // Set your domain if needed
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}


// --- Main Login Logic ---

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';

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
        
        // Handle "Remember Me" functionality
        if ($remember_me) {
            try {
                $token = generate_and_store_token($conn, $user['id']);
                set_persistent_cookie($token['selector'], $token['validator']);
            } catch (Exception $e) {
                // Log the error, but don't block the user from logging in
                // error_log("Failed to create remember_me token: " . $e->getMessage());
            }
        }

        // Redirect to the main application page
        header('Location: index.php');
        exit;
    }
}

// If we reach here, it means login failed (user not found or password incorrect)
header('Location: login.php?error=ユーザー名またはパスワードが間違っています。');
exit;
?>
