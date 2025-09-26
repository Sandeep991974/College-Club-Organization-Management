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
    
    // Get user's clubs to filter events
    $userClubs = $pdo->prepare("
        SELECT DISTINCT c.id, c.name
        FROM clubs c 
        LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? AND cm.status = 'active'
        WHERE cm.user_id IS NOT NULL OR c.created_by = ?
        ORDER BY c.name
    ");
    $userClubs->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $clubs = $userClubs->fetchAll();
    
    // Get club IDs for filtering events
    $clubIds = array_column($clubs, 'id');
    
    // Handle event actions (delete, update status)
    $success = '';
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            $eventId = $_POST['event_id'] ?? 0;
            
            switch ($_POST['action']) {
                case 'delete':
                    try {
                        // Check if user has permission to delete this event
                        $checkStmt = $pdo->prepare("
                            SELECT e.id FROM events e 
                            JOIN clubs c ON e.club_id = c.id 
                            LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? 
                            WHERE e.id = ? AND (c.created_by = ? OR cm.user_id IS NOT NULL)
                        ");
                        $checkStmt->execute([$_SESSION['user_id'], $eventId, $_SESSION['user_id']]);
                        
                        if ($checkStmt->fetch()) {
                            $deleteStmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                            if ($deleteStmt->execute([$eventId])) {
                                $success = 'Event deleted successfully!';
                            } else {
                                $error = 'Failed to delete event.';
                            }
                        } else {
                            $error = 'You do not have permission to delete this event.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error occurred.';
                        error_log("Delete event error: " . $e->getMessage());
                    }
                    break;
                    
                case 'toggle_status':
                    try {
                        $newStatus = $_POST['new_status'] ?? 'draft';
                        $updateStmt = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
                        if ($updateStmt->execute([$newStatus, $eventId])) {
                            $success = 'Event status updated successfully!';
                        } else {
                            $error = 'Failed to update event status.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error occurred.';
                        error_log("Update event status error: " . $e->getMessage());
                    }
                    break;
            }
        }
    }
    
    // Get events for user's clubs
    $events = [];
    if (!empty($clubIds)) {
        $placeholders = str_repeat('?,', count($clubIds) - 1) . '?';
        $eventsStmt = $pdo->prepare("
            SELECT e.*, c.name as club_name,
                   (SELECT COUNT(*) FROM event_attendees ea WHERE ea.event_id = e.id) as attendee_count
            FROM events e 
            JOIN clubs c ON e.club_id = c.id 
            WHERE e.club_id IN ($placeholders)
            ORDER BY e.start_datetime DESC
        ");
        $eventsStmt->execute($clubIds);
        $events = $eventsStmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Event management page error: " . $e->getMessage());
    $error = "Database error occurred";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - ClubMaster</title>
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

        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #ffffff, #b0b0b0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            color: #b0b0b0;
            font-size: 1.1rem;
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .event-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .event-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-published {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }

        .status-draft {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-completed {
            background: rgba(33, 150, 243, 0.2);
            color: #2196f3;
        }

        .status-cancelled {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }

        .event-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #ffffff;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: #b0b0b0;
            font-size: 0.85rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            width: 16px;
            text-align: center;
            color: #667eea;
        }

        .event-description {
            color: #cccccc;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .event-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
        }

        .btn-success {
            background: linear-gradient(45deg, #4caf50, #45a049);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(45deg, #ff9800, #e68900);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(45deg, #f44336, #d32f2f);
            color: white;
        }

        .btn-info {
            background: linear-gradient(45deg, #2196f3, #1976d2);
            color: white;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #999;
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

            .events-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 2rem;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
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
            <a href="event_management.php" class="nav-link active">
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
        <div class="page-header">
            <h1 class="page-title">Event Management</h1>
            <p class="page-subtitle">Manage all events created by your teams</p>
        </div>

        <div class="action-bar">
            <a href="events.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Create New Event
            </a>
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

        <?php if (!empty($events)): ?>
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <div class="event-status status-<?php echo $event['status']; ?>">
                        <?php echo ucfirst($event['status']); ?>
                    </div>

                    <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>

                    <div class="event-meta">
                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo htmlspecialchars($event['club_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('M d, Y', strtotime($event['start_datetime'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('h:i A', strtotime($event['start_datetime'])); ?></span>
                        </div>
                        <?php if ($event['location']): ?>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($event['location']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <i class="fas fa-user-check"></i>
                            <span><?php echo $event['attendee_count']; ?> attendees</span>
                        </div>
                    </div>

                    <div class="event-description">
                        <?php echo htmlspecialchars($event['description']); ?>
                    </div>

                    <div class="event-actions">
                        <?php if ($event['status'] === 'draft'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="new_status" value="published">
                            <button type="submit" class="btn btn-success btn-small">
                                <i class="fas fa-eye"></i> Publish
                            </button>
                        </form>
                        <?php elseif ($event['status'] === 'published'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="new_status" value="completed">
                            <button type="submit" class="btn btn-info btn-small">
                                <i class="fas fa-check"></i> Mark Complete
                            </button>
                        </form>
                        <?php endif; ?>

                        <button class="btn btn-warning btn-small" onclick="editEvent(<?php echo $event['id']; ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?')">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger btn-small">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Events Found</h3>
                <p>You haven't created any events yet. Click "Create New Event" to get started!</p>
            </div>
        <?php endif; ?>
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

        // Edit event function (placeholder)
        function editEvent(eventId) {
            // You can implement edit functionality here
            // For now, redirect to events page with edit parameter
            window.location.href = 'events.php?edit=' + eventId;
        }

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

        console.log('📅 Event Management page initialized successfully!');
    </script>
</body>
</html>