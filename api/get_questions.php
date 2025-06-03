<?php
// api/get_questions.php - 사용자의 문제 목록 조회
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth();

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT id, title, pdf_filename, created_at 
        FROM questions 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $questions = $stmt->fetchAll();
    
    // 디버그 로그 추가
    error_log("=== GET_QUESTIONS DEBUG ===");
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("Questions count: " . count($questions));
    error_log("Questions data: " . print_r($questions, true));
    
    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
    
} catch (Exception $e) {
    error_log("Get questions error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '문제 목록을 불러올 수 없습니다.']);
}
?>
