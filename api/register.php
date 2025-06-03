<?php
// api/register.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않은 메소드입니다.']);
    exit;
}

try {
    // 입력 데이터 검증
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // 유효성 검사
    $errors = [];
    
    if (empty($email)) {
        $errors[] = '이메일을 입력해주세요.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '올바른 이메일 형식이 아닙니다.';
    }
    
    if (empty($password)) {
        $errors[] = '비밀번호를 입력해주세요.';
    } elseif (strlen($password) < 6) {
        $errors[] = '비밀번호는 최소 6자 이상이어야 합니다.';
    }
    
    if (empty($confirmPassword)) {
        $errors[] = '비밀번호 확인을 입력해주세요.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = '비밀번호가 일치하지 않습니다.';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }
    
    // 데이터베이스 연결
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 이메일 중복 확인
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '이미 등록된 이메일입니다.']);
        exit;
    }
    
    // 비밀번호 해시화
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // 사용자 등록
    $stmt = $pdo->prepare("INSERT INTO users (email, password, created_at) VALUES (?, ?, NOW())");
    $result = $stmt->execute([$email, $hashedPassword]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => '회원가입이 완료되었습니다.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => '회원가입 중 오류가 발생했습니다.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => '데이터베이스 오류가 발생했습니다.'
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => '서버 오류가 발생했습니다.'
    ]);
}
?>
