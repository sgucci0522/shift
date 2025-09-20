<?php
// setup_database.php

require_once __DIR__ . '/src/database.php';

echo "Setting up the database...\n";

try {
    $conn = get_db_connection();
    echo "Database '" . DB_NAME . "' is ready.\n";

    // SQL to create employees table
    $sql_employees = "
    CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    );";

    // SQL to create shifts table
    $sql_shifts = "
    CREATE TABLE IF NOT EXISTS shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        shift_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    );";

    if ($conn->query($sql_employees) === TRUE) {
        echo "Table 'employees' created successfully or already exists.\n";
    } else {
        throw new Exception("Error creating table 'employees': " . $conn->error);
    }

    if ($conn->query($sql_shifts) === TRUE) {
        echo "Table 'shifts' created successfully or already exists.\n";
    } else {
        throw new Exception("Error creating table 'shifts': " . $conn->error);
    }
    
    // Let's add a sample employee
    $sql_check_employee = "SELECT id FROM employees LIMIT 1;";
    $result = $conn->query($sql_check_employee);
    if ($result->num_rows == 0) {
        $conn->query("INSERT INTO employees (name) VALUES ('サンプル太郎');");
        echo "Added sample employee 'サンプル太郎'.\n";
    }


    $conn->close();
    echo "Database setup complete!\n";

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}

?>
