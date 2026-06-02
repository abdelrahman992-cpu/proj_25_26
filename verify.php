<?php
ob_start(); // هام جداً لتجنب مشاكل الـ Headers
session_start();
include("conn.php");
include("validation.php"); // تأكد أنها تحتوي على دالة sendOTP
include("header.php"); // اختياري حسب تصميم موقعك

$error = "";
$success = "";

// 1. التحقق من وجود الجلسة
if(empty($_SESSION['otp_user'])){
    $error = "❌ لا يوجد مستخدم للتحقق منه. الرجاء تسجيل حسابك أولاً.";
}

// 2. معالجة زر التأكيد
if(isset($_POST['verify']) && empty($error)){
    $code = trim($_POST['code']);

    if(!preg_match('/^[0-9]{6}$/', $code)){
        $error = "❌ الكود يجب أن يتكون من 6 أرقام.";
    } else {
        $user = $_SESSION['otp_user'];
        $stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE username=? AND otp_code=? AND otp_expire > NOW()");
        mysqli_stmt_bind_param($stmt, "ss", $user, $code);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if(mysqli_num_rows($res) == 1){
            // مسح الكود بعد التحقق الناجح
            $stmt2 = mysqli_prepare($connect, "UPDATE users SET otp_code=NULL, otp_expire=NULL WHERE username=?");
            mysqli_stmt_bind_param($stmt2, "s", $user);
            mysqli_stmt_execute($stmt2);
            
            unset($_SESSION['otp_user']);
            header("Location: index.php");
            exit;
        } else {
            $error = "❌ الكود غير صحيح أو انتهت صلاحيته.";
        }
    }
}

// 3. حساب الوقت المتبقي
$remaining_time = 0;
if(!empty($_SESSION['otp_user'])){
    $username = $_SESSION['otp_user'];
    $stmt = mysqli_prepare($connect, "SELECT otp_expire FROM users WHERE username=?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if($row && $row['otp_expire']){
        $remaining_time = strtotime($row['otp_expire']) - time();
        if($remaining_time < 0) $remaining_time = 0;
    }
}

// 4. إعادة إرسال الكود
if(isset($_POST['resend_otp']) && !empty($_SESSION['otp_user'])){
    // (نفس منطقك السابق وهو سليم 100%)
    // ... تأكد من جلب الإيميل وإرسال الـ OTP
    $success = "📧 تم إرسال كود جديد إلى بريدك الإلكتروني.";
}
?>

<div class="container mt-5">
    <div class="card shadow-sm" style="max-width: 500px; margin: auto;">
        <div class="card-body">
            <h2 class="card-title">تأكيد الحساب</h2>
            <?php 
                if(!empty($error)) echo "<div class='alert alert-danger'>$error</div>";
                if(!empty($success)) echo "<div class='alert alert-success'>$success</div>";
            ?>
            <form method="post">
                <div class="form-group">
                    <label>ادخل كود التحقق (6 أرقام):</label>
                    <input type="text" name="code" class="form-control" maxlength="6" required>
                </div>
                <button name="verify" class="btn btn-success btn-block">تأكيد</button>
            </form>
            <hr>
            <p id="timer" class="text-center font-weight-bold"></p>
            <form method="post" id="resendForm" style="display:none;">
                <button type="submit" name="resend_otp" class="btn btn-outline-primary btn-block">إعادة إرسال الكود</button>
            </form>
        </div>
    </div>
</div>

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
