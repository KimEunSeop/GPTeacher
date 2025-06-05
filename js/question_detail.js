// question_detail.js - 문제 상세 페이지 전용 JavaScript

let currentQuestion = null;
let questionId = null;
let allQuestionsInSet = [];
let currentQuestionIndex = -1;
let questionSetId = null;

document.addEventListener('DOMContentLoaded', function() {
    // URL에서 question ID 추출 (디버그 추가)
    console.log('=== DEBUG: URL Parsing ===');
    console.log('Full URL:', window.location.href);
    console.log('Search params:', window.location.search);
    
    const urlParams = new URLSearchParams(window.location.search);
    questionId = urlParams.get('id');
    
    console.log('Extracted question ID:', questionId);
    console.log('Question ID type:', typeof questionId);
    
    if (!questionId) {
        console.error('No question ID found in URL');
        showError('문제 ID가 없습니다.');
        return;
    }

    checkAuth();
    loadQuestion();
});

async function checkAuth() {
    try {
        const response = await fetch('api/check_login.php');
        const result = await response.json();
        
        if (!result.logged_in) {
            window.location.href = 'login.html';
        }
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = 'login.html';
    }
}

async function loadQuestion() {
    try {
        console.log('=== DEBUG: Loading Question ===');
        console.log('Requesting URL:', `api/get_question.php?id=${questionId}`);
        
        const response = await fetch(`api/get_question.php?id=${questionId}`);
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('Response data:', result);
        
        if (result.success && result.question) {
            currentQuestion = result.question;
            displayQuestion();
            
            // 문제집 데이터 로드는 별도로 실행 (실패해도 메인 기능에 영향 없음)
            loadQuestionSetData().catch(err => {
                console.warn('Question set loading failed:', err);
            });
        } else {
            showError(result.message || '문제를 불러올 수 없습니다.');
        }
    } catch (error) {
        console.error('Load question error:', error);
        showError('서버 오류가 발생했습니다. 페이지를 새로고침해보세요.');
    }
}

async function loadQuestionSetData() {
    try {
        // 파일명에서 question_set_id 추출
        if (!currentQuestion || !currentQuestion.pdf_filename) {
            console.warn('No filename available for set_id extraction');
            updateNavigationButtons();
            return;
        }
        
        const fileName = currentQuestion.pdf_filename;
        console.log('Trying to extract set_id from filename:', fileName);
        
        let setId = null;
        if (fileName.startsWith('qs_')) {
            const parts = fileName.split('_');
            if (parts.length >= 4) {
                setId = `${parts[0]}_${parts[1]}_${parts[2]}_${parts[3]}`;
                console.log('Extracted set_id:', setId);
            }
        }
        
        if (!setId) {
            console.warn('Cannot extract question_set_id from filename');
            updateNavigationButtons();
            return;
        }
        
        console.log('Loading question set with ID:', setId);
        
        const response = await fetch(`api/get_questions_by_set.php?set_id=${encodeURIComponent(setId)}`);
        
        if (!response.ok) {
            console.error(`Failed to load question set: HTTP ${response.status}`);
            updateNavigationButtons();
            return;
        }
        
        const result = await response.json();
        console.log('Question set result:', result);
        
        if (result.success && result.questions && Array.isArray(result.questions)) {
            allQuestionsInSet = result.questions;
            questionSetId = setId;
            
            // 현재 문제의 인덱스 찾기
            currentQuestionIndex = allQuestionsInSet.findIndex(q => 
                q.id == questionId || q.id === parseInt(questionId)
            );
            
            console.log('Current question index:', currentQuestionIndex);
            console.log('Total questions in set:', allQuestionsInSet.length);
        } else {
            console.error('Invalid question set data received');
        }
        
        updateNavigationButtons();
        
    } catch (error) {
        console.error('Load question set error:', error);
        updateNavigationButtons();
    }
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const counter = document.getElementById('questionCounter');
    
    if (!prevBtn || !nextBtn || !counter) {
        console.error('Navigation elements not found');
        return;
    }
    
    if (allQuestionsInSet.length > 0 && currentQuestionIndex >= 0) {
        // 카운터 업데이트
        counter.textContent = `문제 ${currentQuestionIndex + 1} / ${allQuestionsInSet.length}`;
        
        // 이전 버튼
        prevBtn.disabled = currentQuestionIndex <= 0;
        
        // 다음 버튼
        nextBtn.disabled = currentQuestionIndex >= allQuestionsInSet.length - 1;
    } else {
        // 기본값 설정
        counter.textContent = '문제 1 / 1';
        prevBtn.disabled = true;
        nextBtn.disabled = true;
    }
}

function goToPreviousQuestion() {
    if (currentQuestionIndex > 0 && allQuestionsInSet.length > 0) {
        const prevQuestion = allQuestionsInSet[currentQuestionIndex - 1];
        if (prevQuestion && prevQuestion.id) {
            window.location.href = `question_detail.html?id=${prevQuestion.id}`;
        }
    }
}

function goToNextQuestion() {
    if (currentQuestionIndex < allQuestionsInSet.length - 1 && allQuestionsInSet.length > 0) {
        const nextQuestion = allQuestionsInSet[currentQuestionIndex + 1];
        if (nextQuestion && nextQuestion.id) {
            window.location.href = `question_detail.html?id=${nextQuestion.id}`;
        }
    }
}

function displayQuestion() {
    // 로딩 상태 숨기기
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('questionContent').style.display = 'block';

    // 기본 정보 표시
    document.getElementById('questionTitle').textContent = currentQuestion.title;
    document.getElementById('createdDate').textContent = new Date(currentQuestion.created_at).toLocaleDateString('ko-KR');
    document.getElementById('pdfFileName').textContent = currentQuestion.pdf_filename;

    // 문제와 정답 파싱 및 표시
    parseAndDisplayContent();
}

function parseAndDisplayContent() {
    const questions = parseQuestions(currentQuestion.question_text);
    const answers = parseAnswers(currentQuestion.answer_text);

    displayQuestions(questions);
    displayAnswers(answers);
    displayStudyMode(questions, answers);
}

function parseQuestions(questionText) {
    const questions = [];
    const lines = questionText.split('\n');
    let currentQuestion = '';
    let questionNumber = 0;

    lines.forEach(line => {
        line = line.trim();
        if (line.match(/^Q\d+\./)) {
            if (currentQuestion) {
                questions.push({
                    number: questionNumber,
                    text: currentQuestion.trim()
                });
            }
            questionNumber++;
            currentQuestion = line.replace(/^Q\d+\./, '').trim();
        } else if (line && !line.match(/^A\d+\./)) {
            currentQuestion += ' ' + line;
        }
    });

    if (currentQuestion) {
        questions.push({
            number: questionNumber,
            text: currentQuestion.trim()
        });
    }

    return questions;
}

function parseAnswers(answerText) {
    const answers = [];
    const lines = answerText.split('\n');
    let currentAnswer = '';
    let answerNumber = 0;

    lines.forEach(line => {
        line = line.trim();
        if (line.match(/^A\d+\./)) {
            if (currentAnswer) {
                answers.push({
                    number: answerNumber,
                    text: currentAnswer.trim()
                });
            }
            answerNumber++;
            currentAnswer = line.replace(/^A\d+\./, '').trim();
        } else if (line && !line.match(/^Q\d+\./)) {
            currentAnswer += ' ' + line;
        }
    });

    if (currentAnswer) {
        answers.push({
            number: answerNumber,
            text: currentAnswer.trim()
        });
    }

    return answers;
}

function displayQuestions(questions) {
    const container = document.getElementById('questionsList');
    container.innerHTML = questions.map(q => `
        <div class="question-item">
            <div class="question-number">Q${q.number}.</div>
            <div class="question-text">${q.text}</div>
        </div>
    `).join('');
}

function displayAnswers(answers) {
    const container = document.getElementById('answersList');
    container.innerHTML = answers.map(a => `
        <div class="answer-item">
            <div class="answer-number">A${a.number}.</div>
            <div class="answer-text">${a.text}</div>
        </div>
    `).join('');
}

function displayStudyMode(questions, answers) {
    const container = document.getElementById('studyQuestions');
    container.innerHTML = questions.map((q, index) => `
        <div class="study-question" id="study-${index}">
            <div class="question-number">Q${q.number}.</div>
            <div class="question-text">${q.text}</div>
            <button class="reveal-btn" onclick="revealAnswer(${index})">정답 보기</button>
            <div class="revealed-answer" id="answer-${index}">
                <strong>정답:</strong> ${answers[index]?.text || '정답을 찾을 수 없습니다.'}
            </div>
        </div>
    `).join('');
}

function revealAnswer(index) {
    const studyQuestion = document.getElementById(`study-${index}`);
    const revealedAnswer = document.getElementById(`answer-${index}`);
    const button = studyQuestion.querySelector('.reveal-btn');
    
    studyQuestion.classList.add('revealed');
    revealedAnswer.classList.add('show');
    button.style.display = 'none';
}

function switchTab(tabName) {
    // 모든 탭 버튼과 패널 비활성화
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
    
    // 선택된 탭 활성화
    event.target.classList.add('active');
    document.getElementById(tabName + 'Tab').classList.add('active');
}

function showError(message) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('errorState').style.display = 'block';
    document.getElementById('errorState').innerHTML = `
        <div>❌</div>
        <div>${message}</div>
    `;
}

async function downloadQuestion() {
    try {
        const response = await fetch(`api/download_question.php?id=${questionId}`);
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${currentQuestion.title}.txt`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (error) {
        console.error('Download error:', error);
        alert('다운로드에 실패했습니다.');
    }
}

async function deleteQuestion() {
    if (!confirm('정말로 이 문제를 삭제하시겠습니까? 삭제된 문제는 복구할 수 없습니다.')) {
        return;
    }

    try {
        const response = await fetch(`api/delete_question.php?id=${questionId}`, {
            method: 'DELETE'
        });
        const result = await response.json();
        
        if (result.success) {
            alert('문제가 삭제되었습니다.');
            window.location.href = 'dashboard.html';
        } else {
            alert(result.message || '삭제에 실패했습니다.');
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('삭제 중 오류가 발생했습니다.');
    }
}
