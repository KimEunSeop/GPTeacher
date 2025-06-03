<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json; charset=utf-8');

error_log("=== Upload API Called ===");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

// 로그인 확인
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청 방식입니다.']);
    exit;
}

// 필수 필드 확인
if (!isset($_FILES['pdf_file']) || !isset($_POST['title']) || !isset($_POST['question_count']) || !isset($_POST['question_type'])) {
    echo json_encode(['success' => false, 'message' => '필수 필드가 누락되었습니다.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$title = trim($_POST['title']);
$question_count = intval($_POST['question_count']);
$question_type = $_POST['question_type'];

// 입력값 검증
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => '제목을 입력해주세요.']);
    exit;
}

if ($question_count < 1 || $question_count > 10) {
    echo json_encode(['success' => false, 'message' => '문제 개수는 1~10개 사이여야 합니다.']);
    exit;
}

if (!in_array($question_type, ['multiple_choice', 'subjective', 'both'])) {
    echo json_encode(['success' => false, 'message' => '유효하지 않은 문제 유형입니다.']);
    exit;
}

// 파일 업로드 처리
$upload_file = $_FILES['pdf_file'];

if ($upload_file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '파일 업로드 중 오류가 발생했습니다.']);
    exit;
}

if ($upload_file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => '파일 크기는 10MB 이하여야 합니다.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $upload_file['tmp_name']);
finfo_close($finfo);

if ($mime_type !== 'application/pdf') {
    echo json_encode(['success' => false, 'message' => 'PDF 파일만 업로드 가능합니다.']);
    exit;
}

try {
    // 데이터베이스 연결
    $pdo = new PDO('mysql:host=localhost;dbname=gpt_question_maker;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 고유한 question_set_id 생성
    $question_set_id = 'qs_' . $user_id . '_' . time() . '_' . mt_rand(1000, 9999);
    
    // 업로드 디렉토리 생성
    $upload_dir = '../uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // 파일명 생성 (중복 방지)
    $file_extension = '.pdf';
    $safe_filename = preg_replace('/[^a-zA-Z0-9가-힣_.-]/', '', $title);
    $uploaded_filename = $question_set_id . '_' . $safe_filename . $file_extension;
    $uploaded_path = $upload_dir . $uploaded_filename;
    
    // 파일 저장
    if (!move_uploaded_file($upload_file['tmp_name'], $uploaded_path)) {
        throw new Exception('파일 저장에 실패했습니다.');
    }
    
    // PDF 텍스트 추출
    $extract_script = '../scripts/extract_pdf.py';
    $extract_command = escapeshellcmd("python3 $extract_script " . escapeshellarg($uploaded_path));
    
    $extracted_text = shell_exec($extract_command . ' 2>&1');
    
    if (empty($extracted_text) || strpos($extracted_text, 'Error:') === 0) {
        throw new Exception('PDF 텍스트 추출에 실패했습니다: ' . $extracted_text);
    }
    
    // OpenAI API 설정 로드
    $config_file = '../config/database.php';
    if (!file_exists($config_file)) {
        throw new Exception('API 설정 파일이 없습니다.');
    }
    include $config_file;
    
    if (empty(OPENAI_API_KEY)) {
        throw new Exception('OpenAI API 키가 설정되지 않았습니다.');
    }
    
    // OpenAI API 호출
    $api_script = '../openai_api.py';
    $api_command = escapeshellcmd("python3 $api_script " . 
        escapeshellarg($extracted_text) . ' ' . 
        escapeshellarg($OPENAI_API_KEY) . ' ' . 
        escapeshellarg($question_count) . ' ' . 
        escapeshellarg($question_type));
    
    $api_result = shell_exec($api_command . ' 2>&1');
    
    if (empty($api_result)) {
        throw new Exception('OpenAI API 응답이 없습니다.');
    }
    
    // JSON 응답 파싱
    $questions_data = json_decode($api_result, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('OpenAI API 응답 파싱 실패: ' . $api_result);
    }
    
    if (!isset($questions_data['questions']) || !is_array($questions_data['questions'])) {
        throw new Exception('유효하지 않은 문제 데이터 형식입니다.');
    }
    
    // 트랜잭션 시작
    $pdo->beginTransaction();
    
    $insert_sql = "INSERT INTO questions (user_id, question_set_id, pdf_filename, title, question_type, question_number, question_text, answer_text, choices, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    
    $inserted_count = 0;
    
    foreach ($questions_data['questions'] as $index => $question) {
        $question_number = $index + 1;
        $q_type = $question['type'];
        $question_text = $question['question'];
        
        if ($q_type === 'multiple_choice') {
            $answer_text = isset($question['explanation']) ? $question['explanation'] : '정답: ' . $question['correct_answer'];
            $choices = json_encode([
                'choices' => $question['choices'],
                'correct_answer' => $question['correct_answer']
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $answer_text = isset($question['sample_answer']) ? $question['sample_answer'] : '';
            if (isset($question['grading_criteria'])) {
                $answer_text .= "\n\n[채점 기준]\n" . $question['grading_criteria'];
            }
            $choices = null;
        }
        
        $insert_stmt->execute([
            $user_id,
            $question_set_id,
            $uploaded_filename,
            $title,
            $q_type,
            $question_number,
            $question_text,
            $answer_text,
            $choices
        ]);
        
        $inserted_count++;
    }
    
    // 트랜잭션 커밋
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '문제가 성공적으로 생성되었습니다.',
        'question_set_id' => $question_set_id,
        'total_questions' => $inserted_count,
        'pdf_filename' => $uploaded_filename
    ]);

} catch (Exception $e) {
    // 트랜잭션 롤백
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    // 업로드된 파일 삭제
    if (isset($uploaded_path) && file_exists($uploaded_path)) {
        unlink($uploaded_path);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
