<?php
// src/check_auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// This file is in src/, so database.php is in the same directory.
require_once __DIR__ . '/database.php';

/**
 * Validates the persistent login cookie and logs the user in if valid.
 * @return bool True if login via cookie was successful, false otherwise.
 */
function login_via_cookie(): bool {
    $cookie = $_COOKIE['remember_me'] ?? '';
    if (!$cookie) {
        return false;
    }

    list($selector, $validator) = explode(':', $cookie, 2);
    if (empty($selector) || empty($validator)) {
        return false;
    }

    try {
        $conn = get_db_connection();

        $stmt = $conn->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        $token = $result->fetch_assoc();

        if (!$token) {
            clear_remember_me_cookie(); // Token not found or expired
            return false;
        }

        // Validate the token
        if (!hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            // Validator mismatch. Possible theft attempt.
            clear_all_user_tokens($conn, $token['employee_id']);
            clear_remember_me_cookie();
            return false;
        }

        // Token is valid, log the user in
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
            
            // Optional but recommended: Rotate token
            // For simplicity, we'll skip this here.

            return true;
        }

    } catch (Exception $e) {
        // Log error, don't expose details
        // error_log('Cookie login failed: ' . $e->getMessage());
        return false;
    }

    return false;
}

/**
 * Clears the remember_me cookie from the browser.
 */
function clear_remember_me_cookie(): void {
    if (isset($_COOKIE['remember_me'])) {
        unset($_COOKIE['remember_me']);
        setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/']);
    }
}

/**
 * Clears all persistent login tokens for a specific user from the database.
 * @param mysqli $conn
 * @param int $employee_id
 */
function clear_all_user_tokens(mysqli $conn, int $employee_id): void {
    $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
}


// --- Main Authentication Check ---

// If user is not logged in via session, try to log in via cookie.
if (!isset($_SESSION['user_id'])) {
    if (!login_via_cookie()) {
        // If both session and cookie login fail, redirect to login page.
        // This path needs to be absolute from the web root.
        header('Location: /shift/public/login.php');
        exit;
    }
}
