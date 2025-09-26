<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'notifications') {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $event_reminders = isset($_POST['event_reminders']) ? 1 : 0;
        $club_updates = isset($_POST['club_updates']) ? 1 : 0;
        $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
        
        try {
            // Try to update/create settings if table exists
            try {
                $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $settings_exist = $stmt->fetch();
                
                if ($settings_exist) {
                    // Update existing settings
                    $stmt = $pdo->prepare("
                        UPDATE user_settings 
                        SET email_notifications = ?, event_reminders = ?, club_updates = ?, marketing_emails = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$email_notifications, $event_reminders, $club_updates, $marketing_emails, $user_id]);
                } else {
                    // Create new settings
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, email_notifications, event_reminders, club_updates, marketing_emails)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $email_notifications, $event_reminders, $club_updates, $marketing_emails]);
                }
                
                $success = "Notification settings updated successfully!";
            } catch (Exception $e) {
                // Table doesn't exist, show message to create it
                $error = "Settings functionality requires database setup. Please <a href='setup_user_settings.php' style='color: #667eea; text-decoration: underline;'>click here to set up the database</a> first.";
            }
        } catch (Exception $e) {
            $error = "Error updating settings: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'privacy') {
        $profile_visibility = $_POST['profile_visibility'] ?? 'public';
        $show_email = isset($_POST['show_email']) ? 1 : 0;
        $show_phone = isset($_POST['show_phone']) ? 1 : 0;
        
        try {
            // Try to update/create privacy settings if table exists
            try {
                $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $settings_exist = $stmt->fetch();
                
                if ($settings_exist) {
                    $stmt = $pdo->prepare("
                        UPDATE user_settings 
                        SET profile_visibility = ?, show_email = ?, show_phone = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$profile_visibility, $show_email, $show_phone, $user_id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, profile_visibility, show_email, show_phone)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $profile_visibility, $show_email, $show_phone]);
                }
                
                $success = "Privacy settings updated successfully!";
            } catch (Exception $e) {
                // Table doesn't exist, show message to create it
                $error = "Settings functionality requires database setup. Please create the user_settings table first.";
            }
        } catch (Exception $e) {
            $error = "Error updating privacy settings: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'deactivate') {
        $confirm = $_POST['confirm_deactivate'] ?? '';
        if ($confirm === 'DEACTIVATE') {
            try {
                // Mark user as inactive instead of deleting
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                
                // Clear session and redirect
                session_destroy();
                header('Location: ../index.php?message=Account deactivated successfully');
                exit();
            } catch (Exception $e) {
                $error = "Error deactivating account: " . $e->getMessage();
            }
        } else {
            $error = "Please type 'DEACTIVATE' to confirm account deactivation.";
        }
    }
}

try {
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Initialize default settings
    $settings = [
        'email_notifications' => 1,
        'event_reminders' => 1,
        'club_updates' => 1,
        'marketing_emails' => 0,
        'profile_visibility' => 'public',
        'show_email' => 0,
        'show_phone' => 0
    ];

    // Try to get user settings if table exists
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_settings = $stmt->fetch();
        
        if ($user_settings) {
            $settings = $user_settings;
        }
    } catch (Exception $e) {
        // user_settings table doesn't exist yet, use defaults
        // This is not an error, just means table needs to be created
    }

} catch (Exception $e) {
    $error = "Error loading user information: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ClubMaster</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a0a;
            color: #ffffff;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 200px;
            height: 100vh;
            background: rgba(15, 15, 15, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1000;
            transform: translateX(0);
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
        }

        .sidebar-logo i {
            margin-right: 0.4rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.3rem;
        }

        .user-badge {
            display: inline-block;
            background: linear-gradient(45deg, #4caf50, #45a049);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .manager-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.75rem;
            color: #b0b0b0;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 0.85rem;
        }

        .nav-link:hover, .nav-link.active {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            border-left-color: #667eea;
        }

        .nav-link i {
            margin-right: 1rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .nav-section {
            padding: 0 0.75rem;
            margin: 1.25rem 0 0.5rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* Main Content */
        .main-content {
            margin-left: 200px;
            min-height: 100vh;
            padding: 1.25rem;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding: 0.6rem 1.25rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ffffff, #b0b0b0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #ffffff;
        }

        .user-role {
            font-size: 0.85rem;
            color: #667eea;
        }

        .logout-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        }

        /* Settings Layout */
        .settings-container {
            max-width: 800px;
        }

        .settings-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1.25rem;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.875rem;
            padding: 1.25rem 1.25rem 0.625rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
        }

        .card-description {
            margin-top: 0.5rem;
            color: #b0b0b0;
            font-size: 0.85rem;
        }

        .card-content {
            padding: 1.25rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #b0b0b0;
            font-size: 0.9rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        /* Checkboxes */
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .checkbox-label {
            font-weight: 500;
            color: #ffffff;
        }

        .checkbox-description {
            font-size: 0.8rem;
            color: #b0b0b0;
            margin-top: 0.25rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(45deg, #f44336, #d32f2f);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(244, 67, 54, 0.3);
        }

        /* Alerts */
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

        /* Warning Section */
        .warning-section {
            border: 2px solid #ff9800;
            border-radius: 8px;
            padding: 1.25rem;
            background: rgba(255, 152, 0, 0.05);
        }

        .warning-title {
            color: #ff9800;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-text {
            color: #b0b0b0;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .confirm-input {
            margin: 1rem 0;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            font-size: 1.2rem;
            cursor: pointer;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .settings-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .settings-card:nth-child(2) { animation-delay: 0.1s; }
        .settings-card:nth-child(3) { animation-delay: 0.2s; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .mobile-toggle {
                display: block;
            }

            .top-bar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .user-menu {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-users"></i>
                <span>ClubMaster</span>
            </div>
            <div class="user-badge <?php echo $user['role'] === 'manager' ? 'manager-badge' : ''; ?>">
                <?php echo ucfirst($user['role']); ?>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">My Teams</div>
            <a href="team_management.php" class="nav-link">
                <i class="fas fa-tasks"></i>
                <span>Team Management</span>
            </a>
            <a href="events.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Events</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>

            <?php if ($user['role'] === 'manager'): ?>
            <div class="nav-section">Management</div>
            <a href="event_management.php" class="nav-link">
                <i class="fas fa-calendar-check"></i>
                <span>Event Management</span>
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <?php endif; ?>

            <div class="nav-section">Account</div>
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="settings.php" class="nav-link active">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Account Settings</h1>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <div class="settings-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Notification Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            <i class="fas fa-bell"></i>
                            Notification Preferences
                        </h3>
                        <p class="card-description">Choose what notifications you want to receive</p>
                    </div>
                </div>
                <div class="card-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="notifications">
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="email_notifications" name="email_notifications" 
                                       <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <div>
                                    <label for="email_notifications" class="checkbox-label">Email Notifications</label>
                                    <div class="checkbox-description">Receive general notifications via email</div>
                                </div>
                            </div>

                            <div class="checkbox-item">
                                <input type="checkbox" id="event_reminders" name="event_reminders" 
                                       <?php echo $settings['event_reminders'] ? 'checked' : ''; ?>>
                                <div>
                                    <label for="event_reminders" class="checkbox-label">Event Reminders</label>
                                    <div class="checkbox-description">Get reminders about upcoming events</div>
                                </div>
                            </div>

                            <div class="checkbox-item">
                                <input type="checkbox" id="club_updates" name="club_updates" 
                                       <?php echo $settings['club_updates'] ? 'checked' : ''; ?>>
                                <div>
                                    <label for="club_updates" class="checkbox-label">Club Updates</label>
                                    <div class="checkbox-description">Receive updates from your clubs</div>
                                </div>
                            </div>

                            <div class="checkbox-item">
                                <input type="checkbox" id="marketing_emails" name="marketing_emails" 
                                       <?php echo $settings['marketing_emails'] ? 'checked' : ''; ?>>
                                <div>
                                    <label for="marketing_emails" class="checkbox-label">Marketing Emails</label>
                                    <div class="checkbox-description">Receive promotional emails and newsletters</div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-save"></i>
                            Save Notification Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Privacy Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            <i class="fas fa-shield-alt"></i>
                            Privacy Settings
                        </h3>
                        <p class="card-description">Control who can see your information</p>
                    </div>
                </div>
                <div class="card-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="privacy">
                        
                        <div class="form-group">
                            <label class="form-label">Profile Visibility</label>
                            <select name="profile_visibility" class="form-select">
                                <option value="public" <?php echo $settings['profile_visibility'] === 'public' ? 'selected' : ''; ?>>
                                    Public - Anyone can see my profile
                                </option>
                                <option value="members" <?php echo $settings['profile_visibility'] === 'members' ? 'selected' : ''; ?>>
                                    Club Members Only - Only members of my clubs can see my profile
                                </option>
                                <option value="private" <?php echo $settings['profile_visibility'] === 'private' ? 'selected' : ''; ?>>
                                    Private - Only I can see my profile
                                </option>
                            </select>
                        </div>

                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="show_email" name="show_email" 
                                       <?php echo $settings['show_email'] ? 'checked' : ''; ?>>
                                <div>
                                    <label for="show_email" class="checkbox-label">Show Email Address</label>
                                    <div class="checkbox-description">Allow others to see your email address</div>
                                </div>
                            </div>

                            <div class="checkbox-item">
                                <input type="checkbox" id="show_phone" name="show_phone" 
                                       <?php echo $settings['show_phone'] ? 'checked' : ''; ?>>
                                <div>
                                    <label for="show_phone" class="checkbox-label">Show Phone Number</label>
                                    <div class="checkbox-description">Allow others to see your phone number</div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-save"></i>
                            Save Privacy Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Management -->
            <div class="settings-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Account Management
                        </h3>
                        <p class="card-description">Manage your account status</p>
                    </div>
                </div>
                <div class="card-content">
                    <div class="warning-section">
                        <h4 class="warning-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Deactivate Account
                        </h4>
                        <p class="warning-text">
                            Deactivating your account will prevent you from logging in and participating in club activities. 
                            Your data will be preserved, and you can reactivate your account by contacting support.
                        </p>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate your account? This action cannot be undone.');">
                            <input type="hidden" name="action" value="deactivate">
                            <div class="form-group confirm-input">
                                <label class="form-label">Type "DEACTIVATE" to confirm:</label>
                                <input type="text" name="confirm_deactivate" class="form-input" 
                                       placeholder="Type DEACTIVATE" required>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-user-slash"></i>
                                Deactivate Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>