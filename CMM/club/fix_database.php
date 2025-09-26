<?php
require_once '../config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Fix - ClubMaster</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>ClubMaster Database Fix</h1>
        
        <?php
        try {
            echo "<h2>Step 1: Checking Current Database Structure</h2>";
            
            // Get current columns
            $columnsStmt = $pdo->query("DESCRIBE users");
            $currentColumns = [];
            while ($col = $columnsStmt->fetch()) {
                $currentColumns[] = $col['Field'];
            }
            
            echo "<div class='info'>Current columns in users table: " . implode(', ', $currentColumns) . "</div>";
            
            // Check which columns are missing
            $requiredColumns = [
                'course' => 'VARCHAR(100) DEFAULT NULL',
                'year' => 'VARCHAR(20) DEFAULT NULL', 
                'department' => 'VARCHAR(100) DEFAULT NULL'
            ];
            
            $missingColumns = [];
            foreach ($requiredColumns as $column => $definition) {
                if (!in_array($column, $currentColumns)) {
                    $missingColumns[$column] = $definition;
                }
            }
            
            if (empty($missingColumns)) {
                echo "<div class='success'>✓ All required columns are present!</div>";
            } else {
                echo "<h2>Step 2: Adding Missing Columns</h2>";
                
                foreach ($missingColumns as $column => $definition) {
                    echo "<p>Adding column: <strong>$column</strong></p>";
                    
                    try {
                        $sql = "ALTER TABLE users ADD COLUMN `$column` $definition";
                        echo "<div style='background: #f8f9fa; padding: 10px; font-family: monospace; margin: 5px 0;'>$sql</div>";
                        
                        $pdo->exec($sql);
                        echo "<div class='success'>✓ Column '$column' added successfully!</div>";
                        
                    } catch (PDOException $e) {
                        echo "<div class='error'>✗ Error adding column '$column': " . $e->getMessage() . "</div>";
                    }
                }
            }
            
            echo "<h2>Step 3: Final Database Structure</h2>";
            
            // Show final structure
            $finalColumnsStmt = $pdo->query("DESCRIBE users");
            $finalColumns = $finalColumnsStmt->fetchAll();
            
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            foreach ($finalColumns as $column) {
                $highlight = in_array($column['Field'], ['course', 'year', 'department']) ? 'background: #d4edda;' : '';
                echo "<tr style='$highlight'>";
                echo "<td><strong>{$column['Field']}</strong></td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "<td>{$column['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<div class='success'><h3>✓ Database update completed!</h3></div>";
            
        } catch (PDOException $e) {
            echo "<div class='error'><strong>Database Error:</strong> " . $e->getMessage() . "</div>";
        }
        ?>
        
        <h2>Next Steps</h2>
        <p>Now that the database has been updated, you can:</p>
        <ul>
            <li><a href="team_management.php" class="button">Go to Team Management</a></li>
            <li><a href="../index.php" class="button">Go to Homepage</a></li>
        </ul>
        
        <h3>Testing</h3>
        <p>Try these features to verify everything works:</p>
        <ul>
            <li>Click "View" button on any team to see member details</li>
            <li>Click "Edit" button to manage team information and members</li>
            <li>Create a new team with member information</li>
        </ul>
    </div>
</body>
</html>