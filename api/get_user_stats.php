<?php
// api/get_user_stats.php - 사용자 통계 조회
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth();

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $userId = $_SESSION['user_id'];
    
    // 기본 사용자 정보
    $stmt = $pdo->prepare("SELECT email, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // 총 문제 수
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalQuestions = $stmt->fetch()['total'];
    
    // 이번 달 문제 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM questions 
        WHERE user_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
    ");
    $stmt->execute([$userId]);
    $thisMonthQuestions = $stmt->fetch()['total'];
    
    // 가입 일수
    $joinDate = new DateTime($user['created_at']);
    $now = new DateTime();
    $daysSinceJoin = $now->diff($joinDate)->days;
    
    // 마지막 활동일
    $stmt = $pdo->prepare("SELECT MAX(created_at) as last_activity FROM questions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lastActivity = $stmt->fetch()['last_activity'];
    
    // 연속 사용일 계산 (간단한 버전)
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date 
        FROM questions 
        WHERE user_id = ? 
        GROUP BY DATE(created_at) 
        ORDER BY date DESC 
        LIMIT 30
    ");
    $stmt->execute([$userId]);
    $activityDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $streak = calculateStreak($activityDates);
    
    // 월별 데이터 (최근 6개월)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM questions 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$userId]);
    $monthlyData = $stmt->fetchAll();
    
    // 최근 6개월 데이터 보완 (빈 월을 0으로 채우기)
    $completeMonthlyData = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $found = false;
        foreach ($monthlyData as $data) {
            if ($data['month'] === $month) {
                $completeMonthlyData[] = [
                    'month' => date('m월', strtotime($month . '-01')),
                    'count' => (int)$data['count']
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $completeMonthlyData[] = [
                'month' => date('m월', strtotime($month . '-01')),
                'count' => 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'email' => $user['email'],
            'join_date' => $user['created_at'],
            'total_questions' => (int)$totalQuestions,
            'this_month_questions' => (int)$thisMonthQuestions,
            'days_since_join' => $daysSinceJoin,
            'last_activity' => $lastActivity,
            'streak' => $streak,
            'monthly_data' => $completeMonthlyData
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get user stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '통계를 불러올 수 없습니다.']);
}

function calculateStreak($dates) {
    if (empty($dates)) return 0;
    
    $streak = 0;
    $currentDate = new DateTime();
    
    foreach ($dates as $dateStr) {
        $date = new DateTime($dateStr);
        $diff = $currentDate->diff($date)->days;
        
        if ($diff === $streak) {
            $streak++;
            $currentDate = $date;
        } else {
            break;
        }
    }
    
    return $streak;
}
?>
