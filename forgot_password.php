<?php
include("conn.php");


$error = "";
$success = "";
// ملاحظة: لا نحتاج لتهيئة $result خارج الـ if لأننا سنتحقق من وجوده قبل استخدامه

// معالجة طلب الكود
if(isset($_POST['submit'])) {
    $input = trim($_POST['email']); 
    
    $stmt = mysqli_prepare($connect, "SELECT id, username, email FROM users WHERE email=? OR username=?");
    mysqli_stmt_bind_param($stmt, "ss", $input, $input);
    mysqli_stmt_execute($stmt);
    $result_db = mysqli_stmt_get_result($stmt);

    if(mysqli_num_rows($result_db) == 1) {
        $row = mysqli_fetch_assoc($result_db);
        $user_email = $row['email'];
        $user_name = $row['username'];

        $result_api = callAPI("POST", "/password/request-reset/?email=" . urlencode($user_email));
        
        if(isset($result_api['message'])) {
            $_SESSION['reset_email'] = $user_email;
            $parts = explode("@", $user_email);
            $domainParts = explode(".", $parts[1]);
            $domain = end($domainParts); 
            $maskedEmail = substr($parts[0], 0, 2) . "****" . substr($parts[0], -1) . "@*****." . $domain;
            $success = "📧 تم إرسال كود التحقق إلى: المستخدم ($user_name) | الإيميل ($maskedEmail)";
        } else {
            $error = "❌ فشل الإرسال: " . ($result_api['detail'] ?? "خطأ في الاتصال بالخادم");
        }
    } else {
        $error = "❌ المستخدم غير موجود.";
    }
}

// معالجة التحقق من الكود
if(isset($_POST['submito'])) {
    // التأكد من وجود الإيميل في الجلسة
    if(!isset($_SESSION['reset_email'])) {
        $error = "❌ انتهت الجلسة، يرجى طلب الكود مرة أخرى.";
    } else {
        $email = $_SESSION['reset_email'];
        $otp_input = trim($_POST['ottp']);
        
        $postData = [
            'email' => $email,
            'code' => $otp_input
        ];

        $result = callAPI("POST", "/password/verify-code/", $postData); 
        
        // التحقق الصحيح من الرد
        if(isset($result['status']) && $result['status'] == 'success') {
            $_SESSION['reset_user'] = $email; 
            unset($_SESSION['reset_email']);
            header("Location: reset_password.php"); 
            exit;
        } else {
            // هنا الخطأ سيظهر فقط إذا كان الزر مضغوطاً والنتيجة ليست success
            $error = "❌ " . ($result['detail'] ?? "الكود غير صحيح أو منتهي الصلاحية.");
        }
    }
}


include("header.php");
?>

<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>استعادة كلمة المرور</title>
</head>
<body>



<h2>استعادة كلمة المرور</h2>

<?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if(!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>

<form method="post">
    <label>أدخل بريدك الإلكتروني أو اسم المستخدم:</label><br>
    <input type="text" name="email" required><br><br>
    <input type="submit" name="submit" value="إرسال كود التحقق">
</form>

<hr>

<form method="post">
    <label>أدخل كودك الالكتروني (OTP):</label><br>
    <input type="text" name="ottp" required><br><br>
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?>">
    <input type="submit" name="submito" value="تحقق من الكود">
</form>

<?php include("footer.php"); ?>
</body>
</html>
