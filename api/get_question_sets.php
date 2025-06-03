<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

try {
    // 데이터베이스 연결
    $pdo = new PDO('mysql:host=localhost;dbname=gpt_question_maker;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];

    // 문제집 목록 조회 (문제집별로 그룹화)
    $sql = "SELECT 
                question_set_id,
                pdf_filename,
                title,
                COUNT(*) as total_questions,
                SUM(CASE WHEN question_type = 'multiple_choice' THEN 1 ELSE 0 END) as multiple_choice_count,
                SUM(CASE WHEN question_type = 'subjective' THEN 1 ELSE 0 END) as subjective_count,
                MIN(created_at) as created_at,
                MAX(updated_at) as updated_at
            FROM questions 
            WHERE user_id = ? 
            GROUP BY question_set_id, pdf_filename, title
            ORDER BY MIN(created_at) DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $question_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'question_sets' => $question_sets
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => '데이터베이스 오류가 발생했습니다.',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
