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
    
    // Get user's clubs
    $userClubs = $pdo->prepare("
        SELECT c.*, cm.role as member_role, cm.status as member_status
        FROM clubs c 
        JOIN club_members cm ON c.id = cm.club_id 
        WHERE cm.user_id = ? AND cm.status = 'active'
        ORDER BY c.name
    ");
    $userClubs->execute([$_SESSION['user_id']]);
    $clubs = $userClubs->fetchAll();
    
    // Get recent events for user's clubs
    $clubIds = array_column($clubs, 'id');
    $recentEvents = [];
    if (!empty($clubIds)) {
        $placeholders = str_repeat('?,', count($clubIds) - 1) . '?';
        $eventsQuery = $pdo->prepare("
            SELECT e.*, c.name as club_name
            FROM events e 
            JOIN clubs c ON e.club_id = c.id
            WHERE e.club_id IN ($placeholders) AND e.start_datetime >= NOW()
            ORDER BY e.start_datetime ASC
            LIMIT 5
        ");
        $eventsQuery->execute($clubIds);
        $recentEvents = $eventsQuery->fetchAll();
    }
    
    // Get announcements
    $announcements = [];
    if (!empty($clubIds)) {
        $placeholders = str_repeat('?,', count($clubIds) - 1) . '?';
        $announcementsQuery = $pdo->prepare("
            SELECT a.*, c.name as club_name
            FROM announcements a 
            JOIN clubs c ON a.club_id = c.id
            WHERE a.club_id IN ($placeholders) AND a.status = 'published'
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $announcementsQuery->execute($clubIds);
        $announcements = $announcementsQuery->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Club dashboard error: " . $e->getMessage());
    $error = "Database error occurred";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Dashboard - ClubMaster</title>
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

        /* Club Cards Grid */
        .clubs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .club-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .club-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .club-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .club-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .club-name {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.4rem;
        }

        .club-category {
            color: #b0b0b0;
            font-size: 0.9rem;
        }

        .club-role {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .club-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .club-stat {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #b0b0b0;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.875rem;
            padding-bottom: 0.625rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
        }

        .view-all {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .view-all:hover {
            color: #764ba2;
        }

        /* Event Items */
        .event-list, .announcement-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .event-item, .announcement-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .event-item:hover, .announcement-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .event-date {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .event-day {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            line-height: 1;
        }

        .event-month {
            font-size: 0.75rem;
            color: white;
            text-transform: uppercase;
        }

        .event-content, .announcement-content {
            flex: 1;
        }

        .event-title, .announcement-title {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .event-details, .announcement-details {
            font-size: 0.85rem;
            color: #b0b0b0;
            margin-bottom: 0.25rem;
        }

        .event-club, .announcement-club {
            font-size: 0.8rem;
            color: #667eea;
        }

        .announcement-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(45deg, #ff9800, #f57c00);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .urgent-announcement .announcement-icon {
            background: linear-gradient(45deg, #ff5722, #d32f2f);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #333;
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

            .content-grid {
                grid-template-columns: 1fr;
            }

            .clubs-grid {
                grid-template-columns: 1fr;
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

        .club-card, .content-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .club-card:nth-child(2) { animation-delay: 0.1s; }
        .club-card:nth-child(3) { animation-delay: 0.2s; }
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
            <a href="#" class="nav-link active">
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
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">
                <?php echo $user['role'] === 'manager' ? 'Club Manager Dashboard' : 'Member Dashboard'; ?>
            </h1>
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

        <!-- My Clubs -->
        <?php if (!empty($clubs)): ?>
        <div class="clubs-grid">
            <?php foreach ($clubs as $club): ?>
            <div class="club-card">
                <div class="club-header">
                    <div>
                        <div class="club-name"><?php echo htmlspecialchars($club['name']); ?></div>
                        <div class="club-category"><?php echo htmlspecialchars($club['category']); ?></div>
                    </div>
                    <div class="club-role"><?php echo ucfirst(str_replace('_', ' ', $club['member_role'])); ?></div>
                </div>
                <p class="club-description"><?php echo htmlspecialchars(substr($club['description'], 0, 100)); ?>...</p>
                <div class="club-stats">
                    <div class="club-stat">
                        <span class="stat-number"><?php echo $club['member_count']; ?></span>
                        <span class="stat-label">Members</span>
                    </div>
                    <div class="club-stat">
                        <span class="stat-number">Active</span>
                        <span class="stat-label">Status</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="content-card">
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <h3>No Clubs Found</h3>
                <p>You're not a member of any clubs yet. Join a club to get started!</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Upcoming Events -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Upcoming Events</h3>
                    <a href="#" class="view-all">View All</a>
                </div>
                <?php if (!empty($recentEvents)): ?>
                <div class="event-list">
                    <?php foreach ($recentEvents as $event): ?>
                    <div class="event-item">
                        <div class="event-date">
                            <div class="event-day"><?php echo date('d', strtotime($event['start_datetime'])); ?></div>
                            <div class="event-month"><?php echo date('M', strtotime($event['start_datetime'])); ?></div>
                        </div>
                        <div class="event-content">
                            <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                            <div class="event-details">
                                <?php echo date('g:i A', strtotime($event['start_datetime'])); ?>
                                <?php if ($event['location']): ?>
                                • <?php echo htmlspecialchars($event['location']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="event-club"><?php echo htmlspecialchars($event['club_name']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar"></i>
                    <p>No upcoming events</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Announcements -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Announcements</h3>
                    <a href="#" class="view-all">View All</a>
                </div>
                <?php if (!empty($announcements)): ?>
                <div class="announcement-list">
                    <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item <?php echo $announcement['priority'] === 'high' ? 'urgent-announcement' : ''; ?>">
                        <div class="announcement-icon">
                            <i class="fas <?php echo $announcement['priority'] === 'high' ? 'fa-exclamation' : 'fa-bullhorn'; ?>"></i>
                        </div>
                        <div class="announcement-content">
                            <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                            <div class="announcement-details"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)); ?>...</div>
                            <div class="announcement-club"><?php echo htmlspecialchars($announcement['club_name']); ?> • <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <p>No announcements</p>
                </div>
                <?php endif; ?>
            </div>
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

        // Dynamic greeting
        function updateGreeting() {
            const hour = new Date().getHours();
            let greeting = 'Good Morning';
            
            if (hour >= 12 && hour < 17) {
                greeting = 'Good Afternoon';
            } else if (hour >= 17) {
                greeting = 'Good Evening';
            }
            
            const userInfo = document.querySelector('.user-name');
            if (userInfo) {
                userInfo.textContent = `${greeting}, <?php echo htmlspecialchars($user['first_name']); ?>`;
            }
        }

        updateGreeting();

        console.log('🏢 Club Dashboard initialized successfully!');
    </script>
</body>
</html>