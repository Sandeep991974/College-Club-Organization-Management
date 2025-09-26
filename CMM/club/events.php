<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT role, username, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || !in_array($user['role'], ['manager', 'member'])) {
        header('Location: ../login.php');
        exit();
    }
    
    // Ensure managers always have at least one team
    if ($user && $user['role'] === 'manager') {
        // Check if the user already has any teams (either as a member or as creator)
        $hasTeamsStmt = $pdo->prepare("\n            SELECT COUNT(*) AS cnt\n            FROM clubs c\n            LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? AND cm.status = 'active'\n            WHERE cm.user_id IS NOT NULL OR c.created_by = ?\n        ");
        $hasTeamsStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $hasTeams = (int)$hasTeamsStmt->fetchColumn();

        if ($hasTeams === 0) {
            // Create a default team for this manager and add them as president
            $defaultName = 'Team of ' . ($user['first_name'] ? $user['first_name'] : $user['username']);
            $createClub = $pdo->prepare("INSERT INTO clubs (name, description, category, location, contact_email, member_count, created_by, status) VALUES (?, ?, 'General', 'N/A', ?, 1, ?, 'active')");
            $createClub->execute([$defaultName, 'Auto-created team for manager', ($user['username'] ?? 'user') . '@example.com', $_SESSION['user_id']]);
            $newClubId = (int)$pdo->lastInsertId();

            // Add membership
            $addMembership = $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status, joined_date) VALUES (?, ?, 'president', 'active', CURDATE())");
            $addMembership->execute([$newClubId, $_SESSION['user_id']]);
        }
    }

    // Get user's clubs for dropdown (either member or creator)
    $userClubs = $pdo->prepare("\r
        SELECT DISTINCT c.id, c.name\r
        FROM clubs c \r
        LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? AND cm.status = 'active'\r
        WHERE cm.user_id IS NOT NULL OR c.created_by = ?\r
        ORDER BY c.name\r
    ");
    $userClubs->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $clubs = $userClubs->fetchAll();
    
    // Debug: Log club information
    error_log("User ID: " . $_SESSION['user_id'] . ", Clubs found: " . count($clubs));
    if (empty($clubs)) {
        error_log("No clubs found for user. This might cause issues with event creation.");
    }
    
    // Handle form submission
    $success = '';
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $club_id = $_POST['club_id'] ?? '';
        $event_name = trim($_POST['event_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_type = $_POST['event_type'] ?? 'meeting';
        $start_date = $_POST['start_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $max_attendees = $_POST['max_attendees'] ?? null;
        $registration_deadline = $_POST['registration_deadline'] ?? null;
        $fee = $_POST['fee'] ?? 0;
        
        // Debug information
        error_log("Form submission data: club_id=$club_id, event_name=$event_name, description length=" . strlen($description) . ", start_date=$start_date, start_time=$start_time");
        
        // Validation with specific field checking
        $missing_fields = [];
        // Only require club selection for members (not managers)
        if ($user['role'] !== 'manager' && !empty($clubs) && empty($club_id)) $missing_fields[] = 'Team selection';
        if (empty($event_name)) $missing_fields[] = 'Event name';
        if (empty($description)) $missing_fields[] = 'Event description';
        if (empty($start_date)) $missing_fields[] = 'Start date';
        if (empty($start_time)) $missing_fields[] = 'Start time';
        
        // Check if user has clubs to create events for
        if (empty($clubs)) {
            // For managers, this should never happen since we auto-create teams
            if ($user['role'] === 'manager') {
                $error = 'System error: Unable to create or access teams. Please contact support.';
            } else {
                $error = 'You need to be a member of at least one team to create events. Please contact an administrator to join a team first.';
            }
        } elseif (!empty($missing_fields)) {
            $error = 'Please fill in these required fields: ' . implode(', ', $missing_fields);
        } else {
            // Handle file upload
            $photo_path = null;
            if (isset($_FILES['event_photo']) && $_FILES['event_photo']['error'] == 0) {
                $upload_dir = '../uploads/events/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['event_photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $filename = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['event_photo']['tmp_name'], $target_path)) {
                        $photo_path = 'uploads/events/' . $filename;
                    }
                }
            }
            
            // Combine date and time
            $start_datetime = $start_date . ' ' . $start_time;
            $end_datetime = $end_date ? ($end_date . ' ' . ($end_time ?: $start_time)) : $start_datetime;
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO events (club_id, title, description, event_type, start_datetime, end_datetime, 
                                      location, max_attendees, registration_deadline, fee, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)
                ");
                
                if ($stmt->execute([$club_id, $event_name, $description, $event_type, $start_datetime, 
                                  $end_datetime, $location, $max_attendees, $registration_deadline, $fee, $_SESSION['user_id']])) {
                    $success = 'Event registered successfully!';
                    // Clear form data
                    $event_name = $description = $location = '';
                } else {
                    $error = 'Failed to register event. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error occurred';
                error_log("Event registration error: " . $e->getMessage());
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Events page error: " . $e->getMessage());
    $error = "Database error occurred";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration - ClubMaster</title>
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
            min-height: 100vh;
        }

        /* Background Animation */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            background: radial-gradient(ellipse at center, rgba(102, 126, 234, 0.1) 0%, rgba(10, 10, 10, 1) 70%);
        }

        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .shape {
            position: absolute;
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        .shape-1 {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 80%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 150px;
            height: 150px;
            top: 60%;
            left: 10%;
            animation-delay: 2s;
        }

        .shape-3 {
            width: 100px;
            height: 100px;
            top: 80%;
            left: 70%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
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

        /* Main Content */
        .main-content {
            margin-left: 200px;
            min-height: 100vh;
            padding: 2rem;
        }

        /* Event Container */
        .event-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: slideUp 1s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .event-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo i {
            margin-right: 0.5rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
        }

        .event-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #ffffff, #b0b0b0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .event-subtitle {
            color: #b0b0b0;
            font-size: 1rem;
        }

        /* Form Styles */
        .event-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            position: relative;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ffffff;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group label .required {
            color: #ff6b6b;
            margin-left: 0.25rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: 'Poppins', sans-serif;
        }

        .form-select, .form-input[type="date"], .form-input[type="time"], .form-input[type="number"] {
            padding-left: 3rem;
            cursor: pointer;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: #888;
        }

        .form-select option {
            background: #2a2a2a;
            color: #ffffff;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-group:focus-within .input-icon {
            color: #667eea;
        }

        /* File Upload */
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.08);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: #b0b0b0;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .file-label:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: #667eea;
            color: #667eea;
        }

        .file-info {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #888;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-primary:disabled {
            background: #444;
            color: #888;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-primary:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 1px solid #667eea;
            margin-top: 1rem;
            width: 100%;
        }

        .btn-secondary:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            animation: slideDown 0.3s ease;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #b0b0b0;
            text-decoration: none;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-btn:hover {
            color: #667eea;
        }

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

            .event-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
                max-width: none;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .event-title {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .event-container {
                padding: 1.5rem 1rem;
            }

            .event-title {
                font-size: 1.6rem;
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
            <a href="events.php" class="nav-link active">
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
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <div class="bg-animation">
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="event-container">
        <div class="event-header">
            <div class="logo">
                <i class="fas fa-calendar-plus"></i>
                <span>Event Registration</span>
            </div>
            <h1 class="event-title">Create New Event</h1>
            <p class="event-subtitle">Fill in the details to register your event</p>
        </div>

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

        <form class="event-form" method="POST" enctype="multipart/form-data" id="eventForm">
            <!-- Club Selection - Hidden for managers, shown for members -->
            <?php if (!empty($clubs) && $user['role'] !== 'manager'): ?>
            <div class="form-group">
                <label for="club_id">Select Team<span class="required">*</span></label>
                <div style="position: relative;">
                    <i class="input-icon fas fa-users"></i>
                    <select id="club_id" name="club_id" class="form-select" required>
                        <option value="">Choose a team</option>
                        <?php foreach ($clubs as $club): ?>
                        <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php elseif ($user['role'] === 'manager' && !empty($clubs)): ?>
            <!-- Hidden input for managers - auto-select their first team -->
            <input type="hidden" name="club_id" value="<?php echo $clubs[0]['id']; ?>">
            <?php elseif ($user['role'] !== 'manager'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                You are not a member of any teams yet. Please contact an administrator to join a team before creating events.
            </div>
            <?php endif; ?>

            <!-- Event Photo -->
            <div class="form-group full-width">
                <label for="event_photo">Event Photo</label>
                <div class="file-upload">
                    <input type="file" id="event_photo" name="event_photo" class="file-input" accept="image/*">
                    <label for="event_photo" class="file-label">
                        <div>
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <div>Click to upload event photo</div>
                            <div class="file-info">JPG, PNG, GIF up to 10MB</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Event Name and Type -->
            <div class="form-row">
                <div class="form-group">
                    <label for="event_name">Event Name<span class="required">*</span></label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-tag"></i>
                        <input 
                            type="text" 
                            id="event_name" 
                            name="event_name" 
                            class="form-input" 
                            placeholder="Enter event name"
                            value="<?php echo htmlspecialchars($event_name ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="event_type">Event Type</label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-list"></i>
                        <select id="event_type" name="event_type" class="form-select">
                            <option value="meeting">Meeting</option>
                            <option value="workshop">Workshop</option>
                            <option value="social">Social Event</option>
                            <option value="competition">Competition</option>
                            <option value="fundraiser">Fundraiser</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group full-width">
                <label for="description">Event Description<span class="required">*</span></label>
                <div style="position: relative;">
                    <i class="input-icon fas fa-align-left" style="top: 1.2rem;"></i>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-textarea" 
                        placeholder="Describe your event in detail"
                        required
                    ><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Start Date and Time -->
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Start Date<span class="required">*</span></label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-calendar"></i>
                        <input 
                            type="date" 
                            id="start_date" 
                            name="start_date" 
                            class="form-input"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="start_time">Start Time<span class="required">*</span></label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-clock"></i>
                        <input 
                            type="time" 
                            id="start_time" 
                            name="start_time" 
                            class="form-input"
                            required
                        >
                    </div>
                </div>
            </div>

            <!-- End Date and Time -->
            <div class="form-row">
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-calendar"></i>
                        <input 
                            type="date" 
                            id="end_date" 
                            name="end_date" 
                            class="form-input"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-clock"></i>
                        <input 
                            type="time" 
                            id="end_time" 
                            name="end_time" 
                            class="form-input"
                        >
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="form-group full-width">
                <label for="location">Location</label>
                <div style="position: relative;">
                    <i class="input-icon fas fa-map-marker-alt"></i>
                    <input 
                        type="text" 
                        id="location" 
                        name="location" 
                        class="form-input" 
                        placeholder="Event location or venue"
                        value="<?php echo htmlspecialchars($location ?? ''); ?>"
                    >
                </div>
            </div>

            <!-- Max Attendees and Registration Deadline -->
            <div class="form-row">
                <div class="form-group">
                    <label for="max_attendees">Max Attendees</label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-users"></i>
                        <input 
                            type="number" 
                            id="max_attendees" 
                            name="max_attendees" 
                            class="form-input" 
                            placeholder="Leave empty for unlimited"
                            min="1"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="registration_deadline">Registration Deadline</label>
                    <div style="position: relative;">
                        <i class="input-icon fas fa-calendar-times"></i>
                        <input 
                            type="datetime-local" 
                            id="registration_deadline" 
                            name="registration_deadline" 
                            class="form-input"
                        >
                    </div>
                </div>
            </div>

            <!-- Fee -->
            <div class="form-group">
                <label for="fee">Event Fee (₹)</label>
                <div style="position: relative;">
                    <i class="input-icon fas fa-rupee-sign"></i>
                    <input 
                        type="number" 
                        id="fee" 
                        name="fee" 
                        class="form-input" 
                        placeholder="0.00"
                        min="0"
                        step="0.01"
                        value="0"
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="eventBtn" <?php echo (empty($clubs) && $user['role'] !== 'manager') ? 'disabled' : ''; ?>>
                <i class="fas fa-calendar-plus"></i>
                <?php echo (empty($clubs) && $user['role'] !== 'manager') ? 'Join a Team First' : 'Register Event'; ?>
            </button>

            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Cancel
            </a>
        </form>
        </div>
    </main>

    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('mobileToggle');
            
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // File upload preview
        document.getElementById('event_photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = document.querySelector('.file-label');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    label.innerHTML = `
                        <div>
                            <img src="${e.target.result}" style="max-width: 200px; max-height: 150px; border-radius: 8px; margin-bottom: 0.5rem;">
                            <div style="color: #4caf50;"><i class="fas fa-check-circle"></i> Photo selected: ${file.name}</div>
                            <div class="file-info">Click to change photo</div>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (endDate && endDate < startDate) {
                e.preventDefault();
                alert('End date cannot be before start date');
                return;
            }
            
            const submitBtn = document.getElementById('eventBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering Event...';
            submitBtn.disabled = true;
        });

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        document.getElementById('end_date').min = today;
        
        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });

        console.log('📅 Event Registration page initialized successfully!');
    </script>
</body>
</html>