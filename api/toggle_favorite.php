<?php
// api/toggle_favorite.php 
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// CORS 헤더 추가
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 디버깅 로그 추가
error_log("=== TOGGLE FAVORITE DEBUG ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '허용되지 않은 메소드입니다.']);
    exit;
}

if (!isset($_POST['question_id'])) {
    echo json_encode(['success' => false, 'message' => '문제 ID가 필요합니다.']);
    exit;
}

try {
    $questionId = (int)$_POST['question_id'];
    $userId = $_SESSION['user_id'];
    
    error_log("Processing favorite toggle for question_id: $questionId, user_id: $userId");
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 문제가 사용자의 것인지 확인
    $stmt = $pdo->prepare("SELECT id, is_favorite FROM questions WHERE id = ? AND user_id = ?");
    $stmt->execute([$questionId, $userId]);
    $question = $stmt->fetch();
    
    if (!$question) {
        error_log("Question not found or not owned by user");
        echo json_encode(['success' => false, 'message' => '문제를 찾을 수 없습니다.']);
        exit;
    }
    
    // 즐겨찾기 상태 토글
    $currentFavorite = (bool)$question['is_favorite'];
    $newFavoriteStatus = $currentFavorite ? 0 : 1;
    
    error_log("Current favorite status: " . ($currentFavorite ? 'true' : 'false'));
    error_log("New favorite status: " . ($newFavoriteStatus ? 'true' : 'false'));
    
    $stmt = $pdo->prepare("UPDATE questions SET is_favorite = ? WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$newFavoriteStatus, $questionId, $userId]);
    
    if ($result) {
        $message = $newFavoriteStatus ? '즐겨찾기에 추가되었습니다.' : '즐겨찾기에서 제거되었습니다.';
        error_log("Favorite toggle successful: $message");
        
        echo json_encode([
            'success' => true,
            'is_favorite' => (bool)$newFavoriteStatus,
            'message' => $message
        ]);
    } else {
        error_log("Database update failed");
        echo json_encode(['success' => false, 'message' => '즐겨찾기 변경에 실패했습니다.']);
    }
    
} catch (Exception $e) {
    error_log("Toggle favorite error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다: ' . $e->getMessage()]);
}
?>