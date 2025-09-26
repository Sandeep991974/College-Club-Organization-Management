<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT role, username, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'admin') {
        header('Location: ../login.php');
        exit();
    }
    
    // Get dashboard statistics
    $stats = getDatabaseStats($pdo);
    
    // Get recent activities
    $recentUsers = $pdo->query("SELECT username, first_name, last_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recentClubs = $pdo->query("SELECT name, category, member_count, created_at FROM clubs ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Database error occurred";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ClubMaster</title>
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

        .admin-badge {
            display: inline-block;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.5rem;
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .stat-title {
            color: #b0b0b0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.4rem;
        }

        .stat-change {
            font-size: 0.85rem;
            color: #4caf50;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-top: 1.25rem;
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

        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .activity-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
            font-size: 0.85rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-name {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .activity-details {
            font-size: 0.85rem;
            color: #b0b0b0;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #888;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 1rem;
        }

        .role-admin { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .role-manager { background: rgba(102, 126, 234, 0.2); color: #667eea; }
        .role-member { background: rgba(76, 175, 80, 0.2); color: #4caf50; }

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

            .stats-grid {
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

        /* Loading Animation */
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

        .stat-card, .content-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.3s; }
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
                <i class="fas fa-crown"></i>
                <span>ClubMaster</span>
            </div>
            <div class="admin-badge">Administrator</div>
        </div>

        <nav class="sidebar-nav">
            <a href="#" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">User Management</div>
            <a href="#" class="nav-link">
                <i class="fas fa-users"></i>
                <span>All Users</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-user-tie"></i>
                <span>Club Managers</span>
            </a>

            <div class="nav-section">Club Management</div>
            <a href="#" class="nav-link">
                <i class="fas fa-building"></i>
                <span>All Clubs</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Events</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>

            <div class="nav-section">System</div>
            <a href="#" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Admin Dashboard</h1>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">System Administrator</div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Users</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                <div class="stat-change">
                    <i class="fas fa-arrow-up"></i> +12% from last month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Active Clubs</div>
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_clubs'] ?? 0); ?></div>
                <div class="stat-change">
                    <i class="fas fa-arrow-up"></i> +8% from last month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Events</div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_events'] ?? 0); ?></div>
                <div class="stat-change">
                    <i class="fas fa-arrow-up"></i> +25% from last month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Active Members</div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_members'] ?? 0); ?></div>
                <div class="stat-change">
                    <i class="fas fa-arrow-up"></i> +15% from last month
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Users -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Users</h3>
                    <a href="#" class="view-all">View All</a>
                </div>
                <div class="activity-list">
                    <?php foreach ($recentUsers as $recentUser): ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <?php echo strtoupper(substr($recentUser['first_name'], 0, 1)); ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-name"><?php echo htmlspecialchars($recentUser['first_name'] . ' ' . $recentUser['last_name']); ?></div>
                            <div class="activity-details">@<?php echo htmlspecialchars($recentUser['username']); ?></div>
                        </div>
                        <div class="role-badge role-<?php echo $recentUser['role']; ?>">
                            <?php echo ucfirst($recentUser['role']); ?>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M d', strtotime($recentUser['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Clubs -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Clubs</h3>
                    <a href="#" class="view-all">View All</a>
                </div>
                <div class="activity-list">
                    <?php foreach ($recentClubs as $club): ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <?php echo strtoupper(substr($club['name'], 0, 1)); ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-name"><?php echo htmlspecialchars($club['name']); ?></div>
                            <div class="activity-details"><?php echo htmlspecialchars($club['category']); ?> • <?php echo $club['member_count']; ?> members</div>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M d', strtotime($club['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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

        // Dynamic greeting based on time
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

        console.log('👑 Admin Dashboard initialized successfully!');
    </script>
</body>
</html>