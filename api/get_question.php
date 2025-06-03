<?php
// api/get_question.php - 특정 문제 상세 조회
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => '문제 ID가 필요합니다.']);
    exit;
}

try {
    $questionId = (int)$_GET['id'];
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT id, title, pdf_filename, question_text, answer_text, created_at 
        FROM questions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$questionId, $_SESSION['user_id']]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'message' => '문제를 찾을 수 없습니다.']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'question' => $question
    ]);
    
} catch (Exception $e) {
    error_log("Get question error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '문제를 불러올 수 없습니다.']);
}
?>
