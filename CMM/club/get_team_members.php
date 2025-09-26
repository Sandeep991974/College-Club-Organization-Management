<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$team_id = $_GET['team_id'] ?? 0;

if (!$team_id) {
    echo json_encode(['success' => false, 'message' => 'Team ID required']);
    exit();
}

try {
    // Check if user has permission to view this team
    $checkStmt = $pdo->prepare("
        SELECT c.created_by FROM clubs c 
        LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? 
        WHERE c.id = ? AND (c.created_by = ? OR cm.user_id IS NOT NULL)
    ");
    $checkStmt->execute([$_SESSION['user_id'], $team_id, $_SESSION['user_id']]);
    $teamInfo = $checkStmt->fetch();
    
    if (!$teamInfo) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    // Check what columns exist in users table
    $columnsStmt = $pdo->query("DESCRIBE users");
    $existingColumns = [];
    while ($col = $columnsStmt->fetch()) {
        $existingColumns[] = $col['Field'];
    }
    
    // Build query based on available columns
    $selectFields = ['u.id', 'u.first_name', 'u.last_name', 'u.email'];
    
    if (in_array('phone', $existingColumns)) $selectFields[] = 'u.phone';
    if (in_array('course', $existingColumns)) $selectFields[] = 'u.course';
    if (in_array('year', $existingColumns)) $selectFields[] = 'u.year';
    if (in_array('department', $existingColumns)) $selectFields[] = 'u.department';
    
    $selectFields[] = 'cm.role';
    $selectFields[] = 'cm.joined_date';
    $selectFields[] = 'CASE WHEN c.created_by = ? THEN true ELSE false END as is_creator';
    
    $selectClause = implode(', ', $selectFields);
    
    // Get team members with detailed information
    $membersStmt = $pdo->prepare("
        SELECT $selectClause
        FROM club_members cm
        JOIN users u ON cm.user_id = u.id
        JOIN clubs c ON cm.club_id = c.id
        WHERE cm.club_id = ? AND cm.status = 'active'
        ORDER BY 
            CASE cm.role 
                WHEN 'president' THEN 1
                WHEN 'vice_president' THEN 2
                WHEN 'secretary' THEN 3
                WHEN 'treasurer' THEN 4
                ELSE 5
            END,
            u.first_name
    ");
    $membersStmt->execute([$_SESSION['user_id'], $team_id]);
    $members = $membersStmt->fetchAll();
    
    // Format the response
    $response = [
        'success' => true,
        'members' => []
    ];
    
    foreach ($members as $member) {
        // Only team creators can remove members (except themselves)
        $canRemove = ($teamInfo['created_by'] == $_SESSION['user_id'] && $member['id'] != $teamInfo['created_by']);
                     
        $response['members'][] = [
            'id' => $member['id'] ?? '',
            'name' => trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')),
            'email' => $member['email'] ?? '',
            'phone' => isset($member['phone']) ? $member['phone'] : '',
            'course' => isset($member['course']) ? $member['course'] : '',
            'year' => isset($member['year']) ? $member['year'] : '',
            'department' => isset($member['department']) ? $member['department'] : '',
            'role' => $member['role'] ?? 'member',
            'joined_date' => $member['joined_date'] ?? '',
            'is_creator' => $member['is_creator'] ?? false,
            'can_remove' => $canRemove
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Get team members error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>