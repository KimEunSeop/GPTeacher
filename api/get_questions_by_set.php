<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

// question_set_id 확인
if (!isset($_GET['set_id']) || empty($_GET['set_id'])) {
    echo json_encode(['success' => false, 'message' => '문제집 ID가 필요합니다.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$question_set_id = $_GET['set_id'];

try {
    // 데이터베이스 연결
    $pdo = new PDO('mysql:host=localhost;dbname=gpt_question_maker;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 문제집 정보 조회
    $info_sql = "SELECT 
                    question_set_id,
                    pdf_filename,
                    title,
                    COUNT(*) as total_questions,
                    SUM(CASE WHEN question_type = 'multiple_choice' THEN 1 ELSE 0 END) as multiple_choice_count,
                    SUM(CASE WHEN question_type = 'subjective' THEN 1 ELSE 0 END) as subjective_count,
                    MIN(created_at) as created_at,
                    MAX(updated_at) as updated_at
                 FROM questions 
                 WHERE user_id = ? AND question_set_id = ?
                 GROUP BY question_set_id, pdf_filename, title";

    $info_stmt = $pdo->prepare($info_sql);
    $info_stmt->execute([$user_id, $question_set_id]);
    $question_set_info = $info_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question_set_info) {
        echo json_encode(['success' => false, 'message' => '문제집을 찾을 수 없습니다.']);
        exit;
    }

    // 문제 목록 조회
    $questions_sql = "SELECT 
                        id,
                        question_set_id,
                        question_type,
                        question_number,
                        question_text,
                        answer_text,
                        choices,
                        created_at,
                        updated_at
                      FROM questions 
                      WHERE user_id = ? AND question_set_id = ?
                      ORDER BY question_number ASC";

    $questions_stmt = $pdo->prepare($questions_sql);
    $questions_stmt->execute([$user_id, $question_set_id]);
    $questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'question_set_info' => $question_set_info,
        'questions' => $questions
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
