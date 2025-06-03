<?php
// api/simple_upload.php - 업로드 테스트용
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. 로그인 확인
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => '로그인이 필요합니다.',
        'step' => '로그인 체크'
    ]);
    exit;
}

// 2. POST 요청 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'POST 요청이 필요합니다.',
        'method' => $_SERVER['REQUEST_METHOD'],
        'step' => 'HTTP 메소드 체크'
    ]);
    exit;
}

// 3. 파일과 제목 확인
if (!isset($_FILES['pdf_file'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'PDF 파일이 없습니다.',
        'files' => $_FILES,
        'step' => '파일 존재 체크'
    ]);
    exit;
}

if (!isset($_POST['title'])) {
    echo json_encode([
        'success' => false, 
        'message' => '제목이 없습니다.',
        'post' => $_POST,
        'step' => '제목 체크'
    ]);
    exit;
}

$file = $_FILES['pdf_file'];
$title = $_POST['title'];

// 4. 파일 업로드 오류 확인
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => '파일이 너무 큽니다 (php.ini 설정)',
        UPLOAD_ERR_FORM_SIZE => '파일이 너무 큽니다 (HTML 설정)',
        UPLOAD_ERR_PARTIAL => '파일이 부분적으로만 업로드됨',
        UPLOAD_ERR_NO_FILE => '파일이 업로드되지 않음',
        UPLOAD_ERR_NO_TMP_DIR => '임시 폴더 없음',
        UPLOAD_ERR_CANT_WRITE => '디스크 쓰기 실패',
        UPLOAD_ERR_EXTENSION => 'PHP 확장에 의해 차단됨'
    ];
    
    echo json_encode([
        'success' => false,
        'message' => '파일 업로드 오류: ' . ($errors[$file['error']] ?? '알 수 없는 오류'),
        'error_code' => $file['error'],
        'step' => '파일 업로드 오류 체크'
    ]);
    exit;
}

// 5. 경로 설정 및 확인
$currentDir = getcwd();
$apiDir = __DIR__;
$projectDir = dirname($apiDir);
$uploadsDir = $projectDir . '/uploads/';

// 6. uploads 폴더 생성
if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0777, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'uploads 폴더를 생성할 수 없습니다.',
            'uploads_dir' => $uploadsDir,
            'project_dir' => $projectDir,
            'step' => 'uploads 폴더 생성'
        ]);
        exit;
    }
    chmod($uploadsDir, 0777);
}

// 7. 파일 이동
$fileName = 'test_' . time() . '_' . basename($file['name']);
$filePath = $uploadsDir . $fileName;

if (move_uploaded_file($file['tmp_name'], $filePath)) {
    // 성공!
    echo json_encode([
        'success' => true,
        'message' => '파일 업로드 성공! 🎉',
        'data' => [
            'title' => $title,
            'original_name' => $file['name'],
            'saved_name' => $fileName,
            'file_size' => $file['size'],
            'file_path' => $filePath,
            'uploads_dir' => $uploadsDir
        ],
        'step' => '완료'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '파일 이동 실패',
        'from' => $file['tmp_name'],
        'to' => $filePath,
        'step' => '파일 이동'
    ]);
}
?>
