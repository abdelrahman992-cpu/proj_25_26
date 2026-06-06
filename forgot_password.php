<?php
include("conn.php");


$error = "";
$success = "";

// 1. معالجة طلب إرسال الكود
if(isset($_POST['submit'])) {
    $input = trim($_POST['email']); 

    $stmt = mysqli_prepare($connect, "SELECT id, username, email FROM users WHERE email=? OR username=?");
    mysqli_stmt_bind_param($stmt, "ss", $input, $input);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if(mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $user_id = $row['id'];
        $user_email = $row['email'];
        $user_name = $row['username'];

        $reset_code = random_int(100000, 999999);
        $expire_time = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $stmt2 = mysqli_prepare($connect, "UPDATE users SET reset_code=?, reset_expire=? WHERE id=?");
        mysqli_stmt_bind_param($stmt2, "ssi", $reset_code, $expire_time, $user_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
// تمويه البيانات (تعديل: اسم المستخدم يظهر كاملاً)
$maskedName = $user_name; // سيظهر اسم المستخدم كما هو بدون نجوم
$parts = explode("@", $user_email);
$domainParts = explode(".", $parts[1]); // تقسيم الدومين لمتغير لتجنب خطأ end
$domain = end($domainParts); 
$maskedEmail = substr($parts[0], 0, 2) . "****" . substr($parts[0], -1) . "@*****." . $domain;



        if(sendOTP($user_email, $reset_code, $sender_email, $sender_pass)) {
            $_SESSION['reset_email'] = $user_email;
            $success = "📧 تم إرسال كود التحقق إلى: المستخدم ($maskedName) | الإيميل ($maskedEmail)";
        } else {
            $error = "❌ فشل في إرسال البريد الإلكتروني.";
        }
    } else {
        $error = "❌ اسم المستخدم أو البريد الإلكتروني غير موجود.";
    }
}

// 2. معالجة التحقق من الكود (OTP)
if(isset($_POST['submito'])){
    if(isset($_POST['ottp']) && isset($_POST['email'])){
        $email = trim($_POST['email']);
        $otp_input = trim($_POST['ottp']);

        if(!preg_match('/^[0-9]{6}$/', $otp_input)){
            $error = "❌ OTP غير صالح";
        } else {
            if (getFailedAttemptsCount($connect) >= 5) {
                die("❌ لقد تجاوزت عدد المحاولات المسموح بها. يرجى الانتظار 15 دقيقة.");
            }

            $stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE email=? AND reset_code=? AND reset_expire > NOW()");
            mysqli_stmt_bind_param($stmt, "ss", $email, $otp_input);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if(mysqli_num_rows($res) == 1){
                $_SESSION['reset_user'] = $email;
                unset($_SESSION['reset_email']);
                header("Location: reset_password.php");
                exit;
            } else {
                logFailedAttempt($connect);
                $error = "❌ الكود غير صحيح أو منتهي الصلاحية.";
            }
        }
    }
}

// استدعاء الهيدر هنا (بعد معالجة كل الـ POST)
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
