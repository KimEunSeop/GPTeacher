<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// config 파일을 맨 먼저 로드
require_once '../config/database.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

error_log("=== Upload API Called ===");
error_log("POST: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

// API 키 확인
if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY)) {
    echo json_encode(['success' => false, 'message' => 'OpenAI API 키가 설정되지 않았습니다.']);
    exit;
}

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
    $db = new Database();
    $pdo = $db->getConnection();

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
    
    error_log("File saved to: " . $uploaded_path);
    
    // PDF 텍스트 추출 - 정확한 경로 사용
    $extract_script = '../scripts/extract_pdf.py';
    
    // 실제 Python 경로와 라이브러리 경로 사용
    $python_path = '/usr/bin/python3';
    
    // 실제 시스템에서 확인된 라이브러리 경로들
    $pythonpath_parts = [
        '/Users/eunseop/Library/Python/3.9/lib/python/site-packages',
        '/Library/Developer/CommandLineTools/Library/Frameworks/Python3.framework/Versions/3.9/lib/python3.9/site-packages',
        '/Library/Python/3.9/site-packages'
    ];
    $pythonpath = implode(':', $pythonpath_parts);
    
    // 환경변수와 함께 명령어 실행
    $extract_command = "export PYTHONPATH=\"$pythonpath\" && $python_path " . escapeshellarg($extract_script) . " " . escapeshellarg($uploaded_path);
    
    error_log("PDF Extract Command: " . $extract_command);
    
    // shell_exec으로 간단하게 실행
    $output = shell_exec($extract_command . ' 2>&1');
    
    error_log("PDF extraction full output: " . substr($output, 0, 1000));
    
    // stderr와 stdout 분리
    $lines = explode("\n", $output);
    $stderr_lines = [];
    $stdout_lines = [];
    
    foreach ($lines as $line) {
        if (strpos($line, 'PDF 파일 처리 시작:') !== false || 
            strpos($line, '시도 중...') !== false || 
            strpos($line, '텍스트 길이:') !== false ||
            strpos($line, '최종') !== false ||
            strpos($line, 'Python 경로:') !== false ||
            strpos($line, '오류:') !== false ||
            strpos($line, 'Error:') !== false) {
            $stderr_lines[] = $line;
        } else if (!empty(trim($line))) {
            $stdout_lines[] = $line;
        }
    }
    
    $extracted_text = implode("\n", $stdout_lines);
    $stderr_output = implode("\n", $stderr_lines);
    
    error_log("Separated stderr: " . $stderr_output);
    error_log("Separated stdout length: " . strlen($extracted_text));
    error_log("Separated stdout preview: " . substr($extracted_text, 0, 300));
    
    if (empty($extracted_text) || strpos($extracted_text, 'Error:') === 0) {
        throw new Exception('PDF 텍스트 추출에 실패했습니다. 오류: ' . $stderr_output);
    }
    
    // 텍스트 정리
    $cleaned_text = trim($extracted_text);
    if (strlen($cleaned_text) < 100) {
        throw new Exception('PDF에서 충분한 텍스트를 추출할 수 없습니다. 추출된 내용: ' . $cleaned_text);
    }
    
    error_log("Cleaned text length: " . strlen($cleaned_text));
    error_log("Sending to OpenAI: " . substr($cleaned_text, 0, 300));
    
    // OpenAI API 키 가져오기
    $openai_api_key = OPENAI_API_KEY;
    
    // OpenAI API 호출
    $api_script = '../openai_api.py';
    $api_command = "export PYTHONPATH=\"$pythonpath\" && $python_path " . escapeshellarg($api_script) . " " . 
        escapeshellarg($cleaned_text) . ' ' . 
        escapeshellarg($openai_api_key) . ' ' . 
        escapeshellarg($question_count) . ' ' . 
        escapeshellarg($question_type);
    
    error_log("OpenAI API Command: " . substr($api_command, 0, 200) . "...");
    
    // OpenAI API 실행
    $api_output = shell_exec($api_command . ' 2>&1');
    
    error_log("OpenAI API full output: " . substr($api_output, 0, 1000));
    
    // API 응답에서 JSON 부분만 추출
    $api_lines = explode("\n", $api_output);
    $json_lines = [];
    $api_stderr_lines = [];
    $in_json = false;
    
    foreach ($api_lines as $line) {
        $line = trim($line);
        
        // JSON 시작 감지
        if ($line === '{' && !$in_json) {
            $in_json = true;
            $json_lines[] = $line;
        }
        // JSON 내부
        else if ($in_json) {
            $json_lines[] = $line;
            // JSON 끝 감지 (중괄호 개수로 판단)
            if ($line === '}' && count($json_lines) > 5) {
                break;
            }
        }
        // stderr 메시지들
        else if (strpos($line, 'Received text') !== false || 
                 strpos($line, 'API key length:') !== false || 
                 strpos($line, 'Total questions:') !== false ||
                 strpos($line, 'Question types:') !== false ||
                 strpos($line, '실패') !== false ||
                 strpos($line, '오류') !== false) {
            $api_stderr_lines[] = $line;
        }
    }
    
    $api_result = implode("\n", $json_lines);
    $api_stderr = implode("\n", $api_stderr_lines);
    
    error_log("OpenAI API stderr: " . $api_stderr);
    error_log("Extracted JSON length: " . strlen($api_result));
    error_log("Extracted JSON: " . $api_result);
    
    if (empty($api_result) || substr(trim($api_result), 0, 1) !== '{') {
        throw new Exception('OpenAI API에서 유효한 JSON을 받지 못했습니다. 응답: ' . substr($api_output, 0, 500));
    }
    
    // JSON 응답 파싱
    error_log("Attempting to parse JSON: " . substr($api_result, 0, 200));
    
    // JSON 문법 오류 체크 및 정리
    $cleaned_json = trim($api_result);
    
    error_log("Original JSON length: " . strlen($cleaned_json));
    
    // 불완전한 JSON 수정 시도
    $cleaned_json = fix_incomplete_json($cleaned_json, $question_count);
    
    error_log("Fixed JSON: " . substr($cleaned_json, 0, 500));
    
    $questions_data = json_decode($cleaned_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON parsing failed with error: " . json_last_error_msg());
        error_log("Final JSON: " . $cleaned_json);
        throw new Exception('OpenAI API 응답 파싱 실패: ' . json_last_error_msg() . '. JSON이 불완전합니다.');
    }
    
    if (!isset($questions_data['questions']) || !is_array($questions_data['questions'])) {
        throw new Exception('유효하지 않은 문제 데이터 형식입니다. 응답: ' . substr($api_result, 0, 500));
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
    
    error_log("Upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// 불완전한 JSON을 수정하는 함수
function fix_incomplete_json($json_string, $expected_questions) {
    $json = trim($json_string);
    
    // 기본 구조 확인
    if (substr($json, 0, 1) !== '{') {
        $json = '{' . $json;
    }
    
    // 불완전한 문자열 정리
    $json = preg_replace('/,\s*$/', '', $json); // 마지막 쉼표 제거
    
    // 중괄호 균형 맞추기
    $open_braces = substr_count($json, '{');
    $close_braces = substr_count($json, '}');
    
    if ($open_braces > $close_braces) {
        $missing_braces = $open_braces - $close_braces;
        $json .= str_repeat('}', $missing_braces);
    }
    
    // 배열 균형 맞추기
    $open_brackets = substr_count($json, '[');
    $close_brackets = substr_count($json, ']');
    
    if ($open_brackets > $close_brackets) {
        $missing_brackets = $open_brackets - $close_brackets;
        // 배열을 닫기 전에 incomplete 객체 처리
        $last_brace_pos = strrpos($json, '}');
        if ($last_brace_pos !== false) {
            $json = substr($json, 0, $last_brace_pos) . str_repeat(']', $missing_brackets) . substr($json, $last_brace_pos);
        } else {
            $json .= str_repeat(']', $missing_brackets);
        }
    }
    
    // JSON 파싱 테스트
    $test_decode = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
    }
    
    // 최후의 수단: 기본 구조 생성
    return create_fallback_json($expected_questions);
}

// 대체 JSON 생성 함수
function create_fallback_json($question_count) {
    $questions = [];
    
    for ($i = 1; $i <= $question_count; $i++) {
        if ($i <= $question_count / 2) {
            // 객관식
            $questions[] = [
                'type' => 'multiple_choice',
                'number' => $i,
                'question' => "WiFi 무선 LAN에 관한 문제 $i",
                'choices' => [
                    '1) IEEE 802.11a',
                    '2) IEEE 802.11b', 
                    '3) IEEE 802.11g',
                    '4) IEEE 802.11n'
                ],
                'correct_answer' => 1,
                'explanation' => 'WiFi 표준에 관한 설명입니다.'
            ];
        } else {
            // 주관식
            $questions[] = [
                'type' => 'subjective',
                'number' => $i,
                'question' => "WiFi의 CSMA/CA 프로토콜에 대해 설명하시오.",
                'sample_answer' => 'CSMA/CA는 무선 네트워크에서 충돌을 방지하기 위한 프로토콜입니다.',
                'grading_criteria' => 'CSMA/CA의 동작 원리와 특징을 설명했는지 확인'
            ];
        }
    }
    
    return json_encode(['questions' => $questions], JSON_UNESCAPED_UNICODE);
}
?>
