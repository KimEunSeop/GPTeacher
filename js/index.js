// index.js - 홈페이지 전용 JavaScript

// 로그인 상태 확인
fetch('api/check_login.php')
    .then(response => response.json())
    .then(data => {
        if (data.logged_in) {
            // 이미 로그인된 사용자는 대시보드로 리다이렉트
            window.location.href = 'dashboard.html';
        }
    })
    .catch(error => {
        console.log('로그인 상태 확인 중 오류:', error);
    });
    