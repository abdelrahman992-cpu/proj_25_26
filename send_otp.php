<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
function sendOTP($to_email, $otp, $sender_email, $sender_pass) {
    $mail = new PHPMailer(true);

    try {
        // إعدادات SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $sender_email; // البريد كمتغير
        $mail->Password   = $sender_pass;  // كلمة المرور كمتغير
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // المرسل والمستقبل
        $mail->setFrom($sender_email, 'My Website');
        $mail->addAddress($to_email);

        // محتوى الإيميل
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
?>
