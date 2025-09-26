<?php
session_start();

require_once __DIR__ . '/../src/database.php';

// --- Clear Persistent Login Token ---

if (isset($_COOKIE['remember_me'])) {
    $cookie = $_COOKIE['remember_me'];
    list($selector, $validator) = explode(':', $cookie, 2);

    if (!empty($selector)) {
        try {
            $conn = get_db_connection();
            $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE selector = ?");
            $stmt->bind_param("s", $selector);
            $stmt->execute();
        } catch (Exception $e) {
            // Log error, but proceed with logout
            // error_log('Failed to delete auth token: ' . $e->getMessage());
        }
    }

    // Unset the cookie by setting its expiration to the past
    setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE['remember_me']);
}


// --- Standard Session Destroy ---

// Unset all of the session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
