<?php
require __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
$envFile = (PHP_OS_FAMILY === 'Windows') ? '2.env' : '.env';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, $envFile);
$dotenv->load();
// 3. جلب القيم من $_ENV مباشرة (تجنب الاعتماد على $config إذا كان غير مستقر)
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$db   = $_ENV['DB_NAME'] ?? 'dbdictionary';
// 4. جلب قيم الإيميل (الإضافة الجديدة)
$sender_email = $_ENV['MAIL_EMAIL'] ?? '';
$sender_pass  = $_ENV['MAIL_PASS'] ?? '';
// 4. الاتصال
$connect = mysqli_connect($host, $user, $pass, $db);

if (!$connect) {
    die("❌ خطأ في الاتصال: " . mysqli_connect_error());
}

// الدوال الخاصة بك (لا تغيير فيها)
function logFailedAttempt($connect) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = mysqli_prepare($connect, "INSERT INTO failed_attempts (ip_address) VALUES (?)");
    mysqli_stmt_bind_param($stmt, "s", $ip);
    mysqli_stmt_execute($stmt);
}

function getFailedAttemptsCount($connect) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as count FROM failed_attempts WHERE ip_address=? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)");
    mysqli_stmt_bind_param($stmt, "s", $ip);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}
?>
