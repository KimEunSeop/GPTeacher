// dashboard.js - 대시보드 페이지 전용 JavaScript

let selectedFile = null;
let generatedQuestionSetId = null;

// 페이지 로드시 초기화
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎓 대시보드 로드됨');
    
    checkAuth();
    setupUpload();
    setupSlider();
    loadQuestionSets();
});

// 인증 확인 및 사용자 정보 표시
async function checkAuth() {
    try {
        const response = await fetch('api/check_login.php');
        const result = await response.json();
        
        if (!result.logged_in) {
            window.location.href = 'login.html';
            return;
        }
        
        // 사용자 정보 표시
        document.getElementById('userInfo').textContent = result.user.email;
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = 'login.html';
    }
}

// 파일 선택
function selectFile() {
    document.getElementById('fileInput').click();
}

// 업로드 설정
function setupUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');

    // 드래그 앤 드롭
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    // 파일 선택
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    });
}

// 파일 처리
function handleFile(file) {
    console.log('📄 파일 선택됨:', file.name);
    
    if (!file.type.includes('pdf')) {
        alert('PDF 파일만 업로드 가능합니다.');
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        alert('파일 크기는 10MB 이하여야 합니다.');
        return;
    }

    selectedFile = file;
    
    // 업로드 영역 업데이트
    const uploadArea = document.getElementById('uploadArea');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    uploadArea.classList.add('file-selected');
    uploadArea.querySelector('.upload-icon').textContent = '📄';
    uploadArea.querySelector('.upload-text').textContent = '파일이 선택되었습니다';
    uploadArea.querySelector('.upload-hint').textContent = '아래에서 문제 생성 옵션을 설정하세요';
    uploadArea.querySelector('.upload-button').textContent = '다른 파일 선택';
    uploadArea.querySelector('.upload-button').onclick = resetFile;
    
    // 파일 정보 표시
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    fileInfo.classList.add('show');
    
    // 옵션 표시
    document.getElementById('questionOptions').style.display = 'block';
}

// 파일 초기화
function resetFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('questionOptions').style.display = 'none';
    
    const uploadArea = document.getElementById('uploadArea');
    const fileInfo = document.getElementById('fileInfo');
    
    uploadArea.classList.remove('file-selected');
    uploadArea.querySelector('.upload-icon').textContent = '📄';
    uploadArea.querySelector('.upload-text').textContent = '파일을 끌어다 놓거나 클릭하여 업로드';
    uploadArea.querySelector('.upload-hint').textContent = 'PDF 파일만 지원 (최대 10MB)';
    uploadArea.querySelector('.upload-button').textContent = '파일 선택하기';
    uploadArea.querySelector('.upload-button').onclick = selectFile;
    
    fileInfo.classList.remove('show');
    
    setupUpload(); // 이벤트 다시 설정
}

// 파일 크기 포맷팅
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// 슬라이더 설정
function setupSlider() {
    const slider = document.getElementById('questionCount');
    const value = document.getElementById('questionCountValue');
    
    slider.addEventListener('input', function() {
        value.textContent = this.value;
    });
}

// 문제 생성 시작
function startGeneration() {
    if (!selectedFile) {
        alert('먼저 PDF 파일을 선택해주세요.');
        return;
    }

    const title = prompt('문제집 제목을 입력하세요:', selectedFile.name.replace('.pdf', ''));
    if (!title) return;

    const questionCount = document.getElementById('questionCount').value;
    const questionType = document.querySelector('input[name="questionType"]:checked').value;

    console.log('🚀 문제 생성 시작:', { title, questionCount, questionType });

    // 진행률 표시
    document.getElementById('progressSection').style.display = 'block';
    document.getElementById('questionOptions').style.display = 'none';
    updateProgress(10, 'PDF 업로드 중...');

    // 실제 업로드 (여기서 API 호출)
    uploadAndGenerate(selectedFile, title, questionCount, questionType);
}

// 업로드 및 생성
async function uploadAndGenerate(file, title, questionCount, questionType) {
    const formData = new FormData();
    formData.append('pdf_file', file);
    formData.append('title', title);
    formData.append('question_count', questionCount);
    formData.append('question_type', questionType);

    try {
        updateProgress(30, 'AI 문제 생성 중...');
        
        const response = await fetch('api/upload_and_generate_v2.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        
        if (result.success) {
            updateProgress(100, '문제 생성 완료!');
            generatedQuestionSetId = result.question_set_id;
            
            setTimeout(() => {
                showSuccessModal(title, result.total_questions);
            }, 1000);
        } else {
            alert('문제 생성 실패: ' + result.message);
            resetGeneration();
        }
    } catch (error) {
        console.error('업로드 오류:', error);
        alert('업로드 중 오류가 발생했습니다.');
        resetGeneration();
    }
}

// 진행률 업데이트
function updateProgress(percent, text) {
    document.getElementById('progressFill').style.width = percent + '%';
    document.getElementById('progressText').textContent = text;
}

// 생성 초기화
function resetGeneration() {
    document.getElementById('progressSection').style.display = 'none';
    resetFile();
}

// 성공 모달 표시
function showSuccessModal(title, questionCount) {
    document.getElementById('progressSection').style.display = 'none';
    document.getElementById('modalMessage').textContent = `"${title}" 문제집이 성공적으로 생성되었습니다. (총 ${questionCount}개 문제)`;
    
    document.getElementById('viewQuestionsBtn').onclick = function() {
        window.location.href = `question_list.html?set_id=${generatedQuestionSetId}`;
    };
    
    document.getElementById('successModal').style.display = 'flex';
}

// 모달 닫기
function closeModal() {
    document.getElementById('successModal').style.display = 'none';
    resetFile();
    loadQuestionSets();
}

// 문제집 목록 로드
async function loadQuestionSets() {
    try {
        const response = await fetch('api/get_question_sets.php');
        const result = await response.json();
        
        const questionsList = document.getElementById('questionsList');
        
        if (result.success && result.question_sets && result.question_sets.length > 0) {
            questionsList.innerHTML = result.question_sets.map(set => `
                <div class="question-set-item" onclick="openQuestionSet('${set.question_set_id}')">
                    <div class="question-set-title">${set.display_title || set.title}</div>
                    <div class="question-set-info">
                        <span>${set.total_questions}개 문제</span>
                        <span>${new Date(set.created_at).toLocaleDateString('ko-KR')}</span>
                    </div>
                </div>
            `).join('');
        } else {
            questionsList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div>아직 생성된 문제집이 없습니다.<br>PDF를 업로드해서 첫 문제집을 만들어보세요!</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('문제집 로딩 오류:', error);
        document.getElementById('questionsList').innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">⚠️</div>
                <div>문제집 목록을 불러올 수 없습니다.</div>
            </div>
        `;
    }
}

// 문제집 열기
function openQuestionSet(questionSetId) {
    window.location.href = `question_list.html?set_id=${questionSetId}`;
}

// 모달 외부 클릭시 닫기
document.addEventListener('DOMContentLoaded', function() {
    const successModal = document.getElementById('successModal');
    if (successModal) {
        successModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }
});
