<?php
// api/get_favorites.php 
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
error_log("=== GET FAVORITES DEBUG ===");
error_log("Session data: " . print_r($_SESSION, true));

requireAuth();

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $userId = $_SESSION['user_id'];
    
    // 전체 즐겨찾기 목록 또는 최근 몇 개만
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
    
    error_log("Getting favorites for user_id: $userId, limit: " . ($limit ?: 'none'));
    
    $sql = "
        SELECT id, title, pdf_filename, created_at, question_set_id
        FROM questions 
        WHERE user_id = ? AND is_favorite = 1 
        ORDER BY created_at DESC
    ";
    
    if ($limit) {
        $sql .= " LIMIT " . $limit;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $favorites = $stmt->fetchAll();
    
    // 총 즐겨찾기 개수
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE user_id = ? AND is_favorite = 1");
    $stmt->execute([$userId]);
    $totalCount = $stmt->fetch()['total'];
    
    error_log("Found " . count($favorites) . " favorites out of $totalCount total");
    
    echo json_encode([
        'success' => true,
        'favorites' => $favorites,
        'total_count' => (int)$totalCount
    ]);
    
} catch (Exception $e) {
    error_log("Get favorites error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => '즐겨찾기 목록을 불러올 수 없습니다: ' . $e->getMessage()]);
}
?>
