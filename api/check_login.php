<?php
// api/check_login.php - 로그인 상태 확인
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (isLoggedIn()) {
    echo json_encode([
        'logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email']
        ]
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>
