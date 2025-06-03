<?php
// debug_extract.php - PDF 텍스트 추출 디버깅
session_start();
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    die('로그인이 필요합니다.');
}

echo "<h1>PDF 텍스트 추출 디버깅</h1>";

// 가장 최근 PDF 파일 찾기
$upload_dir = 'uploads/';
$pdf_files = glob($upload_dir . '*.pdf');

if (empty($pdf_files)) {
    echo "<p>업로드된 PDF 파일이 없습니다.</p>";
    exit;
}

// 가장 최근 파일 선택
usort($pdf_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$latest_pdf = $pdf_files[0];
echo "<h2>테스트 파일: " . basename($latest_pdf) . "</h2>";

// 웹서버에서와 동일한 환경으로 실행
$extract_script = 'scripts/extract_pdf.py';
$python_path = '/usr/bin/python3';

$pythonpath_parts = [
    '/Users/eunseop/Library/Python/3.9/lib/python/site-packages',
    '/Library/Developer/CommandLineTools/Library/Frameworks/Python3.framework/Versions/3.9/lib/python3.9/site-packages',
    '/Library/Python/3.9/site-packages'
];
$pythonpath = implode(':', $pythonpath_parts);

$extract_command = "export PYTHONPATH=\"$pythonpath\" && $python_path " . escapeshellarg($extract_script) . " " . escapeshellarg($latest_pdf);

echo "<h3>실행 명령어:</h3>";
echo "<code>" . htmlspecialchars($extract_command) . "</code>";

echo "<h3>전체 출력:</h3>";
$output = shell_exec($extract_command . ' 2>&1');
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9; white-space: pre-wrap; font-family: monospace; max-height: 400px; overflow-y: auto;'>";
echo htmlspecialchars($output);
echo "</div>";

echo "<h3>출력 분석:</h3>";
$lines = explode("\n", $output);
$stderr_lines = [];
$stdout_lines = [];

foreach ($lines as $line) {
    if (strpos($line, 'PDF 파일 처리 시작:') !== false || 
        strpos($line, '시도 중...') !== false || 
        strpos($line, '텍스트 길이:') !== false ||
        strpos($line, '최종') !== false ||
        strpos($line, 'Python 경로:') !== false ||
        strpos($line, '오류:') !== false ||
        strpos($line, 'Error:') !== false) {
        $stderr_lines[] = $line;
    } else if (!empty(trim($line))) {
        $stdout_lines[] = $line;
    }
}

$extracted_text = implode("\n", $stdout_lines);
$stderr_output = implode("\n", $stderr_lines);

echo "<h4>STDERR (디버그 메시지):</h4>";
echo "<div style='border: 1px solid #ddd; padding: 10px; background: #fff3cd;'>";
echo htmlspecialchars($stderr_output);
echo "</div>";

echo "<h4>STDOUT (추출된 텍스트):</h4>";
echo "<p>길이: " . strlen($extracted_text) . " 문자</p>";
echo "<div style='border: 1px solid #ddd; padding: 10px; background: #d4edda; max-height: 300px; overflow-y: auto;'>";
echo htmlspecialchars(substr($extracted_text, 0, 1000)) . (strlen($extracted_text) > 1000 ? "...\n\n[전체 텍스트는 " . strlen($extracted_text) . "자]" : "");
echo "</div>";

// OpenAI에 보낼 텍스트 미리보기
echo "<h3>OpenAI에 전달될 텍스트 (처음 500자):</h3>";
$cleaned_text = trim($extracted_text);
echo "<div style='border: 2px solid #007bff; padding: 10px; background: #e7f3ff;'>";
echo htmlspecialchars(substr($cleaned_text, 0, 500));
echo "</div>";

if (strlen($cleaned_text) < 100) {
    echo "<div style='color: red; font-weight: bold; margin-top: 10px;'>⚠️ 경고: 추출된 텍스트가 너무 짧습니다!</div>";
}

// 실제 PDF 내용인지 확인
if (strpos($cleaned_text, 'PyMuPDF') !== false || strpos($cleaned_text, 'pdfplumber') !== false) {
    echo "<div style='color: red; font-weight: bold; margin-top: 10px;'>❌ 문제: 추출된 텍스트에 라이브러리 관련 내용이 포함되어 있습니다!</div>";
} else {
    echo "<div style='color: green; font-weight: bold; margin-top: 10px;'>✅ 좋음: 추출된 텍스트가 정상적으로 보입니다.</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3, h4 { color: #333; }
code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
</style>
