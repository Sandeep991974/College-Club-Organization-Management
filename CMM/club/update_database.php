<?php
require_once '../config/database.php';

try {
    echo "<h2>Database Schema Update</h2>";
    
    // Check if columns exist and add them if they don't
    $columns_to_add = [
        'course' => 'VARCHAR(100) DEFAULT NULL',
        'year' => 'VARCHAR(20) DEFAULT NULL', 
        'department' => 'VARCHAR(100) DEFAULT NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        // Check if column exists
        $checkQuery = "SHOW COLUMNS FROM users LIKE '$column'";
        $checkStmt = $pdo->query($checkQuery);
        $columnExists = $checkStmt->fetch();
        
        if (!$columnExists) {
            echo "<p>Adding column '$column' to users table...</p>";
            try {
                $alterStmt = "ALTER TABLE users ADD COLUMN `$column` $definition";
                $pdo->exec($alterStmt);
                echo "<p style='color: green;'>✓ Column '$column' added successfully!</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ Error adding column '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ Column '$column' already exists.</p>";
        }
    }
    
    // Verify all columns exist
    echo "<h3>Current users table structure:</h3>";
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $columnsStmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3 style='color: green;'>Database update completed successfully!</h3>";
    echo "<p><a href='team_management.php'>← Go back to Team Management</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error updating database: " . $e->getMessage() . "</p>";
}
?>