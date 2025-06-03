<?php
// api/get_recent_activity.php - 최근 활동 조회
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth();

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT id, title, created_at 
        FROM questions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $activities = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    error_log("Get recent activity error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '활동 내역을 불러올 수 없습니다.']);
}
?>