<?php
// api/delete_question.php - 문제 삭제
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    $questionId = (int)$_GET['id'];
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 먼저 해당 문제가 사용자의 것인지 확인하고 파일명 가져오기
    $stmt = $pdo->prepare("
        SELECT pdf_filename 
        FROM questions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$questionId, $_SESSION['user_id']]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'message' => '문제를 찾을 수 없습니다.']);
        exit;
    }
    
    // 문제 삭제
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$questionId, $_SESSION['user_id']]);
    
    if ($result) {
        // PDF 파일도 삭제
        $filePath = UPLOAD_DIR . $question['pdf_filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        echo json_encode(['success' => true, 'message' => '문제가 삭제되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '문제 삭제에 실패했습니다.']);
    }
    
} catch (Exception $e) {
    error_log("Delete question error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '문제 삭제 중 오류가 발생했습니다.']);
}
?>
