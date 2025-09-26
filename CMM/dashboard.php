<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT role, username, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found, destroy session and redirect
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
    // Update last login time
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
    
    // Redirect based on user role
    switch ($user['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'manager':
            header('Location: club/dashboard.php');
            break;
        case 'member':
            header('Location: club/dashboard.php');
            break;
        default:
            header('Location: club/dashboard.php');
            break;
    }
    exit();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    header('Location: login.php?error=database_error');
    exit();
}
?>