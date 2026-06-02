<?php
ob_start();
session_start(); // ضرورية جداً للتعرف على الجلسة
include("conn.php");
include("header.php"); // اختياري حسب تصميم موقعك
if(!isset($_SESSION['delete_user'])){
    header("Location: delete_user.php");
    exit;
}

$error = "";

if(isset($_POST['SubmitConfirm'])){
    $otp_input = trim($_POST['otp']);

    if(!preg_match('/^[0-9]{6}$/', $otp_input)){
        $error = "❌ كود غير صالح";
    } else {
        $user_id = $_SESSION['delete_user'];

        $stmt = mysqli_prepare($connect, "SELECT delete_otp, delete_expire FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        // التحقق من الكود وتاريخ الانتهاء
        if($row && hash_equals($row['delete_otp'], $otp_input) && strtotime($row['delete_expire']) > time()){
            
            $stmt2 = mysqli_prepare($connect, "DELETE FROM users WHERE id=?");
            mysqli_stmt_bind_param($stmt2, "i", $user_id);
            mysqli_stmt_execute($stmt2);

            session_destroy();
            echo "<h2>✅ تم حذف الحساب نهائياً</h2>";
            echo "<script>setTimeout(function(){ window.location.href='index.php'; }, 3000);</script>";
            exit;
        } else {
            $error = "❌ كود غير صحيح أو انتهت صلاحيته.";
        }
    }
}
// 4. حساب الوقت المتبقي (باستخدام ID وليس username)
$remaining_time = 0;
if(!empty($_SESSION['delete_user'])){
    $user_id = $_SESSION['delete_user'];
    // جلب وقت انتهاء كود الحذف
    $stmt = mysqli_prepare($connect, "SELECT delete_expire FROM users WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if($row && $row['delete_expire']){
        $remaining_time = strtotime($row['delete_expire']) - time();
        if($remaining_time < 0) $remaining_time = 0;
    }
}

// 5. إعادة إرسال الكود
if(isset($_POST['resend_otp']) && !empty($_SESSION['delete_user'])){
    $user_id = $_SESSION['delete_user'];
    
    // جلب الإيميل
    $stmt = mysqli_prepare($connect, "SELECT email FROM users WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    // توليد كود جديد وإرساله (نفس منطقك في verify.php)
    $new_otp = rand(100000, 999999);
    $new_expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));
    
    $stmt2 = mysqli_prepare($connect, "UPDATE users SET delete_otp=?, delete_expire=? WHERE id=?");
    mysqli_stmt_bind_param($stmt2, "ssi", $new_otp, $new_expire, $user_id);
    mysqli_stmt_execute($stmt2);
    
    sendOTP($row['email'], $new_otp, $sender_email, $sender_pass);
    $success = "📧 تم إرسال كود جديد لحذف الحساب.";
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

<?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

<form method="post">
    <label>ادخل كود التأكيد المرسل على الإيميل:</label><br>
    <input type="text" name="otp" required><br><br>
    <input type="submit" name="SubmitConfirm" value="تأكيد الحذف">
</form>
    <hr>
            <p id="timer" class="text-center font-weight-bold"></p>
            <form method="post" id="resendForm" style="display:none;">
                <button type="submit" name="resend_otp" class="btn btn-outline-primary btn-block">إعادة إرسال الكود</button>
            </form>
            <script>
let timeLeft = <?php echo $remaining_time; ?>;
let timer = document.getElementById("timer");
let resendForm = document.getElementById("resendForm");

function updateTimer() {
    if(timeLeft > 0){
        let m = Math.floor(timeLeft / 60);
        let s = timeLeft % 60;
        timer.innerHTML = "⏳ يمكنك طلب كود جديد بعد: " + m + ":" + (s < 10 ? "0" : "") + s;
        timeLeft--;
        setTimeout(updateTimer, 1000);
    } else {
        timer.innerHTML = "✅ الكود متاح الآن";
        resendForm.style.display = "block";
    }
}
updateTimer();
</script>
<?php include("footer.php"); ?>
</body>
</html>
