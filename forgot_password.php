<?php
include("conn.php");


$error = "";
$success = "";
// إذا كان الكود غير صحيح:
if ($otp_input != $correct_otp) {
    logFailedAttempt($connect); // تسجيل المحاولة
    $error = "❌ الكود غير صحيح.";
}

// قبل معالجة أي طلب، تحقق من الحظر:
if (getFailedAttemptsCount($connect) >= 5) {
    die("❌ لقد تجاوزت عدد المحاولات المسموح بها. يرجى الانتظار 15 دقيقة.");
}
if(isset($_POST['submit'])) {
    $email = trim($_POST['email']);

    $stmt = mysqli_prepare($connect, "SELECT id, username FROM users WHERE email=?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if(mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $user_id = $row['id'];

        // 🔐 OTP آمن
        $reset_code = random_int(100000, 999999);
        $expire_time = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $stmt2 = mysqli_prepare($connect, "UPDATE users SET reset_code=?, reset_expire=? WHERE id=?");
        mysqli_stmt_bind_param($stmt2, "ssi", $reset_code, $expire_time, $user_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        function maskEmail($email) {
            $parts = explode("@", $email);
            $name = $parts[0];
            $domain = $parts[1];

            $first = substr($name, 0, 2);
            $last  = substr($name, -1);

            $maskedName = $first . "****" . $last;

            $domainParts = explode(".", $domain);
            $maskedDomain = "*****." . end($domainParts);

            return $maskedName . "@" . $maskedDomain;
        }

        $maskedEmail = maskEmail($email);

        if(sendOTP($email, $reset_code, $sender_email, $sender_pass)) {

            // ✅ تخزين الإيميل في السيشن
            $_SESSION['reset_email'] = $email;

            $success = "📧 تم إرسال كود التحقق إلى: $maskedEmail";
        } else {
            $error = "❌ فشل في إرسال البريد الإلكتروني.";
        }

    } else {
        $error = "❌ البريد الإلكتروني غير موجود في النظام.";
    }
}

if(isset($_POST['submito'])){

    if(isset($_POST['ottp']) && isset($_POST['email'])){

        $email = trim($_POST['email']);
        $otp_input = trim($_POST['ottp']);

        if(!preg_match('/^[0-9]{6}$/', $otp_input)){
            $error = "❌ OTP غير صالح";
        } else {

            $stmt = mysqli_prepare($connect, 
            "SELECT id FROM users WHERE email=? AND reset_code=? AND reset_expire > NOW()");

            mysqli_stmt_bind_param($stmt, "si", $email, $otp_input);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if(mysqli_num_rows($res) == 1){

                $_SESSION['reset_user'] = $email;

                // 🔥 حذف السيشن القديمة
                unset($_SESSION['reset_email']);

                header("Location: reset_password.php");
                exit;

            } else {
                $error = "❌ الكود غير صحيح أو منتهي";
            }
        }
    } else {
        $error = "❌ لازم تطلب كود الأول";
    }
}

echo"<div>
    <img height='161' src='images/banner.jpg' width='1267'>
</div>";

echo "<ul>";
if(empty($_SESSION['username'])){
    echo "
    <li class='left'><a href='signin.php'>تسجيل الدخول (مستخدم غير مسجل)</a></li>
    <li class='left'><a href='index.php'>الرئيسية</a></li>
    <li class='left'><a href='add_term.php'>إضافة مصطلح</a></li>
    <li class='left'><a href='search_term.php'>البحث عن مصطلح</a></li>
    <li class='left'><a href='Del_Term.php'>حذف مصطلح</a></li>
    <li class='left'><a href='Edit_Term.php'>تعديل مصطلح</a></li>
    <li class='left'><a href='Help.php'>مساعدة</a></li>
    <li class='right'><a href='https://www.facebook.com/profile.php?id=100011930646556'><i class='fab fa-facebook'></i></a></li>
    <li class='right'><a href='https://www.twitter.com'><i class='fab fa-twitter-square'></i></a></li>";
} else {
    echo "
    <li class='left'><a href='index.php'>الرئيسية</a></li>
    <li class='left'><a href='add_term.php'>إضافة مصطلح</a></li>
    <li class='left'><a href='search_term.php'>البحث عن مصطلح</a></li>
    <li class='left'><a href='Del_Term.php'>حذف مصطلح</a></li>
    <li class='left'><a href='Edit_Term.php'>تعديل مصطلح</a></li>
    <li class='left'><a href='Help.php'>مساعدة</a></li>
    <li class='right'><a href='https://www.facebook.com/profile.php?id=100011930646556'><i class='fab fa-facebook'></i></a></li>
    <li class='right'><a href='https://www.twitter.com'><i class='fab fa-twitter-square'></i></a></li>
    <li class='left'><a href='signout.php'>تسجيل الخروج</a></li>";
}
echo "</ul>";
?>

<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>استعادة كلمة المرور</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.2/css/all.css" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
<style>
ul{list-style-type:none;margin:0;padding:0;overflow:hidden;background-color:#2c3e50;position:sticky;top:0;z-index:999;}
.left{float:left;} .right{float:right;}
li a{display:inline-block;color:white;text-align:center;padding:14px 16px;text-decoration:none;}
li a:hover,.active{background-color:red;opacity:0.4;}
footer{background-color:#555;color:white;padding:15px;}
</style>
</head>

<body>

<h2>استعادة كلمة المرور</h2>

<?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if(!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>

<!-- فورم الإيميل -->
<form method="post">
    <label>أدخل بريدك الإلكتروني:</label><br>
    <input type="email" name="email" required><br><br>
    <input type="submit" name="submit" value="إرسال كود التحقق">
</form>

<!-- فورم OTP -->
<form method="post">
    <label>أدخل كودك الالكتروني:</label><br>
    <input type="text" name="ottp" required><br><br>

    <!-- 🔥 بدون تغيير الشكل -->
    <input type="hidden" name="email" value="<?php echo isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : ''; ?>">

    <input type="submit" name="submito" value="توجه لصفحة تغيير كلمة المرور">
</form>

</body>
</html>
