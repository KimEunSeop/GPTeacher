<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// 디버깅: $_FILES 내용 확인
error_log("=== DEBUG: Upload request received ===");
error_log("POST data: " . json_encode($_POST));
error_log("FILES data: " . json_encode($_FILES));

if (isset($_FILES['pdf_file'])) {
    error_log("=== PDF file upload detected ===");
    
    // 파일 정보 확인
    $uploadedFile = $_FILES['pdf_file'];
    
    error_log("File info: " . json_encode($uploadedFile));
    
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error code: " . $uploadedFile['error']);
        echo json_encode(['success' => false, 'message' => '파일 업로드 중 오류가 발생했습니다. (Error: ' . $uploadedFile['error'] . ')']);
        exit;
    }
    
    // PDF 텍스트 추출
    $extractedText = extractPdfText($uploadedFile['tmp_name']);
    
    if (!$extractedText) {
        echo json_encode(['success' => false, 'message' => 'PDF 텍스트 추출에 실패했습니다.']);
        exit;
    }
    
    // PDF 텍스트가 오류 메시지인지 확인
    if (strpos($extractedText, "can't open file") !== false || strpos($extractedText, "No such file") !== false) {
        error_log("PDF extraction returned error: " . $extractedText);
        echo json_encode(['success' => false, 'message' => 'PDF 텍스트 추출에 실패했습니다.']);
        exit;
    }
    
    error_log("PDF text extracted successfully, length: " . strlen($extractedText));
    
    // OpenAI API로 문제 생성 (Python 스크립트 사용)
    $questions = generateQuestions($extractedText);
    
    if (!$questions) {
        echo json_encode(['success' => false, 'message' => 'GPT API 오류가 발생했습니다.']);
        exit;
    }
    
    // 문제를 데이터베이스에 저장
    $questionId = saveQuestions($questions, $uploadedFile['name']);
    
    if ($questionId) {
        echo json_encode(['success' => true, 'redirect' => 'questions.php?id=' . $questionId]);
    } else {
        echo json_encode(['success' => false, 'message' => '문제 저장에 실패했습니다.']);
    }
} else {
    error_log("=== No pdf_file in \$_FILES ===");
    error_log("Available keys in \$_FILES: " . implode(', ', array_keys($_FILES)));
    echo json_encode(['success' => false, 'message' => '파일이 업로드되지 않았습니다.']);
}

// PDF 텍스트 추출 함수
function extractPdfText($filePath) {
    // 올바른 파일명으로 수정
    $pythonScript = dirname(__DIR__) . '/scripts/extract_pdf.py';
    
    // 파일 존재 확인
    if (!file_exists($pythonScript)) {
        error_log("PDF script not found at: $pythonScript");
        return false;
    }
    
    error_log("Using PDF script: $pythonScript");
    
    $command = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($filePath) . " 2>&1";
    error_log("PDF extraction command: $command");
    
    $output = shell_exec($command);
    error_log("Python output length: " . strlen($output));
    error_log("Python output preview: " . substr($output, 0, 200));
    
    if (!$output) {
        error_log("PDF extraction failed: no output");
        return false;
    }
    
    $trimmedOutput = trim($output);
    
    // 오류 메시지 체크
    if (strpos($trimmedOutput, "can't open file") !== false || 
        strpos($trimmedOutput, "No such file") !== false ||
        strpos($trimmedOutput, "Error") !== false ||
        strpos($trimmedOutput, "Traceback") !== false) {
        error_log("PDF extraction error: " . $trimmedOutput);
        return false;
    }
    
    return $trimmedOutput;
}

// NEW: Python 기반 OpenAI API 함수
function generateQuestions($text) {
    error_log("=== Starting Python-based question generation ===");
    
    $apiKey = OPENAI_API_KEY;
    
    // 루트 디렉토리의 Python 스크립트 경로
    $scriptPath = dirname(__DIR__) . '/openai_api.py';
    
    // 파일 존재 확인
    if (!file_exists($scriptPath)) {
        error_log("CRITICAL: Python script not found at: $scriptPath");
        return false;
    }
    
    error_log("Python script found at: $scriptPath");
    
    // 텍스트 길이 제한
    $limitedText = substr($text, 0, 3000);
    error_log("Text length: " . strlen($limitedText));
    
    // Python 스크립트 실행
    $rootDir = dirname(__DIR__);
    $escapedText = escapeshellarg($limitedText);
    $escapedApiKey = escapeshellarg($apiKey);
    
    $command = "cd '$rootDir' && python3 openai_api.py $escapedText $escapedApiKey 2>&1";
    
    error_log("Executing Python command: $command");
    
    $output = shell_exec($command);
    
    error_log("Python script raw output: " . substr($output, 0, 200) . "...");
    
    if (!$output) {
        error_log("ERROR: No output from Python script");
        return false;
    }
    
    $trimmedOutput = trim($output);
    
    // 에러 메시지 체크
    if (strpos($trimmedOutput, 'HTTP') !== false || strpos($trimmedOutput, 'Exception') !== false) {
        error_log("Python script returned error: " . $trimmedOutput);
        return false;
    }
    
    if (strlen($trimmedOutput) < 50) {
        error_log("Python output too short, likely an error: " . $trimmedOutput);
        return false;
    }
    
    error_log("=== Python question generation SUCCESS ===");
    return $trimmedOutput;
}

// 문제를 데이터베이스에 저장
function saveQuestions($questionsText, $fileName) {
    $conn = connectDB();
    
    if (!$conn) {
        error_log("Database connection failed");
        return false;
    }
    
    // 세션에서 사용자 ID 가져오기
    session_start();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        error_log("User not logged in");
        return false;
    }
    
    // answer_text 컬럼에도 값을 넣어야 함
    try {
        $stmt = $conn->prepare("INSERT INTO questions (user_id, title, question_text, answer_text, pdf_filename, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $title = "Generated from: " . $fileName;
        
        // GPT 응답에서 정답 부분을 추출하거나, 임시로 기본값 설정
        $answerText = "정답은 문제와 함께 제공됩니다."; // 임시 값
        
        if ($stmt->execute([$userId, $title, $questionsText, $answerText, $fileName])) {
            $questionId = $conn->lastInsertId();
            return $questionId;
        } else {
            error_log("Failed to save questions: " . implode(", ", $stmt->errorInfo()));
            return false;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// 데이터베이스 연결 함수
function connectDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
?>
