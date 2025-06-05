// question_list.js - 문제 목록 페이지 전용 JavaScript

let currentUser = null;
let currentQuestionSetId = null;
let allQuestions = [];

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    // URL에서 question_set_id 추출
    const urlParams = new URLSearchParams(window.location.search);
    currentQuestionSetId = urlParams.get('set_id');
    
    if (!currentQuestionSetId) {
        alert('유효하지 않은 문제집입니다.');
        window.location.href = 'dashboard.html';
        return;
    }

    checkAuth();
    loadQuestionSet();
    setupFilters();
});

// 인증 확인
async function checkAuth() {
    try {
        const response = await fetch('api/check_login.php');
        const result = await response.json();
        
        if (!result.logged_in) {
            window.location.href = 'login.html';
            return;
        }
        
        currentUser = result.user;
        document.getElementById('userInfo').textContent = result.user.email;
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = 'login.html';
    }
}

// 문제집 정보 및 문제 목록 로드
async function loadQuestionSet() {
    try {
        const response = await fetch(`api/get_questions_by_set.php?set_id=${encodeURIComponent(currentQuestionSetId)}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || '문제집을 불러올 수 없습니다.');
        }

        // 문제집 정보 표시
        displayQuestionSetInfo(result.question_set_info);
        
        // 문제 목록 저장 및 표시
        allQuestions = result.questions;
        displayQuestions(allQuestions);

    } catch (error) {
        console.error('Load question set error:', error);
        document.getElementById('questionsList').innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">❌</div>
                <div class="empty-title">오류 발생</div>
                <div class="empty-desc">${error.message}</div>
            </div>
        `;
    }
}

// 문제집 정보 표시
function displayQuestionSetInfo(info) {
    document.getElementById('questionSetTitle').textContent = info.title || info.pdf_filename;
    
    const questionSetInfo = document.getElementById('questionSetInfo');
    questionSetInfo.innerHTML = `
        <span>📄 ${info.pdf_filename}</span>
        <span>📅 ${new Date(info.created_at).toLocaleDateString('ko-KR')}</span>
    `;

    const questionSetStats = document.getElementById('questionSetStats');
    questionSetStats.innerHTML = `
        <div class="stat-item">
            <div class="stat-number">${info.total_questions}</div>
            <div class="stat-label">총 문제</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">${info.multiple_choice_count}</div>
            <div class="stat-label">객관식</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">${info.subjective_count}</div>
            <div class="stat-label">주관식</div>
        </div>
    `;
}

// 문제 목록 표시
function displayQuestions(questions) {
    const questionsList = document.getElementById('questionsList');
    
    if (!questions || questions.length === 0) {
        questionsList.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <div class="empty-title">문제가 없습니다</div>
                <div class="empty-desc">이 문제집에는 아직 문제가 없습니다.</div>
            </div>
        `;
        return;
    }

    questionsList.innerHTML = '';
    
    questions.forEach((question) => {
        const questionItem = document.createElement('div');
        questionItem.className = 'question-item';
        questionItem.dataset.type = question.question_type;
        
        const typeLabel = question.question_type === 'multiple_choice' ? '객관식' : '주관식';
        const typeBadgeClass = question.question_type === 'multiple_choice' ? 'badge-multiple' : 'badge-subjective';
        
        // 문제 미리보기 생성
        let preview = question.question_text;
        if (question.question_type === 'multiple_choice' && question.choices) {
            try {
                const choicesData = JSON.parse(question.choices);
                if (choicesData.choices && Array.isArray(choicesData.choices)) {
                    preview += '\n' + choicesData.choices.slice(0, 2).join('\n') + '...';
                }
            } catch (e) {
                // choices 파싱 실패시 그냥 문제 텍스트만 표시
            }
        }
        
        questionItem.innerHTML = `
            <div class="question-header">
                <span class="question-number">${question.question_number}번</span>
                <span class="question-type-badge ${typeBadgeClass}">${typeLabel}</span>
            </div>
            <div class="question-preview">${preview}</div>
        `;
        
        // 클릭시 문제 상세 페이지로 이동
        questionItem.addEventListener('click', function() {
            window.location.href = `question_detail.html?id=${question.id}`;
        });
        
        questionsList.appendChild(questionItem);
    });
}

// 필터 설정
function setupFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // 활성 버튼 변경
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // 필터 적용
            const filterType = this.dataset.filter;
            filterQuestions(filterType);
        });
    });
}

// 문제 필터링
function filterQuestions(filterType) {
    let filteredQuestions = allQuestions;
    
    if (filterType !== 'all') {
        filteredQuestions = allQuestions.filter(question => question.question_type === filterType);
    }
    
    displayQuestions(filteredQuestions);
}
