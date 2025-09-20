<?php
// update_schema_for_auth.php

require_once __DIR__ . '/src/database.php';

echo "Updating database schema for authentication...\n";

try {
    $conn = get_db_connection();

    // Check if columns exist before adding them
    $columns = ['username', 'password', 'role'];
    foreach ($columns as $column) {
        $result = $conn->query("SHOW COLUMNS FROM `employees` LIKE '{$column}';");
        if ($result->num_rows == 0) {
            $sql = "";
            if ($column === 'username') {
                $sql = "ALTER TABLE employees ADD COLUMN username VARCHAR(255) UNIQUE AFTER name;";
            } elseif ($column === 'password') {
                $sql = "ALTER TABLE employees ADD COLUMN password VARCHAR(255) AFTER username;";
            } elseif ($column === 'role') {
                $sql = "ALTER TABLE employees ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'employee' AFTER password;";
            }
            if ($conn->query($sql) === TRUE) {
                echo "Column '{$column}' added successfully.\n";
            } else {
                throw new Exception("Error adding column '{$column}': " . $conn->error);
            }
        }
    }

    // Add sample admin and user if they don't exist
    $default_password = password_hash('password123', PASSWORD_DEFAULT);

    // Make the first employee an admin
    $result = $conn->query("SELECT id FROM employees WHERE username = 'admin';");
    if ($result->num_rows == 0) {
        // Update the very first employee to be the admin
        $conn->query("UPDATE employees SET username = 'admin', password = '{$default_password}', role = 'admin' ORDER BY id LIMIT 1;");
        echo "Updated first employee to be admin.\n";
    }

    // Add a sample employee user
    $result = $conn->query("SELECT id FROM employees WHERE username = 'user';");
    if ($result->num_rows == 0) {
        $conn->query("INSERT INTO employees (name, username, password, role) VALUES ('一般ユーザー', 'user', '{$default_password}', 'employee');");
        echo "Added sample employee 'user'.\n";
    }

    $conn->close();
    echo "Database schema update complete!\n";

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}

?>