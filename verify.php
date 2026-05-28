<?php
include("conn.php");
$error = "";

if(empty($_SESSION['otp_user'])){
    $error = "❌ لا يوجد مستخدم للتحقق منه. الرجاء تسجيل حسابك أولاً.";
}
if(isset($_POST['verify']) && empty($error)){
    $code = trim($_POST['code']);

    if(!preg_match('/^[0-9]{6}$/', $code)){
        $error = "❌ كود غير صالح";
    } else {

        $user = $_SESSION['otp_user'];


        $stmt = mysqli_prepare($connect, 
        "SELECT id FROM users WHERE username=? AND otp_code=? AND otp_expire > NOW()");

        mysqli_stmt_bind_param($stmt, "ss", $user, $code);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if(mysqli_num_rows($res) == 1){
$stmt2 = mysqli_prepare($connect,
"UPDATE users 
 SET otp_code=NULL,
     otp_expire=NULL
 WHERE username=?");

mysqli_stmt_bind_param($stmt2,"s",$user);
mysqli_stmt_execute($stmt2);
unset($_SESSION['otp_user']);
header("Location: index.php");
exit;    } else {
            $error = "❌ الكود غير صحيح أو انتهت صلاحيته";
        }
    }
}
$remaining_time = 0;

if(!empty($_SESSION['otp_user'])){
    $username = $_SESSION['otp_user'];

    $stmt = mysqli_prepare($connect, 
    "SELECT otp_expire FROM users WHERE username=?");

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if($row){
        $remaining_time = strtotime($row['otp_expire']) - time();
        if($remaining_time < 0){
            $remaining_time = 0;
        }
    }

    mysqli_stmt_close($stmt);
}

if(isset($_POST['resend_otp'])){

    if(!empty($_SESSION['otp_user'])){

        $username = $_SESSION['otp_user'];

        // 👇 نحط الكود هنا
        $stmt = mysqli_prepare($connect, 
        "SELECT email, otp_expire FROM users WHERE username=?");

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // ⛔ تحقق هل الكود لسه شغال
        if(strtotime($row['otp_expire']) > time()){
            $error = "⏳ لازم تستنى لما الكود ينتهي الأول";
        } else {

            // ✅ نولد OTP جديد
            $new_otp = rand(100000,999999);
            $new_expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));

            // تحديث
            $stmt2 = mysqli_prepare($connect, 
            "UPDATE users SET otp_code=?, otp_expire=? WHERE username=?");

            mysqli_stmt_bind_param($stmt2, "sss", 
                $new_otp, 
                $new_expire, 
                $username
            );

            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            // إرسال
            sendOTP($row['email'], $new_otp, $sender_email, $sender_pass);

            $success = "📧 تم إرسال كود جديد";
        }
    }
}
?>

<!-- Front-end -->
<html dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>

<body>

<div style="width:1256px;text-align:right;">

<h2>تأكيد الحساب</h2>

<?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

<form method="post">
    <label>ادخل كود التحقق:</label><br>
    <input type="text" name="code" required style="width:300px;"><br><br>
    <button name="verify">تأكيد</button>
</form>
<p id="timer"></p>

<form method="post" id="resendForm" style="display:none;">
    <input type="submit" name="resend_otp" value="إعادة إرسال الكود">
</form>
<script>
let timeLeft = <?php echo $remaining_time; ?>;
let timer = document.getElementById("timer");
let resendForm = document.getElementById("resendForm");

function updateTimer() {
    if(timeLeft > 0){
        let minutes = Math.floor(timeLeft / 60);
        let seconds = timeLeft % 60;

        timer.innerHTML = "⏳ متبقي: " + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
        timeLeft--;
        setTimeout(updateTimer, 1000);
    } else {
        timer.innerHTML = "✅ يمكنك الآن إعادة إرسال الكود";
        resendForm.style.display = "block";
    }
}

updateTimer();
</script>
</div>

</body>
</html>
