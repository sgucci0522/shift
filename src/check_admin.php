<?php
// First, ensure the user is logged in at all.
require_once __DIR__ . '/check_auth.php';

// Now, check if the logged-in user is an admin.
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // User is not an admin. Redirect them to the main page with an error message.
    header('Location: index.php?error=Access+Denied');
    exit;
}
?>