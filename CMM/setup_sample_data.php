<?php
require_once 'config/database.php';

try {
    // First, let's see what users exist
    $users = $pdo->query("SELECT id, username, email, first_name, last_name FROM users")->fetchAll();
    echo "<h2>Existing Users:</h2>";
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}<br>";
    }

    // Check existing clubs
    $clubs = $pdo->query("SELECT id, name FROM clubs")->fetchAll();
    echo "<h2>Existing Clubs:</h2>";
    foreach ($clubs as $club) {
        echo "ID: {$club['id']}, Name: {$club['name']}<br>";
    }

    // Check existing club members
    $members = $pdo->query("
        SELECT cm.*, u.username, c.name as club_name 
        FROM club_members cm 
        JOIN users u ON cm.user_id = u.id 
        JOIN clubs c ON cm.club_id = c.id
    ")->fetchAll();
    echo "<h2>Existing Club Members:</h2>";
    foreach ($members as $member) {
        echo "User: {$member['username']}, Club: {$member['club_name']}, Role: {$member['role']}<br>";
    }

    // If no club members exist, add some
    if (empty($members)) {
        echo "<h2>Adding Sample Data...</h2>";
        
        // Get the first club
        if (!empty($clubs)) {
            $clubId = $clubs[0]['id'];
            
            // Add club members for existing users
            foreach ($users as $index => $user) {
                if ($user['id'] > 1) { // Skip admin
                    $role = ($index === 1) ? 'president' : 'member';
                    $stmt = $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status, joined_date) VALUES (?, ?, ?, 'active', CURDATE())");
                    $stmt->execute([$clubId, $user['id'], $role]);
                    echo "Added {$user['username']} as {$role} to club<br>";
                }
            }
        } else {
            // Create a sample club first
            $stmt = $pdo->prepare("INSERT INTO clubs (name, description, category, location, contact_email, member_count, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'Tech Innovation Club',
                'A community for technology enthusiasts and innovators',
                'Technology',
                'Main Campus',
                'tech@clubmaster.com',
                3,
                1
            ]);
            $clubId = $pdo->lastInsertId();
            echo "Created new club with ID: {$clubId}<br>";
            
            // Add members
            foreach ($users as $index => $user) {
                if ($user['id'] > 1) { // Skip admin
                    $role = ($index === 1) ? 'president' : 'member';
                    $stmt = $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status, joined_date) VALUES (?, ?, ?, 'active', CURDATE())");
                    $stmt->execute([$clubId, $user['id'], $role]);
                    echo "Added {$user['username']} as {$role} to new club<br>";
                }
            }
        }
    }

    echo "<br><h2>Setup Complete!</h2>";
    echo "<a href='login.php'>Go to Login</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>