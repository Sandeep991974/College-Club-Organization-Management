<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create user_settings table
        $sql = "
        CREATE TABLE IF NOT EXISTS `user_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `email_notifications` tinyint(1) DEFAULT 1,
            `event_reminders` tinyint(1) DEFAULT 1,
            `club_updates` tinyint(1) DEFAULT 1,
            `marketing_emails` tinyint(1) DEFAULT 0,
            `profile_visibility` enum('public','members','private') DEFAULT 'public',
            `show_email` tinyint(1) DEFAULT 0,
            `show_phone` tinyint(1) DEFAULT 0,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_id` (`user_id`),
            KEY `user_settings_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql);
        
        // Add status column to users table if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `status` enum('active','inactive') DEFAULT 'active'");
        } catch (Exception $e) {
            // Column might already exist, ignore this error
        }
        
        $message = "User settings table created successfully! You can now use the Settings page.";
        
    } catch (Exception $e) {
        $error = "Error creating table: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup User Settings - ClubMaster</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .setup-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .setup-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ffffff, #b0b0b0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .setup-description {
            color: #b0b0b0;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .setup-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <i class="fas fa-cogs setup-icon"></i>
        <h1 class="setup-title">Setup User Settings</h1>
        <p class="setup-description">
            The user settings functionality requires a database table to store user preferences. 
            Click the button below to create the required table.
        </p>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="margin: 2rem 0;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-database"></i>
                Create User Settings Table
            </button>
        </form>

        <div>
            <a href="settings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Settings
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>