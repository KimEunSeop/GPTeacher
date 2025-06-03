<?php
// includes/auth.php
function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => '로그인이 필요합니다.',
            'redirect' => 'login.html'
        ]);
        exit();
    }
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email']
    ];
}
?>
