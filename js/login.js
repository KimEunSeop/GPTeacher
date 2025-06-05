// login.js - 로그인 페이지 전용 JavaScript

const form = document.getElementById('loginForm');
const submitBtn = document.getElementById('submitBtn');
const loading = document.getElementById('loading');

function validateEmail() {
    const email = document.getElementById('email');
    const error = document.getElementById('emailError');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!email.value) {
        showError(email, error, '이메일을 입력해주세요.');
        return false;
    } else if (!emailRegex.test(email.value)) {
        showError(email, error, '올바른 이메일 형식이 아닙니다.');
        return false;
    } else {
        hideError(email, error);
        return true;
    }
}

function validatePassword() {
    const password = document.getElementById('password');
    const error = document.getElementById('passwordError');
    
    if (!password.value) {
        showError(password, error, '비밀번호를 입력해주세요.');
        return false;
    } else {
        hideError(password, error);
        return true;
    }
}

function showError(input, errorElement, message) {
    input.classList.add('error');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
}

function hideError(input, errorElement) {
    input.classList.remove('error');
    errorElement.style.display = 'none';
}

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // 유효성 검사
    const isEmailValid = validateEmail();
    const isPasswordValid = validatePassword();
    
    if (!isEmailValid || !isPasswordValid) {
        return;
    }
    
    // 로딩 시작
    submitBtn.disabled = true;
    loading.style.display = 'block';
    
    const formData = new FormData(form);
    
    try {
        const response = await fetch('api/login.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // 로그인 성공 시 대시보드로 이동
            window.location.href = 'dashboard.html';
        } else {
            alert(result.message || '로그인에 실패했습니다.');
        }
    } catch (error) {
        console.error('Login error:', error);
        alert('서버 오류가 발생했습니다. 다시 시도해주세요.');
    } finally {
        // 로딩 종료
        submitBtn.disabled = false;
        loading.style.display = 'none';
    }
});

// Enter 키로 로그인
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        form.dispatchEvent(new Event('submit'));
    }
});
