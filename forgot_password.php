<?php
include("conn.php");

$error = "";
$success = "";

// 1. معالجة طلب إرسال الكود
if(isset($_POST['submit'])) {
    $input = trim($_POST['email']); 
    
    // استخراج بيانات المستخدم أولاً
    $stmt = mysqli_prepare($connect, "SELECT id, username, email FROM users WHERE email=? OR username=?");
    mysqli_stmt_bind_param($stmt, "ss", $input, $input);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if(mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $user_email = $row['email'];
        $user_name = $row['username'];

        // الاتصال بالـ API
        $result_api = callAPI("POST", "/otp/send/?email=" . urlencode($user_email));
        
        // التحقق من الرد
        if(isset($result_api['message'])) {
            $_SESSION['reset_email'] = $user_email;
            
            // تشفير البيانات للعرض
            $parts = explode("@", $user_email);
            $domainParts = explode(".", $parts[1]);
            $domain = end($domainParts); 
            $maskedEmail = substr($parts[0], 0, 2) . "****" . substr($parts[0], -1) . "@*****." . $domain;
            
            $success = "📧 تم إرسال كود التحقق إلى: المستخدم ($user_name) | الإيميل ($maskedEmail)";
        } else {
            // هنا ستظهر تفاصيل الخطأ إذا كان الـ API يعيد رسالة خطأ
            $error = "❌ فشل الإرسال: " . ($result_api['detail'] ?? "خطأ غير معروف");
        }
    } else {
        $error = "❌ المستخدم غير موجود.";
    }
}

if(isset($_POST['submito'])){
    if(isset($_POST['ottp']) && isset($_POST['email'])){
        $email = trim($_POST['email']);
        $otp_input = trim($_POST['ottp']);

        if(!preg_match('/^[0-9]{6}$/', $otp_input)){
            $error = "❌ OTP غير صالح";
        } else {
            // التحقق من الحظر
            if (function_exists('getFailedAttemptsCount') && getFailedAttemptsCount($connect) >= 5) {
                die("❌ لقد تجاوزت عدد المحاولات المسموح بها. يرجى الانتظار 15 دقيقة.");
            }

            $result = callAPI("POST", "/otp/verify/?email=" . urlencode($email) . "&code=" . urlencode($otp_input));

            if(isset($result['status']) && $result['status'] == 'success') {
                $_SESSION['reset_user'] = $email;
                unset($_SESSION['reset_email']);
                header("Location: reset_password.php");
                exit;
            } else {
                if (function_exists('logFailedAttempt')) {
                    logFailedAttempt($connect);
                }
                $error = $result['detail'] ?? "❌ الكود غير صحيح أو منتهي الصلاحية.";
            }
        }
    }
}

// استدعاء الهيدر
include("header.php");
?>

<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>استعادة كلمة المرور</title>
</head>
<body>

<div>
    مرحبا <?php echo htmlspecialchars($_SESSION['username'] ?? "زائر"); ?>
</div>

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
