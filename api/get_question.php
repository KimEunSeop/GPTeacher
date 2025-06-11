<?php
// api/get_question.php - 문제 조회 
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// CORS 헤더 추가
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 디버깅 로그
error_log("=== GET QUESTION DEBUG ===");
error_log("GET data: " . print_r($_GET, true));
error_log("Session data: " . print_r($_SESSION, true));

requireAuth();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => '문제 ID가 필요합니다.']);
    exit;
}

try {
    $questionId = (int)$_GET['id'];
    $userId = $_SESSION['user_id'];
    
    error_log("Getting question_id: $questionId for user_id: $userId");
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT id, title, pdf_filename, question_text, answer_text, created_at, 
               COALESCE(is_favorite, 0) as is_favorite
        FROM questions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$questionId, $userId]);
    $question = $stmt->fetch();
    
    if (!$question) {
        error_log("Question not found for question_id: $questionId, user_id: $userId");
        echo json_encode(['success' => false, 'message' => '문제를 찾을 수 없습니다.']);
        exit;
    }
    
    // is_favorite를 boolean으로 변환
    $question['is_favorite'] = (bool)$question['is_favorite'];
    
    error_log("Question found: " . $question['title'] . ", is_favorite: " . ($question['is_favorite'] ? 'true' : 'false'));
    
    echo json_encode([
        'success' => true,
        'question' => $question
    ]);
    
} catch (Exception $e) {
    error_log("Get question error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => '문제를 불러올 수 없습니다: ' . $e->getMessage()]);
}
?>
