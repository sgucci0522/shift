<?php
// src/database.php
require_once __DIR__ . '/../config/database.php';

function get_db_connection() {
    // Connect without specifying the database first to create it if it doesn't exist
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn->connect_error) {
        // In a real app, you'd log this error, not just die
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    
    // Close connection and reconnect to the specific database
    $conn->close();
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");

    return $conn;
}
?>