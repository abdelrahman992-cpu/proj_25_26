<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$connect = mysqli_connect(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    $_ENV['DB_NAME']
);
if(!$connect){
    die("❌ لم يتم الاتصال بقاعدة البيانات");
}
function logFailedAttempt($connect) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = mysqli_prepare($connect, "INSERT INTO failed_attempts (ip_address) VALUES (?)");
    mysqli_stmt_bind_param($stmt, "s", $ip);
    mysqli_stmt_execute($stmt);
}

function getFailedAttemptsCount($connect) {
    $ip = $_SERVER['REMOTE_ADDR'];
    // جلب المحاولات في آخر 15 دقيقة فقط
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as count FROM failed_attempts WHERE ip_address=? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)");
    mysqli_stmt_bind_param($stmt, "s", $ip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}
