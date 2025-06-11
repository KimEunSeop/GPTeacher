// mypage.js - 마이페이지 전용 JavaScript (즐겨찾기 기능 추가)

let currentUser = null;
let userStats = null;

document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    loadUserStats();
    loadRecentActivity();
    loadFavorites(); // 즐겨찾기 로드 추가
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
        // 헤더의 사용자 정보 업데이트 (첫 번째 userInfo)
        const headerUserInfo = document.querySelector('.user-info');
        if (headerUserInfo) {
            headerUserInfo.textContent = result.user.email;
        }
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = 'login.html';
    }
}

async function loadUserStats() {
    try {
        const response = await fetch('api/get_user_stats.php');
        const result = await response.json();
        
        if (result.success) {
            userStats = result.stats;
            updateStatsDisplay();
            updateUserInfo();
            drawChart();
        } else {
            console.error('Stats API failed:', result.message);
        }
    } catch (error) {
        console.error('Load stats error:', error);
    }
}

function updateStatsDisplay() {
    if (!userStats) return;
    
    const totalQuestions = document.getElementById('totalQuestions');
    const thisMonthQuestions = document.getElementById('thisMonthQuestions');
    const daysSinceJoin = document.getElementById('daysSinceJoin');
    const streak = document.getElementById('streak');
    
    if (totalQuestions) totalQuestions.textContent = userStats.total_questions || 0;
    if (thisMonthQuestions) thisMonthQuestions.textContent = userStats.this_month_questions || 0;
    if (daysSinceJoin) daysSinceJoin.textContent = userStats.days_since_join || 0;
    if (streak) streak.textContent = userStats.streak || 0;
}

function updateUserInfo() {
    if (!userStats) return;
    
    const userEmail = document.getElementById('userEmail');
    const joinDate = document.getElementById('joinDate');
    const lastActivity = document.getElementById('lastActivity');
    
    if (userEmail) userEmail.textContent = userStats.email;
    if (joinDate) joinDate.textContent = new Date(userStats.join_date).toLocaleDateString('ko-KR');
    if (lastActivity) {
        lastActivity.textContent = userStats.last_activity ? 
            new Date(userStats.last_activity).toLocaleDateString('ko-KR') : '활동 없음';
    }
}

async function loadRecentActivity() {
    try {
        const response = await fetch('api/get_recent_activity.php');
        const result = await response.json();
        
        const activityList = document.getElementById('activityList');
        
        if (!activityList) {
            console.error('activityList element not found');
            return;
        }
        
        if (result.success && result.activities && result.activities.length > 0) {
            activityList.innerHTML = result.activities.map(activity => `
                <div class="activity-item">
                    <div class="activity-icon">📝</div>
                    <div class="activity-content">
                        <div class="activity-title">${activity.title} 문제 생성</div>
                        <div class="activity-date">${new Date(activity.created_at).toLocaleDateString('ko-KR')} ${new Date(activity.created_at).toLocaleTimeString('ko-KR', {hour: '2-digit', minute: '2-digit'})}</div>
                    </div>
                </div>
            `).join('');
        } else {
            activityList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <div>아직 활동 내역이 없습니다.<br>첫 문제를 생성해보세요!</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Load activity error:', error);
        const activityList = document.getElementById('activityList');
        if (activityList) {
            activityList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">❌</div>
                    <div>활동 내역을 불러올 수 없습니다.</div>
                </div>
            `;
        }
    }
}

// 즐겨찾기 목록 로드 함수 추가
async function loadFavorites() {
    try {
        const response = await fetch('api/get_favorites.php?limit=5'); // 최대 5개만 표시
        const result = await response.json();
        
        const favoritesList = document.getElementById('favoritesList');
        const favoritesActions = document.getElementById('favoritesActions');
        
        if (!favoritesList) {
            console.error('favoritesList element not found');
            return;
        }
        
        if (result.success && result.favorites && result.favorites.length > 0) {
            favoritesList.innerHTML = result.favorites.map(favorite => `
                <div class="favorite-item" onclick="openFavoriteQuestion('${favorite.id}')">
                    <div class="favorite-icon">⭐</div>
                    <div class="favorite-content">
                        <div class="favorite-title">${favorite.title}</div>
                        <div class="favorite-date">${new Date(favorite.created_at).toLocaleDateString('ko-KR')}</div>
                    </div>
                </div>
            `).join('');
            
            // 전체 보기 버튼 표시 (즐겨찾기가 5개 이상이면)
            if (result.total_count > 5) {
                favoritesActions.style.display = 'block';
                const viewAllBtn = favoritesActions.querySelector('a');
                if (viewAllBtn) {
                    viewAllBtn.innerHTML = `전체 ${result.total_count}개 보기`;
                }
            }
        } else {
            favoritesList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">⭐</div>
                    <div style="font-size: 0.9rem; color: #999;">아직 즐겨찾기한 문제가 없습니다.<br>문제 상세 페이지에서 별표를 클릭해보세요!</div>
                </div>
            `;
            favoritesActions.style.display = 'none';
        }
    } catch (error) {
        console.error('Load favorites error:', error);
        const favoritesList = document.getElementById('favoritesList');
        if (favoritesList) {
            favoritesList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">❌</div>
                    <div style="font-size: 0.9rem; color: #999;">즐겨찾기 목록을 불러올 수 없습니다.</div>
                </div>
            `;
        }
    }
}

// 즐겨찾기 문제 열기 함수
function openFavoriteQuestion(questionId) {
    window.location.href = `question_detail.html?id=${questionId}`;
}

function drawChart() {
    if (!userStats || !userStats.monthly_data) {
        console.log('No chart data available');
        return;
    }
    
    const canvas = document.getElementById('monthlyChart');
    if (!canvas) {
        console.error('monthlyChart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    
    // 간단한 막대 차트 그리기
    const data = userStats.monthly_data;
    const maxValue = Math.max(...data.map(d => d.count));
    const chartHeight = 150;
    const chartWidth = canvas.width - 60;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // 배경
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // 막대 그리기
    const barWidth = chartWidth / data.length;
    data.forEach((item, index) => {
        const barHeight = maxValue > 0 ? (item.count / maxValue) * chartHeight : 0;
        const x = 30 + index * barWidth;
        const y = canvas.height - 30 - barHeight;
        
        // 막대
        const gradient = ctx.createLinearGradient(0, y, 0, y + barHeight);
        gradient.addColorStop(0, '#667eea');
        gradient.addColorStop(1, '#764ba2');
        ctx.fillStyle = gradient;
        ctx.fillRect(x + 5, y, barWidth - 10, barHeight);
        
        // 월 라벨
        ctx.fillStyle = '#666';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(item.month, x + barWidth/2, canvas.height - 10);
        
        // 값 라벨
        if (item.count > 0) {
            ctx.fillStyle = '#333';
            ctx.fillText(item.count, x + barWidth/2, y - 5);
        }
    });
}

async function downloadData() {
    try {
        const response = await fetch('api/export_data.php');
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `gpt_questions_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        alert('데이터 내보내기가 완료되었습니다.');
    } catch (error) {
        console.error('Export error:', error);
        alert('데이터 내보내기에 실패했습니다.');
    }
}
