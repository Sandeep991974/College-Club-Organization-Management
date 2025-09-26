<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Get clubs managed by this user
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.*, COUNT(cm.user_id) as member_count
        FROM clubs c 
        LEFT JOIN club_members cm ON c.id = cm.club_id 
        WHERE c.manager_id = ? OR EXISTS (
            SELECT 1 FROM club_members cm2 
            WHERE cm2.club_id = c.id 
            AND cm2.user_id = ? 
            AND cm2.role IN ('manager', 'co_manager')
        )
        GROUP BY c.id
        ORDER BY c.name
    ");
    $stmt->execute([$user_id, $user_id]);
    $clubs = $stmt->fetchAll();

    // Set events count to 0 for now (events table may not exist or have different structure)
    $totalEvents = 0;

    // Get total members across all managed clubs
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT cm.user_id) as total_members
        FROM club_members cm
        INNER JOIN clubs c ON cm.club_id = c.id
        WHERE c.manager_id = ? OR EXISTS (
            SELECT 1 FROM club_members cm2 
            WHERE cm2.club_id = c.id 
            AND cm2.user_id = ? 
            AND cm2.role IN ('manager', 'co_manager')
        )
    ");
    $stmt->execute([$user_id, $user_id]);
    $totalMembers = $stmt->fetchColumn();

    // Get recent activities - simplified to avoid column issues
    $stmt = $pdo->prepare("
        SELECT 
            'club' as type,
            c.name as title,
            NOW() as date_time,
            c.name as club_name
        FROM clubs c
        WHERE c.manager_id = ? OR EXISTS (
            SELECT 1 FROM club_members cm 
            WHERE cm.club_id = c.id 
            AND cm.user_id = ? 
            AND cm.role IN ('manager', 'co_manager')
        )
        ORDER BY c.name
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $recentActivities = $stmt->fetchAll();

} catch (Exception $e) {
    $error = "Error loading reports: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ClubMaster</title>
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

        .page-header {
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

        .page-subtitle {
            color: #b0b0b0;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
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
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            margin-right: 1rem;
        }

        .stat-icon.clubs { background: linear-gradient(45deg, #667eea, #764ba2); }
        .stat-icon.events { background: linear-gradient(45deg, #ff9800, #f57c00); }
        .stat-icon.members { background: linear-gradient(45deg, #4caf50, #45a049); }

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

        /* List Items */
        .club-item, .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .club-item:hover, .activity-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .club-item:last-child, .activity-item:last-child {
            margin-bottom: 0;
        }

        .club-info h4, .activity-info h5 {
            color: #ffffff;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .club-info p, .activity-info p {
            color: #b0b0b0;
            font-size: 0.85rem;
        }

        .club-stats {
            margin-left: auto;
            text-align: right;
            color: #667eea;
            font-weight: 600;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .activity-date {
            margin-left: auto;
            color: #999;
            font-size: 0.8rem;
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

        .stat-card, .content-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }

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

            .page-header {
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
            <div class="user-badge">
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
            <a href="reports.php" class="nav-link active">
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
        <div class="page-header">
            <div>
                <h1 class="page-title">Analytics & Reports</h1>
                <p class="page-subtitle">Overview of your club management performance</p>
            </div>
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

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon clubs">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo count($clubs); ?></div>
                        <div class="stat-label">Managed Clubs</div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon events">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo $totalEvents; ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon members">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo $totalMembers; ?></div>
                        <div class="stat-label">Total Members</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Managed Clubs -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Your Managed Clubs</h3>
                </div>
                <div class="card-content">
                    <?php if (!empty($clubs)): ?>
                        <?php foreach ($clubs as $club): ?>
                        <div class="club-item">
                            <div class="club-info">
                                <h4><?php echo htmlspecialchars($club['name']); ?></h4>
                                <p><?php echo htmlspecialchars($club['category']); ?></p>
                            </div>
                            <div class="club-stats">
                                <?php echo $club['member_count']; ?> members
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No clubs found</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Activities</h3>
                </div>
                <div class="card-content">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="activity-info">
                                <h5><?php echo htmlspecialchars($activity['title']); ?></h5>
                                <p><?php echo htmlspecialchars($activity['club_name']); ?></p>
                            </div>
                            <div class="activity-date">
                                <?php echo date('M d', strtotime($activity['date_time'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No recent activities</p>
                    <?php endif; ?>
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