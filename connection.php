<?php
require __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db_pass = getenv('DB_PASS'); 
// إذا كانت القيمة فارغة، اجعلها null صراحةً
$db_pass = ($db_pass === false || $db_pass === '') ? null : $db_pass;
// نستخدم المصفوفة التي تم جلبها من config.php
$connect = mysqli_connect(
    $config['DB_HOST'],
    $config['DB_USER'],
    $config['DB_PASS'],
    $config['DB_NAME']
);

if (!$connect) {
    die("❌ خطأ في الاتصال: " . mysqli_connect_error());
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
