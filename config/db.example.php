<?php
// ===== DB 연결 설정 =====
// Naver Cloud Cloud DB for MySQL 정보를 아래 값에 맞게 수정하세요.

$DB_HOST = "YOUR_DB_PRIVATE_DOMAIN";
$DB_USER = "jjh0813";
$DB_PASS = "YOUR_DB_PASSWORD";  // 절대 GitHub에 올리지 말 것!
$DB_NAME = "webtoon_db";

// DEMO_MODE: DB 연결이 안 되거나(또는 PHP에 mysqli 확장이 꺼져 있으면)
// 자동으로 더미 데이터로 화면만 보여줍니다.
// (디자인/UI 테스트용. 실제 회원가입/리뷰작성 등은 DB가 있어야 동작합니다.)
$DEMO_MODE = false;
$conn = null;

if (!extension_loaded('mysqli') || !function_exists('mysqli_report')) {
    // mysqli 확장 자체가 PHP에 설치/활성화되어 있지 않은 경우
    $DEMO_MODE = true;
    require_once __DIR__ . '/demo_data.php';
} else {
    mysqli_report(MYSQLI_REPORT_OFF); // 연결 실패 시 경고창 대신 우리가 직접 처리
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if (!$conn || $conn->connect_error) {
        $DEMO_MODE = true;
        require_once __DIR__ . '/demo_data.php';
    } else {
        $conn->set_charset("utf8mb4");
    }
}
?>
