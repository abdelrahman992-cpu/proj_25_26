<?php
ob_start();
session_start();
include("conn.php");
include("header.php");

if(!isset($_SESSION['delete_user'])){
    header("Location: delete_user.php");
    exit;
}

$error = "";
$success = "";

// 1. معالجة طلب تأكيد الحذف
if(isset($_POST['SubmitConfirm'])){
    $code = trim($_POST['otp']);
    $email = $_SESSION['email'];
    
    $result = callAPI("POST", "/user/delete/?email=" . urlencode($email) . "&code=" . urlencode($code));
    
    if(isset($result['status']) && $result['status'] == 'deleted') {
        session_destroy();
        header("Location: index.php");
        exit;
    } else {
        $error = "❌ " . ($result['detail'] ?? "الكود غير صحيح أو منتهي الصلاحية.");
    }
}

// 2. إعادة إرسال الكود
if(isset($_POST['resend_otp'])){
    $result = callAPI("POST", "/otp/send-delete/?email=" . urlencode($_SESSION['email']));
    if(isset($result['message'])) {
        $success = "✅ تم إرسال كود جديد، يرجى فحص بريدك الإلكتروني.";
    } else {
        $error = "❌ فشل إعادة إرسال الكود.";
    }
}
?>

<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تأكيد حذف الحساب</title>
</head>
<body>
<div>
    <img height="161" src="images/banner.jpg" width="1550">
</div>
<h2>تأكيد حذف الحساب</h2>

<?php 
if(!empty($error)) echo "<p style='color:red;'>$error</p>"; 
if(!empty($success)) echo "<p style='color:green;'>$success</p>"; 
?>

<form method="post">
    <label>ادخل كود التأكيد المرسل على الإيميل:</label><br>
    <input type="text" name="otp" required><br><br>
    <input type="submit" name="SubmitConfirm" value="تأكيد الحذف">
</form>

<hr>

<p>لم يصلك الكود؟</p>
<form method="post">
    <button type="submit" name="resend_otp">إعادة إرسال الكود</button>
</form>

<?php include("footer.php"); ?>
</body>
</html>
