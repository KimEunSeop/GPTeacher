// register.js - 회원가입 페이지 전용 JavaScript

const form = document.getElementById('registerForm');
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
    } else if (password.value.length < 6) {
        showError(password, error, '비밀번호는 최소 6자 이상이어야 합니다.');
        return false;
    } else {
        hideError(password, error);
        return true;
    }
}

function validateConfirmPassword() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const error = document.getElementById('confirmPasswordError');
    
    if (!confirmPassword.value) {
        showError(confirmPassword, error, '비밀번호 확인을 입력해주세요.');
        return false;
    } else if (password.value !== confirmPassword.value) {
        showError(confirmPassword, error, '비밀번호가 일치하지 않습니다.');
        return false;
    } else {
        hideError(confirmPassword, error);
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
    const isConfirmPasswordValid = validateConfirmPassword();
    
    if (!isEmailValid || !isPasswordValid || !isConfirmPasswordValid) {
        return;
    }
    
    // 로딩 시작
    submitBtn.disabled = true;
    loading.style.display = 'block';
    
    const formData = new FormData(form);
    
    try {
        const response = await fetch('api/register.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('회원가입이 완료되었습니다. 로그인 페이지로 이동합니다.');
            window.location.href = 'login.html';
        } else {
            alert(result.message || '회원가입에 실패했습니다.');
        }
    } catch (error) {
        console.error('Register error:', error);
        alert('서버 오류가 발생했습니다. 다시 시도해주세요.');
    } finally {
        // 로딩 종료
        submitBtn.disabled = false;
        loading.style.display = 'none';
    }
});

// 실시간 유효성 검사
document.getElementById('email').addEventListener('blur', validateEmail);
document.getElementById('password').addEventListener('blur', validatePassword);
document.getElementById('confirmPassword').addEventListener('blur', validateConfirmPassword);

// 비밀번호 변경시 확인 필드도 재검사
document.getElementById('password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirmPassword');
    if (confirmPassword.value) {
        validateConfirmPassword();
    }
});
