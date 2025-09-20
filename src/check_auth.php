<?php
// Start session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not, redirect to the login page
    // We need to make sure the location is correct whether this is included from /public or / (root)
    // A simple way is to use an absolute path if possible, but for this project let's assume it's included from /public
    header('Location: login.php');
    exit;
}
?>