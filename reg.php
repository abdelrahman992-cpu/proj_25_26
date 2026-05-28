<?php
include("conn.php");
include("validation.php");
include("header.php");
$error = "";
$success = "";

if(isset($_POST['Submit1'])){

    $txt_user = sanStr(trim($_POST['txt_user']));
    $txt_pass = $_POST['txt_pass'];
    $txt_con  = $_POST['txt_con'];
    $email    = sanStr(trim($_POST['email']));
    $phone    = sanStr(trim($_POST['phone']));

    // ✅ تحقق من قوة الباسورد
    if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $txt_pass)){
        $error = "❌ كلمة المرور لازم تكون 6 أحرف على الأقل وتحتوي على حرف كبير وصغير ورقم";

    } elseif(!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)){
        $error = "❌ رقم الهاتف غير صحيح";

    } else {

        // ✅ تحقق من الهاتف
        $stmt = mysqli_prepare($connect,"SELECT id FROM users WHERE phone=?");
        mysqli_stmt_bind_param($stmt,"s",$phone);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if(mysqli_num_rows($res) > 0){
            $error = "❌ الرقم مستخدم بالفعل";
        }

        // ✅ تحقق من الإيميل
        $stmt2 = mysqli_prepare($connect,"SELECT id FROM users WHERE email=?");
        mysqli_stmt_bind_param($stmt2,"s",$email);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);

        if(mysqli_num_rows($res2) > 0){
            $error = "❌ الإيميل مستخدم بالفعل";
        }

        elseif($txt_pass !== $txt_con){
            $error = "❌ كلمة المرور وتأكيدها غير متطابقين";
        }

        else {

            $hashed_pass = password_hash($txt_pass, PASSWORD_DEFAULT);

            // 🔐 OTP آمن
            $otp = random_int(100000,999999); 
            $expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));

            $stmt = mysqli_prepare($connect, 
            "INSERT INTO users(username, passwor, email, phone, otp_code, otp_expire) VALUES(?,?,?,?,?,?)");

            mysqli_stmt_bind_param($stmt, "ssssss", 
                $txt_user, 
                $hashed_pass, 
                $email, 
                $phone, 
                $otp, 
                $expire
            );

            if(mysqli_stmt_execute($stmt)){

                $_SESSION['user_id'] = mysqli_insert_id($connect);
                $_SESSION['username'] = $txt_user;
                $_SESSION['otp_user'] = $txt_user;

                $session_id = session_id();

                $stmt2 = mysqli_prepare($connect, 
                "INSERT INTO sections (user_id, username, deta, last_activity) VALUES (?, ?, ?, NOW())");

                mysqli_stmt_bind_param($stmt2, "iss",
                    $_SESSION['user_id'],
                    $_SESSION['username'],
                    $session_id
                );

                mysqli_stmt_execute($stmt2);

                sendOTP($email, $otp, $sender_email, $sender_pass);

                $success = "📧 تم إنشاء الحساب بنجاح وسيتم إرسال كود التحقق";

                echo "<script>setTimeout(function(){ window.location.href='verify.php'; }, 3000);</script>";

            } else {
                $error = "❌ خطأ في التسجيل";
            }
        }
    }
}
?>

<html dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.2/css/all.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body>
<h2>تسجيل مستخدم جديد</h2>
<form method="post">
    <label>اسم المستخدم:</label><br>
    <input type="text" name="txt_user" required style="width:300px;"><br><br>

    <label>كلمة المرور:</label><br>
    <input type="password" name="txt_pass" required style="width:300px;"><br><br>

    <label>تأكيد كلمة المرور:</label><br>
    <input type="password" name="txt_con" required style="width:300px;"><br><br>

    <label>البريد الإلكتروني:</label><br>
    <input type="email" name="email" required style="width:300px;"><br><br>

    <label>رقم الهاتف:</label><br>
    <input type="text" name="phone" placeholder="+201234567890" required style="width:300px;"><br><br>

    <input type="submit" name="Submit1" value="تسجيل">
</form>

<?php
if(!empty($error)) echo "<p style='color:red;'>$error</p>";
if(!empty($success)) echo "<p style='color:green;'>$success</p>";
include("footer.php");
?>

