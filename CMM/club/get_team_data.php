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
    // Check if user has permission to view/edit this team
    $checkStmt = $pdo->prepare("
        SELECT c.*, 
               CASE WHEN c.created_by = ? THEN true ELSE false END as can_edit
        FROM clubs c 
        LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.user_id = ? AND cm.status = 'active'
        WHERE c.id = ? AND (c.created_by = ? OR cm.user_id IS NOT NULL)
    ");
    $checkStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $team_id, $_SESSION['user_id']]);
    $team = $checkStmt->fetch();
    
    if (!$team) {
        echo json_encode(['success' => false, 'message' => 'Team not found or access denied']);
        exit();
    }
    
    // Return team data
    $response = [
        'success' => true,
        'team' => [
            'id' => $team['id'],
            'name' => $team['name'],
            'description' => $team['description'],
            'category' => $team['category'],
            'location' => $team['location'],
            'contact_email' => $team['contact_email'],
            'contact_phone' => $team['contact_phone'],
            'member_count' => $team['member_count'],
            'created_at' => $team['created_at'],
            'can_edit' => $team['can_edit']
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Get team data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>