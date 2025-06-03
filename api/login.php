<?php
// api/login.php
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
    
    // 유효성 검사
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => '이메일을 입력해주세요.']);
        exit;
    }
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '비밀번호를 입력해주세요.']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '올바른 이메일 형식이 아닙니다.']);
        exit;
    }
    
    // 데이터베이스 연결
    $db = new Database();
    $pdo = $db->getConnection();
    
    // 사용자 조회
    $stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '등록되지 않은 이메일입니다.']);
        exit;
    }
    
    // 비밀번호 확인
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => '비밀번호가 올바르지 않습니다.']);
        exit;
    }
    
    // 세션 설정
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['login_time'] = time();
    
    // 세션 재생성 (보안)
    session_regenerate_id(true);
    
    echo json_encode([
        'success' => true,
        'message' => '로그인 성공',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email']
        ]
    ]);
    
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
