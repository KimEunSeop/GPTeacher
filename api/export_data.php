<?php
// api/export_data.php - 사용자 데이터 내보내기
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAuth();

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 사용자의 모든 문제 데이터 가져오기
    $stmt = $pdo->prepare("
        SELECT id, title, pdf_filename, question_text, answer_text, created_at 
        FROM questions 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $questions = $stmt->fetchAll();
    
    // 사용자 정보
    $stmt = $pdo->prepare("SELECT email, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    $exportData = [
        'user_info' => [
            'email' => $user['email'],
            'join_date' => $user['created_at'],
            'export_date' => date('Y-m-d H:i:s'),
            'total_questions' => count($questions)
        ],
        'questions' => $questions
    ];
    
    // JSON 파일로 다운로드
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="gpt_questions_export_' . date('Y-m-d') . '.json"');
    
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Export data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '데이터 내보내기에 실패했습니다.']);
}
?>
