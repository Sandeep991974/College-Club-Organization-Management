<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$team_id = $_GET['team_id'] ?? 0;

try {
    // Simple query to debug
    $stmt = $pdo->prepare("SELECT * FROM clubs WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch();
    
    if (!$team) {
        echo json_encode(['success' => false, 'message' => 'Team not found', 'team_id' => $team_id]);
        exit();
    }
    
    // Check what columns exist in users table
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $existingColumns = [];
    while ($col = $columnsStmt->fetch()) {
        $existingColumns[] = $col['Field'];
    }
    
    // Build query based on available columns
    $selectFields = ['u.id', 'u.first_name', 'u.last_name', 'u.email'];
    
    if (in_array('phone', $existingColumns)) $selectFields[] = 'u.phone';
    if (in_array('course', $existingColumns)) $selectFields[] = 'u.course';
    if (in_array('department', $existingColumns)) $selectFields[] = 'u.department';
    if (in_array('year', $existingColumns)) $selectFields[] = 'u.year';
    
    $selectFields[] = 'cm.role';
    $selectFields[] = 'cm.joined_date';
    
    $selectClause = implode(', ', $selectFields);
    
    // Get members
    $membersStmt = $pdo->prepare("
        SELECT $selectClause
        FROM club_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.club_id = ? AND cm.status = 'active'
        ORDER BY u.first_name
    ");
    $membersStmt->execute([$team_id]);
    $members = $membersStmt->fetchAll();
    
    $response = [
        'success' => true,
        'team' => $team,
        'existing_columns' => $existingColumns,
        'query_fields' => $selectFields,
        'members' => []
    ];
    
    foreach ($members as $member) {
        $memberData = [
            'id' => $member['id'] ?? '',
            'name' => trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')),
            'email' => $member['email'] ?? '',
            'phone' => isset($member['phone']) ? $member['phone'] : '',
            'course' => isset($member['course']) ? $member['course'] : '',
            'year' => isset($member['year']) ? $member['year'] : '',
            'department' => isset($member['department']) ? $member['department'] : '',
            'role' => $member['role'] ?? 'member',
            'joined_date' => $member['joined_date'] ?? '',
            'can_remove' => true // For debugging
        ];
        
        $response['members'][] = $memberData;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'team_id' => $team_id
    ]);
}
?>