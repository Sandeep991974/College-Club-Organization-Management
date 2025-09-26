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
    $stmt = $pdo->prepare("SELECT role, username, email, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || !in_array($user['role'], ['manager', 'member'])) {
        header('Location: ../login.php');
        exit();
    }
    
    // Handle success message from redirect
    $success = '';
    $error = '';
    
    if (isset($_GET['success'])) {
        $success = $_GET['success'];
    }
    
    if (isset($_GET['error'])) {
        $error = $_GET['error'];
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_team':
                    $team_name = trim($_POST['team_name'] ?? '');
                    $category = $_POST['category'] ?? 'General';
                    $contact_email = trim($_POST['contact_email'] ?? '');
                    $contact_phone = trim($_POST['contact_phone'] ?? '');
                    $member_count = intval($_POST['member_count'] ?? 1);
                    
                    if (empty($team_name)) {
                        header('Location: team_management.php?error=' . urlencode('Team name is required'));
                        exit();
                    } elseif ($member_count < 1 || $member_count > 50) {
                        header('Location: team_management.php?error=' . urlencode('Number of members must be between 1 and 50'));
                        exit();
                    } else {
                        // Validate member data
                        $members_valid = true;
                        $member_errors = [];
                        
                        for ($i = 1; $i <= $member_count; $i++) {
                            $member_name = trim($_POST["member_name_$i"] ?? '');
                            $member_email = trim($_POST["member_email_$i"] ?? '');
                            
                            if (empty($member_name)) {
                                $member_errors[] = "Member $i name is required";
                                $members_valid = false;
                            }
                            if (empty($member_email)) {
                                $member_errors[] = "Member $i email is required";
                                $members_valid = false;
                            }
                        }
                        
                        if (!$members_valid) {
                            header('Location: team_management.php?error=' . urlencode(implode(', ', $member_errors)));
                            exit();
                        } else {
                            try {
                                $pdo->beginTransaction();
                                
                                // Create team
                                $createStmt = $pdo->prepare("INSERT INTO clubs (name, description, category, contact_email, contact_phone, member_count, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                                if ($createStmt->execute([$team_name, 'Team created via ClubMaster', $category, $contact_email, $contact_phone, $member_count, $_SESSION['user_id']])) {
                                    $teamId = $pdo->lastInsertId();
                                    
                                    // Add creator as president (first member)
                                    $memberStmt = $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status, joined_date) VALUES (?, ?, 'president', 'active', CURDATE())");
                                    $memberStmt->execute([$teamId, $_SESSION['user_id']]);
                                    
                                    // Add other members
                                    for ($i = 1; $i <= $member_count; $i++) {
                                        $member_name = trim($_POST["member_name_$i"]);
                                        $member_email = trim($_POST["member_email_$i"]);
                                        $member_phone = trim($_POST["member_phone_$i"] ?? '');
                                        $member_course = trim($_POST["member_course_$i"] ?? '');
                                        $member_department = trim($_POST["member_department_$i"] ?? '');
                                        
                                        // Check if this is the creator's info (skip adding again)
                                        if ($member_email === $user['email'] || $member_email === $contact_email) {
                                            continue;
                                        }
                                        
                                        // Check if user exists
                                        $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                                        $userStmt->execute([$member_email]);
                                        $existingUser = $userStmt->fetch();
                                        
                                        if (!$existingUser) {
                                            // Create new user
                                            $username = strtolower(str_replace(' ', '_', $member_name)) . '_' . rand(100, 999);
                                            $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
                                            $names = explode(' ', $member_name, 2);
                                            $firstName = $names[0];
                                            $lastName = isset($names[1]) ? $names[1] : '';
                                            
                                            $insertUserStmt = $pdo->prepare(
                                                "INSERT INTO users (username, email, password, first_name, last_name, phone, role, status, email_verified, course, department) 
                                                VALUES (?, ?, ?, ?, ?, ?, 'member', 'active', TRUE, ?, ?)"
                                            );
                                            $insertUserStmt->execute([$username, $member_email, $defaultPassword, $firstName, $lastName, $member_phone, $member_course, $member_department]);
                                            $userId = $pdo->lastInsertId();
                                        } else {
                                            $userId = $existingUser['id'];
                                            // Update user details if provided
                                            if ($member_phone || $member_course || $member_department) {
                                                $updateStmt = $pdo->prepare(
                                                    "UPDATE users 
                                                    SET phone = COALESCE(NULLIF(?, ''), phone), 
                                                        course = COALESCE(NULLIF(?, ''), course), 
                                                        department = COALESCE(NULLIF(?, ''), department)
                                                    WHERE id = ?"
                                                );
                                                $updateStmt->execute([$member_phone, $member_course, $member_department, $userId]);
                                            }
                                        }
                                        
                                        // Add to team
                                        $addMemberStmt = $pdo->prepare(
                                            "INSERT INTO club_members (club_id, user_id, role, status, joined_date) 
                                            VALUES (?, ?, 'member', 'active', CURDATE())
                                            ON DUPLICATE KEY UPDATE status = 'active'"
                                        );
                                        $addMemberStmt->execute([$teamId, $userId]);
                                    }
                                    
                                    $pdo->commit();
                                    // Redirect to prevent form resubmission on refresh
                                    header('Location: team_management.php?success=' . urlencode('Team and all members created successfully!'));
                                    exit();
                                } else {
                                    $pdo->rollBack();
                                    header('Location: team_management.php?error=' . urlencode('Failed to create team'));
                                    exit();
                                }
                            } catch (PDOException $e) {
                                $pdo->rollBack();
                                error_log("Create team error: " . $e->getMessage());
                                header('Location: team_management.php?error=' . urlencode('Database error occurred'));
                                exit();
                            }
                        }
                    }
                    break;
                
                case 'edit_team':
                    $team_id = $_POST['team_id'] ?? 0;
                    $team_name = trim($_POST['team_name'] ?? '');
                    $category = $_POST['category'] ?? 'General';
                    $contact_email = trim($_POST['contact_email'] ?? '');
                    $contact_phone = trim($_POST['contact_phone'] ?? '');
                    
                    if (empty($team_name)) {
                        $error = 'Team name is required';
                    } else {
                        try {
                            // Check permission - only creator can edit
                            $checkStmt = $pdo->prepare("SELECT id FROM clubs WHERE id = ? AND created_by = ?");
                            $checkStmt->execute([$team_id, $_SESSION['user_id']]);
                            
                            if ($checkStmt->fetch()) {
                                $updateStmt = $pdo->prepare("UPDATE clubs SET name = ?, category = ?, contact_email = ?, contact_phone = ? WHERE id = ?");
                                if ($updateStmt->execute([$team_name, $category, $contact_email, $contact_phone, $team_id])) {
                                    header('Location: team_management.php?success=' . urlencode('Team updated successfully!'));
                                    exit();
                                } else {
                                    $error = 'Failed to update team';
                                }
                            } else {
                                $error = 'You can only edit teams you created';
                            }
                        } catch (PDOException $e) {
                            $error = 'Database error occurred';
                            error_log("Edit team error: " . $e->getMessage());
                        }
                    }
                    break;
                
                case 'delete_team':
                    $team_id = $_POST['team_id'] ?? 0;
                    try {
                        // Check permission
                        $checkStmt = $pdo->prepare("SELECT id FROM clubs WHERE id = ? AND created_by = ?");
                        $checkStmt->execute([$team_id, $_SESSION['user_id']]);
                        
                        if ($checkStmt->fetch()) {
                            $deleteStmt = $pdo->prepare("DELETE FROM clubs WHERE id = ?");
                            if ($deleteStmt->execute([$team_id])) {
                                header('Location: team_management.php?success=' . urlencode('Team deleted successfully!'));
                                exit();
                            } else {
                                $error = 'Failed to delete team';
                            }
                        } else {
                            $error = 'You can only delete teams you created';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error occurred';
                        error_log("Delete team error: " . $e->getMessage());
                    }
                    break;
                
                case 'add_member_to_existing_team':
                    $team_id = $_POST['team_id'] ?? 0;
                    $member_name = trim($_POST['member_name'] ?? '');
                    $member_email = trim($_POST['member_email'] ?? '');
                    $member_phone = trim($_POST['member_phone'] ?? '');
                    $member_course = trim($_POST['member_course'] ?? '');
                    $member_department = trim($_POST['member_department'] ?? '');
                    
                    if (empty($member_name) || empty($member_email)) {
                        header('Location: team_management.php?error=' . urlencode('Member name and email are required'));
                        exit();
                    } else {
                        try {
                            // Check permission - only creator can add members
                            $checkStmt = $pdo->prepare("SELECT id FROM clubs WHERE id = ? AND created_by = ?");
                            $checkStmt->execute([$team_id, $_SESSION['user_id']]);
                            
                            if ($checkStmt->fetch()) {
                                // Check if user exists
                                $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                                $userStmt->execute([$member_email]);
                                $existingUser = $userStmt->fetch();
                                
                                if (!$existingUser) {
                                    // Create new user
                                    $username = strtolower(str_replace(' ', '_', $member_name)) . '_' . rand(100, 999);
                                    $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
                                    $names = explode(' ', $member_name, 2);
                                    $firstName = $names[0];
                                    $lastName = isset($names[1]) ? $names[1] : '';
                                    
                                    $insertUserStmt = $pdo->prepare(
                                        "INSERT INTO users (username, email, password, first_name, last_name, phone, role, status, email_verified, course, department) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'member', 'active', TRUE, ?, ?)"
                                    );
                                    $insertUserStmt->execute([$username, $member_email, $defaultPassword, $firstName, $lastName, $member_phone, $member_course, $member_department]);
                                    $userId = $pdo->lastInsertId();
                                } else {
                                    $userId = $existingUser['id'];
                                    // Update user details if provided
                                    if ($member_phone || $member_course || $member_department) {
                                        $updateStmt = $pdo->prepare(
                                            "UPDATE users 
                                            SET phone = COALESCE(NULLIF(?, ''), phone), 
                                                course = COALESCE(NULLIF(?, ''), course), 
                                                department = COALESCE(NULLIF(?, ''), department)
                                            WHERE id = ?"
                                        );
                                        $updateStmt->execute([$member_phone, $member_course, $member_department, $userId]);
                                    }
                                }
                                
                                // Add to team
                                $addMemberStmt = $pdo->prepare(
                                    "INSERT INTO club_members (club_id, user_id, role, status, joined_date) 
                                    VALUES (?, ?, 'member', 'active', CURDATE())
                                    ON DUPLICATE KEY UPDATE status = 'active'"
                                );
                                if ($addMemberStmt->execute([$team_id, $userId])) {
                                    // Update member count
                                    $updateCountStmt = $pdo->prepare("UPDATE clubs SET member_count = (SELECT COUNT(*) FROM club_members WHERE club_id = ? AND status = 'active') WHERE id = ?");
                                    $updateCountStmt->execute([$team_id, $team_id]);
                                    
                                    header('Location: team_management.php?success=' . urlencode('Member added successfully!'));
                                    exit();
                                } else {
                                    header('Location: team_management.php?error=' . urlencode('Failed to add member to team'));
                                    exit();
                                }
                            } else {
                                header('Location: team_management.php?error=' . urlencode('You can only add members to teams you created'));
                                exit();
                            }
                        } catch (PDOException $e) {
                            error_log("Add member to existing team error: " . $e->getMessage());
                            header('Location: team_management.php?error=' . urlencode('Database error occurred'));
                            exit();
                        }
                    }
                    break;
                    
                case 'remove_member':
                    $team_id = $_POST['team_id'] ?? 0;
                    $member_id = $_POST['member_id'] ?? 0;
                    
                    try {
                        // Check permission and prevent removing creator
                        $checkStmt = $pdo->prepare("
                            SELECT c.created_by FROM clubs c 
                            LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? 
                            WHERE c.id = ? AND (c.created_by = ? OR (cm.user_id IS NOT NULL AND cm.role IN ('president', 'vice_president')))
                        ");
                        $checkStmt->execute([$_SESSION['user_id'], $team_id, $_SESSION['user_id']]);
                        $teamInfo = $checkStmt->fetch();
                        
                        if ($teamInfo && $member_id != $teamInfo['created_by']) {
                            $removeStmt = $pdo->prepare("DELETE FROM club_members WHERE club_id = ? AND user_id = ?");
                            if ($removeStmt->execute([$team_id, $member_id])) {
                                // Update member count
                                $updateCountStmt = $pdo->prepare("UPDATE clubs SET member_count = (SELECT COUNT(*) FROM club_members WHERE club_id = ? AND status = 'active') WHERE id = ?");
                                $updateCountStmt->execute([$team_id, $team_id]);
                                
                                header('Location: team_management.php?success=' . urlencode('Member removed successfully!'));
                                exit();
                            } else {
                                $error = 'Failed to remove member';
                            }
                        } else {
                            $error = 'Cannot remove team creator or insufficient permissions';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error occurred';
                        error_log("Remove member error: " . $e->getMessage());
                    }
                    break;
            }
        }
    }
    
    // Get user's teams (created or member of)
    $teamsStmt = $pdo->prepare("
        SELECT DISTINCT c.*, 
               CASE WHEN c.created_by = ? THEN 'creator' 
                    ELSE cm.role 
               END as user_role
        FROM clubs c 
        LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? AND cm.status = 'active'
        WHERE c.created_by = ? OR cm.user_id IS NOT NULL
        ORDER BY c.name
    ");
    $teamsStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $teams = $teamsStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Team management error: " . $e->getMessage());
    $error = "Database error occurred";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - ClubMaster</title>
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
            justify-content: space-between;
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

        /* Teams Table */
        .teams-table-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .teams-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .teams-table thead {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .teams-table th {
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #ffffff;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .teams-table td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
            color: #ffffff;
        }

        .teams-table tbody tr {
            transition: background-color 0.3s ease;
        }

        .teams-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .teams-table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.02);
        }

        .team-role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .role-creator {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.4);
        }

        .role-president {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.4);
        }

        .role-member {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.4);
        }

        .team-name {
            font-weight: 600;
            color: #ffffff;
        }

        .team-category {
            padding: 0.2rem 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 0.8rem;
            color: #cccccc;
        }

        .member-count {
            text-align: center;
            font-weight: 600;
            color: #667eea;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-table {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 500;
        }

        .btn-table:hover {
            transform: translateY(-1px);
        }

        .btn-view {
            background: linear-gradient(45deg, #2196f3, #1976d2);
            color: white;
        }

        .btn-edit {
            background: linear-gradient(45deg, #ff9800, #e68900);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(45deg, #f44336, #d32f2f);
            color: white;
        }

        /* Expanded Members Section */
        .members-row {
            display: none;
        }

        .members-row.expanded {
            display: table-row;
        }

        .members-row td {
            padding: 0;
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
        }

        .members-content {
            padding: 1rem;
            background: rgba(102, 126, 234, 0.05);
        }

        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.75rem;
        }

        .member-card {
            background: rgba(255, 255, 255, 0.08);
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .member-name {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .member-details {
            font-size: 0.8rem;
            color: #b0b0b0;
            line-height: 1.4;
        }

        .member-role-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
            margin-top: 0.25rem;
            display: inline-block;
        }

        /* Forms */
        .form-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
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

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
            font-family: 'Poppins', sans-serif;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: #888;
        }

        .form-select option {
            background: #2a2a2a;
            color: #ffffff;
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: rgba(15, 15, 15, 0.95);
            backdrop-filter: blur(20px);
            margin: 5% auto;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #fff;
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

            .teams-table-container {
                overflow-x: auto;
            }

            .teams-table {
                min-width: 600px;
            }

            .teams-table th,
            .teams-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .table-actions {
                flex-direction: column;
                gap: 0.3rem;
            }

            .btn-table {
                padding: 0.3rem 0.6rem;
                font-size: 0.7rem;
            }

            .form-row {
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
            <a href="team_management.php" class="nav-link active">
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
            <h1 class="page-title">Team Management</h1>
            <p class="page-subtitle">Create and manage your teams with detailed member information</p>
        </div>

        <div class="action-bar">
            <button class="btn btn-primary" onclick="openCreateTeamModal()">
                <i class="fas fa-plus"></i>
                Create New Team
            </button>
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

        <!-- Teams Table -->
        <?php if (!empty($teams)): ?>
            <div class="teams-table-container">
                <table class="teams-table">
                    <thead>
                        <tr>
                            <th>Team Name</th>
                            <th>Category</th>
                            <th>Your Role</th>
                            <th>Members</th>
                            <th>Created</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                        <tr>
                            <td>
                                <div class="team-name"><?php echo htmlspecialchars($team['name']); ?></div>
                            </td>
                            <td>
                                <span class="team-category"><?php echo htmlspecialchars($team['category']); ?></span>
                            </td>
                            <td>
                                <span class="team-role-badge role-<?php echo $team['user_role']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $team['user_role'])); ?>
                                </span>
                            </td>
                            <td class="member-count"><?php echo $team['member_count']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($team['created_at'])); ?></td>
                            <td>
                                <?php if ($team['contact_email']): ?>
                                    <small><?php echo htmlspecialchars($team['contact_email']); ?></small><br>
                                <?php endif; ?>
                                <?php if ($team['contact_phone']): ?>
                                    <small><?php echo htmlspecialchars($team['contact_phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-table btn-view" onclick="toggleTeamMembers(<?php echo $team['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($team['user_role'] === 'creator'): ?>
                                    <button class="btn-table btn-edit" onclick="editTeam(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name']); ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($team['user_role'] === 'creator'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this team?')">
                                        <input type="hidden" name="action" value="delete_team">
                                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                        <button type="submit" class="btn-table btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <!-- Members Row -->
                        <tr class="members-row" id="members-row-<?php echo $team['id']; ?>">
                            <td colspan="7">
                                <div class="members-content">
                                    <h4 style="margin-bottom: 1rem; color: #667eea;">Team Members</h4>
                                    <div class="members-grid" id="members-grid-<?php echo $team['id']; ?>">
                                        <!-- Members will be loaded here -->
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Teams Found</h3>
                <p>You haven't created or joined any teams yet. Click "Create New Team" to get started!</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Create Team Modal -->
    <div id="createTeamModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateTeamModal()">&times;</span>
            <h2 class="form-title">Create New Team</h2>
            <form method="POST" id="createTeamForm">
                <input type="hidden" name="action" value="create_team">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="team_name">Team Name *</label>
                        <input type="text" id="team_name" name="team_name" class="form-input" placeholder="Enter team name" required>
                    </div>
                    <div class="form-group">
                        <label for="member_count">Number of Members *</label>
                        <input type="number" id="member_count" name="member_count" class="form-input" placeholder="Total members" min="1" max="50" value="5" onchange="generateMemberForms()" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="Technology">Technology</option>
                        <option value="Sports">Sports</option>
                        <option value="Arts">Arts</option>
                        <option value="Academic">Academic</option>
                        <option value="Social">Social</option>
                        <option value="Professional">Professional</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" class="form-input" placeholder="team@example.com">
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" class="form-input" placeholder="Contact number">
                    </div>
                </div>

                <!-- Dynamic Member Forms -->
                <div id="memberFormsContainer">
                    <h3 class="form-title" style="margin: 2rem 0 1rem 0; font-size: 1.2rem;">Team Members Information</h3>
                    <!-- Member forms will be generated here -->
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-plus"></i>
                    Create Team with Members
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Team Modal -->
    <div id="editTeamModal" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
            <span class="close" onclick="closeEditTeamModal()">&times;</span>
            <h2 class="form-title">Edit Team & Members</h2>
            
            <!-- Team Information Section -->
            <div class="form-container" style="margin-bottom: 2rem; padding: 1.5rem; background: rgba(255, 255, 255, 0.03); border-radius: 10px;">
                <h3 style="margin-bottom: 1rem; color: #667eea; font-size: 1.1rem;">Team Information</h3>
                <form method="POST" id="editTeamForm">
                    <input type="hidden" name="action" value="edit_team">
                    <input type="hidden" id="edit_team_id" name="team_id" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_team_name">Team Name *</label>
                            <input type="text" id="edit_team_name" name="team_name" class="form-input" placeholder="Enter team name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_category">Category</label>
                            <select id="edit_category" name="category" class="form-select">
                                <option value="Technology">Technology</option>
                                <option value="Sports">Sports</option>
                                <option value="Arts">Arts</option>
                                <option value="Academic">Academic</option>
                                <option value="Social">Social</option>
                                <option value="Professional">Professional</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_contact_email">Contact Email</label>
                            <input type="email" id="edit_contact_email" name="contact_email" class="form-input" placeholder="team@example.com">
                        </div>
                        <div class="form-group">
                            <label for="edit_contact_phone">Contact Phone</label>
                            <input type="tel" id="edit_contact_phone" name="contact_phone" class="form-input" placeholder="Contact number">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i class="fas fa-save"></i>
                        Update Team Information
                    </button>
                </form>
            </div>
            
            <!-- Team Members Section -->
            <div class="form-container" style="padding: 1.5rem; background: rgba(255, 255, 255, 0.03); border-radius: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="color: #667eea; font-size: 1.1rem; margin: 0;">Team Members</h3>
                    <button type="button" class="btn btn-success btn-small" onclick="openAddMemberToTeamModal()">
                        <i class="fas fa-user-plus"></i> Add Member
                    </button>
                </div>
                
                <div id="editTeamMembersList" class="members-grid">
                    <!-- Members will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Member to Team Modal -->
    <div id="addMemberToTeamModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddMemberToTeamModal()">&times;</span>
            <h2 class="form-title">Add New Member</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_member_to_existing_team">
                <input type="hidden" id="add_member_team_id" name="team_id" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_member_name">Full Name *</label>
                        <input type="text" id="new_member_name" name="member_name" class="form-input" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_member_email">Email *</label>
                        <input type="email" id="new_member_email" name="member_email" class="form-input" placeholder="member@example.com" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_member_phone">Phone Number</label>
                        <input type="tel" id="new_member_phone" name="member_phone" class="form-input" placeholder="Contact number">
                    </div>
                    <div class="form-group">
                        <label for="new_member_course">Course</label>
                        <input type="text" id="new_member_course" name="member_course" class="form-input" placeholder="e.g., Computer Science">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_member_department">Department</label>
                    <input type="text" id="new_member_department" name="member_department" class="form-input" placeholder="e.g., Engineering, Business, Arts">
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-user-plus"></i>
                    Add Member
                </button>
            </form>
        </div>
    </div>


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

        // Modal functions
        function openCreateTeamModal() {
            document.getElementById('createTeamModal').style.display = 'block';
            generateMemberForms(); // Generate initial member forms
        }

        function closeCreateTeamModal() {
            document.getElementById('createTeamModal').style.display = 'none';
        }

        // Generate dynamic member forms based on member count
        function generateMemberForms() {
            const memberCount = parseInt(document.getElementById('member_count').value) || 5;
            const container = document.getElementById('memberFormsContainer');
            
            // Clear existing forms except the title
            const title = container.querySelector('h3');
            container.innerHTML = '';
            container.appendChild(title);
            
            for (let i = 1; i <= memberCount; i++) {
                const memberDiv = document.createElement('div');
                memberDiv.className = 'form-container';
                memberDiv.style.marginBottom = '1.5rem';
                memberDiv.style.padding = '1.5rem';
                memberDiv.style.background = 'rgba(255, 255, 255, 0.03)';
                memberDiv.style.borderRadius = '10px';
                memberDiv.style.border = '1px solid rgba(255, 255, 255, 0.05)';
                
                memberDiv.innerHTML = `
                    <h4 style="margin-bottom: 1rem; color: #667eea; font-size: 1rem;">Member ${i} Information</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="member_name_${i}">Full Name *</label>
                            <input type="text" id="member_name_${i}" name="member_name_${i}" class="form-input" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label for="member_email_${i}">Email *</label>
                            <input type="email" id="member_email_${i}" name="member_email_${i}" class="form-input" placeholder="member@example.com" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="member_phone_${i}">Phone Number</label>
                            <input type="tel" id="member_phone_${i}" name="member_phone_${i}" class="form-input" placeholder="Contact number">
                        </div>
                        <div class="form-group">
                            <label for="member_course_${i}">Course</label>
                            <input type="text" id="member_course_${i}" name="member_course_${i}" class="form-input" placeholder="e.g., Computer Science">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="member_department_${i}">Department</label>
                        <input type="text" id="member_department_${i}" name="member_department_${i}" class="form-input" placeholder="e.g., Engineering, Business, Arts">
                    </div>
                `;
                
                container.appendChild(memberDiv);
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createTeamModal');
            const editModal = document.getElementById('editTeamModal');
            const addMemberModal = document.getElementById('addMemberToTeamModal');
            
            if (event.target == createModal) {
                createModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == addMemberModal) {
                addMemberModal.style.display = 'none';
            }
        }

        // Toggle team members display
        async function toggleTeamMembers(teamId) {
            console.log('toggleTeamMembers called with teamId:', teamId);
            
            const membersRow = document.getElementById('members-row-' + teamId);
            const container = document.getElementById('members-grid-' + teamId);
            
            if (!membersRow) {
                console.error('Members row not found for team ID:', teamId);
                alert('Error: Could not find members section');
                return;
            }
            
            if (!container) {
                console.error('Members container not found for team ID:', teamId);
                alert('Error: Could not find members container');
                return;
            }
            
            if (!membersRow.classList.contains('expanded')) {
                // Show members - fetch from server
                console.log('Fetching members for team:', teamId);
                
                try {
                    const response = await fetch('get_team_members.php?team_id=' + teamId);
                    console.log('Response status:', response.status);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        container.innerHTML = '';
                        
                        if (data.members && data.members.length > 0) {
                            data.members.forEach(member => {
                                const memberCard = document.createElement('div');
                                memberCard.className = 'member-card';
                                memberCard.innerHTML = `
                                    <div class="member-name">${member.name || 'No name'}</div>
                                    <div class="member-details">
                                        📧 ${member.email || 'No email'}<br>
                                        ${member.phone ? '📱 ' + member.phone + '<br>' : ''}
                                        ${member.course ? '📚 ' + member.course + '<br>' : ''}
                                        ${member.department ? '🏢 ' + member.department + '<br>' : ''}
                                    </div>
                                    <div class="member-role-badge">${(member.role || 'member').replace('_', ' ')}</div>
                                    ${member.can_remove ? `
                                    <form method="POST" style="margin-top: 0.5rem;" onsubmit="return confirm('Remove this member?')">
                                        <input type="hidden" name="action" value="remove_member">
                                        <input type="hidden" name="team_id" value="${teamId}">
                                        <input type="hidden" name="member_id" value="${member.id}">
                                        <button type="submit" class="btn-table btn-delete" style="width: 100%;">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </form>` : ''}
                                `;
                                container.appendChild(memberCard);
                            });
                        } else {
                            container.innerHTML = '<p style="color: #888; text-align: center; padding: 1rem;">No members found</p>';
                        }
                        
                        membersRow.classList.add('expanded');
                        console.log('Members displayed successfully');
                    } else {
                        console.error('Server error:', data.message);
                        alert('Failed to load members: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error fetching members:', error);
                    alert('Error loading members: ' + error.message);
                }
            } else {
                // Hide members
                console.log('Hiding members for team:', teamId);
                membersRow.classList.remove('expanded');
            }
        }

        // Edit team function
        async function editTeam(teamId, teamName) {
            console.log('editTeam called with:', teamId, teamName);
            
            try {
                // Fetch current team data
                const response = await fetch(`get_team_data.php?team_id=${teamId}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Team data:', data);
                
                if (data.success) {
                    // Check if user can edit
                    if (data.team.can_edit) {
                        // Populate edit form with current data
                        document.getElementById('edit_team_id').value = teamId;
                        document.getElementById('edit_team_name').value = data.team.name || '';
                        document.getElementById('edit_category').value = data.team.category || 'Technology';
                        document.getElementById('edit_contact_email').value = data.team.contact_email || '';
                        document.getElementById('edit_contact_phone').value = data.team.contact_phone || '';
                        
                        // Update modal title
                        document.querySelector('#editTeamModal .form-title').textContent = 'Edit ' + teamName;
                        
                        // Load team members
                        loadTeamMembersForEdit(teamId);
                        
                        // Show edit modal
                        document.getElementById('editTeamModal').style.display = 'block';
                    } else {
                        alert('Only team creators can edit team information.');
                    }
                } else {
                    alert('Failed to load team data: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error fetching team data:', error);
                alert('Error loading team data: ' + error.message);
            }
        }
        
        function closeEditTeamModal() {
            document.getElementById('editTeamModal').style.display = 'none';
        }
        
        // Load team members for edit modal
        async function loadTeamMembersForEdit(teamId) {
            const container = document.getElementById('editTeamMembersList');
            
            try {
                const response = await fetch('get_team_members.php?team_id=' + teamId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    container.innerHTML = '';
                    
                    if (data.members && data.members.length > 0) {
                        data.members.forEach(member => {
                            const memberCard = document.createElement('div');
                            memberCard.className = 'member-card';
                            memberCard.innerHTML = `
                                <div class="member-name">${member.name || 'No name'}</div>
                                <div class="member-details">
                                    📧 ${member.email || 'No email'}<br>
                                    ${member.phone ? '📱 ' + member.phone + '<br>' : ''}
                                    ${member.course ? '📚 ' + member.course + '<br>' : ''}
                                    ${member.department ? '🏢 ' + member.department + '<br>' : ''}
                                    📅 Joined: ${member.joined_date || 'Unknown'}
                                </div>
                                <div class="member-role-badge">${(member.role || 'member').replace('_', ' ')}</div>
                                ${member.can_remove ? `
                                <form method="POST" style="margin-top: 0.5rem;" onsubmit="return confirm('Remove this member?')">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="team_id" value="${teamId}">
                                    <input type="hidden" name="member_id" value="${member.id}">
                                    <button type="submit" class="btn-table btn-delete" style="width: 100%;">
                                        <i class="fas fa-times"></i> Remove Member
                                    </button>
                                </form>` : ''}
                            `;
                            container.appendChild(memberCard);
                        });
                    } else {
                        container.innerHTML = '<p style="color: #888; text-align: center; padding: 1rem;">No members found</p>';
                    }
                } else {
                    container.innerHTML = '<p style="color: #ff6b6b; text-align: center; padding: 1rem;">Error: ' + (data.message || 'Failed to load members') + '</p>';
                }
            } catch (error) {
                console.error('Error loading team members for edit:', error);
                container.innerHTML = '<p style="color: #ff6b6b; text-align: center; padding: 1rem;">Error loading members</p>';
            }
        }
        
        // Add member to team modal functions
        function openAddMemberToTeamModal() {
            const teamId = document.getElementById('edit_team_id').value;
            document.getElementById('add_member_team_id').value = teamId;
            document.getElementById('addMemberToTeamModal').style.display = 'block';
        }
        
        function closeAddMemberToTeamModal() {
            document.getElementById('addMemberToTeamModal').style.display = 'none';
            // Clear form
            document.getElementById('new_member_name').value = '';
            document.getElementById('new_member_email').value = '';
            document.getElementById('new_member_phone').value = '';
            document.getElementById('new_member_course').value = '';
            document.getElementById('new_member_department').value = '';
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

        console.log('👥 Team Management page initialized successfully!');
    </script>
</body>
</html>