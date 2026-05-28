<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
// بيانات الإيميل المرسل OTP
$sender_email = $_ENV['MAIL_EMAIL'];  
$sender_pass = $_ENV['MAIL_PASS'];   

function sendOTPo($to_email, $otp, $sender_email, $sender_pass){
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
$mail->Username = $sender_email; // اسم المستخدم اللي هتسجل به على SMTP
$mail->Password = $sender_pass;  // كلمة مرور التطبيق أو الإيميل
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($sender_email, 'DarragDNA');
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = 'كود تأكيد حذف الحساب';
        $mail->Body    = "كود حذف الحساب الخاص بك هو: <b>$otp</b>. صالح لمدة 5 دقائق.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function sendOTP($to_email, $otp, $sender_email, $sender_pass) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $sender_email;
        $mail->Password   = $sender_pass;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($sender_email, 'DarragDNA');
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = 'كود التحقق OTP';
        $mail->Body    = "كود التحقق الخاص بك هو: <b>$otp</b>. صالح لمدة 5 دقائق.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
function sendOTPe($to_email, $otp, $sender_email, $sender_pass){
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
$mail->Username = $sender_email; // اسم المستخدم اللي هتسجل به على SMTP
$mail->Password = $sender_pass;  // كلمة مرور التطبيق أو الإيميل
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($sender_email, 'DarragDNA');
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = 'كود تأكيد تعديل الحساب';
        $mail->Body    = "كود تعديل الحساب الخاص بك هو: <b>$otp</b>. صالح لمدة 5 دقائق.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

?>
