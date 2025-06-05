// auth.js - 인증 관련 공통 JavaScript 함수들

// 로그인 상태 확인
async function checkAuth() {
    try {
        const response = await fetch('api/check_login.php');
        const result = await response.json();
        
        if (!result.logged_in) {
            window.location.href = 'login.html';
            return null;
        }
        
        return result.user;
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = 'login.html';
        return null;
    }
}

// 사용자 정보 표시
function displayUserInfo(user) {
    const userInfoElement = document.getElementById('userInfo');
    if (userInfoElement && user) {
        userInfoElement.textContent = user.email;
    }
}

// 로그인 폼 유효성 검사
function validateLoginForm() {
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    
    let isValid = true;
    
    // 이메일 검증
    if (!email.value) {
        showError(email, emailError, '이메일을 입력해주세요.');
        isValid = false;
    } else if (!isValidEmail(email.value)) {
        showError(email, emailError, '올바른 이메일 형식이 아닙니다.');
        isValid = false;
    } else {
        hideError(email, emailError);
    }
    
    // 비밀번호 검증
    if (!password.value) {
        showError(password, passwordError, '비밀번호를 입력해주세요.');
        isValid = false;
    } else {
        hideError(password, passwordError);
    }
    
    return isValid;
}

// 회원가입 폼 유효성 검사
function validateRegisterForm() {
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    
    let isValid = true;
    
    // 이메일 검증
    if (!email.value) {
        showFormError('이메일을 입력해주세요.');
        isValid = false;
    } else if (!isValidEmail(email.value)) {
        showFormError('올바른 이메일 형식이 아닙니다.');
        isValid = false;
    }
    
    // 비밀번호 검증
    if (!password.value) {
        showFormError('비밀번호를 입력해주세요.');
        isValid = false;
    } else if (password.value.length < 6) {
        showFormError('비밀번호는 최소 6자 이상이어야 합니다.');
        isValid = false;
    }
    
    // 비밀번호 확인 검증
    if (!confirmPassword.value) {
        showFormError('비밀번호 확인을 입력해주세요.');
        isValid = false;
    } else if (password.value !== confirmPassword.value) {
        showFormError('비밀번호가 일치하지 않습니다.');
        isValid = false;
    }
    
    return isValid;
}

// 이메일 유효성 검사
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// 오류 메시지 표시
function showError(input, errorElement, message) {
    input.classList.add('error');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
}

// 오류 메시지 숨기기
function hideError(input, errorElement) {
    input.classList.remove('error');
    errorElement.style.display = 'none';
}

// 폼 오류 메시지 표시 (일반적인 경우)
function showFormError(message) {
    alert(message);
}

// 로그인 처리
async function processLogin(formData) {
    try {
        const response = await fetch('api/login.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = 'dashboard.html';
        } else {
            showFormError(result.message || '로그인에 실패했습니다.');
        }
    } catch (error) {
        console.error('Login error:', error);
        showFormError('서버 오류가 발생했습니다. 다시 시도해주세요.');
    }
}

// 회원가입 처리
async function processRegister(formData) {
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
            showFormError(result.message || '회원가입에 실패했습니다.');
        }
    } catch (error) {
        console.error('Register error:', error);
        showFormError('서버 오류가 발생했습니다. 다시 시도해주세요.');
    }
}

// 로딩 상태 표시/숨기기
function showLoading(button, loadingElement) {
    if (button) button.disabled = true;
    if (loadingElement) loadingElement.style.display = 'block';
}

function hideLoading(button, loadingElement) {
    if (button) button.disabled = false;
    if (loadingElement) loadingElement.style.display = 'none';
}

// 이미 로그인된 사용자 체크 (홈페이지용)
async function checkAlreadyLoggedIn() {
    try {
        const response = await fetch('api/check_login.php');
        const result = await response.json();
        
        if (result.logged_in) {
            window.location.href = 'dashboard.html';
        }
    } catch (error) {
        console.log('로그인 상태 확인 중 오류:', error);
    }
}
