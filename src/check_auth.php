<?php
// src/check_auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// --- Helper Functions ---

function clear_remember_me_cookie(): void {
    if (isset($_COOKIE['remember_me'])) {
        unset($_COOKIE['remember_me']);
        setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/']);
    }
}

function clear_all_user_tokens(mysqli $conn, int $employee_id): void {
    $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
}

/**
 * Redirects to the login page after clearing any persistent login cookie.
 */
function redirect_to_login(): void {
    clear_remember_me_cookie();
    header('Location: /shift/public/login.php');
    exit;
}

/**
 * Validates the persistent login cookie and attempts to log the user in.
 */
function validate_persistent_login(): void {
    $cookie = $_COOKIE['remember_me'] ?? '';
    if (!$cookie) {
        // No cookie, nothing to do.
        return;
    }

    list($selector, $validator) = explode(':', $cookie, 2);
    if (empty($selector) || empty($validator)) {
        redirect_to_login();
    }

    try {
        $conn = get_db_connection();

        $stmt = $conn->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        $token = $result->fetch_assoc();

        if (!$token) {
            redirect_to_login();
        }

        if (!hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            // Possible theft attempt, clear all tokens for this user.
            clear_all_user_tokens($conn, $token['employee_id']);
            redirect_to_login();
        }

        // Token is valid, fetch user info and log in.
        $stmt = $conn->prepare("SELECT id, name, username, role FROM employees WHERE id = ?");
        $stmt->bind_param("i", $token['employee_id']);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user = $user_result->fetch_assoc();

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
        } else {
            // DB inconsistency: token exists but user doesn't.
            clear_all_user_tokens($conn, $token['employee_id']);
            redirect_to_login();
        }

    } catch (Exception $e) {
        // error_log('Cookie login failed: ' . $e->getMessage());
        redirect_to_login();
    }
}


// --- Main Authentication Check ---

if (!isset($_SESSION['user_id'])) {
    validate_persistent_login();
    
    // If session is still not set after the validation attempt, redirect to the plain login page.
    if (!isset($_SESSION['user_id'])) {
        redirect_to_login();
    }
}

// --- Password Change Check ---

// After authentication, check if user must change their password
if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password']) {
    // Get the current script name
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // If the user is not already on an allowed page, redirect them.
    $allowed_pages = ['change_password.php', 'change_password_process.php', 'logout.php'];
    if (!in_array($current_page, $allowed_pages)) {
        header('Location: /shift/public/change_password.php');
        exit;
    }
}
