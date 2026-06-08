<?php
ob_start();
session_start();
include("conn.php");
include("header.php");

$error = "";
$success = "";
$remaining_time = 0; // تعريف أولي لتجنب الخطأ

// 1. التحقق من وجود الجلسة
$email = isset($_SESSION['otp_email']) ? $_SESSION['otp_email'] : null;

if(empty($_SESSION['otp_user']) || !$email){
    $error = "❌ لا يوجد مستخدم للتحقق منه. الرجاء تسجيل حسابك أولاً.";
}

if(isset($_POST['verify']) && empty($error)){
    $code = trim($_POST['code']);
    
    // تأكد أن الجلسة تحتوي على البيانات
    if(!isset($_SESSION['temp_user_data'])) {
        $error = "❌ انتهت الجلسة، يرجى التسجيل مرة أخرى.";
    } else {
        $finalData = $_SESSION['temp_user_data'];
        
        // --- هذا هو السطر المفقود ---
        $finalData['code'] = $code; 
        // ---------------------------

        $result = callAPI("POST", "/users/finalize-signup/", $finalData);
        
        if(isset($result['status']) && $result['status'] == 'success') {
            unset($_SESSION['temp_user_data']); // تأكد من حذف الجلسة المؤقتة أيضاً
            unset($_SESSION['otp_email']);
            header("Location: index.php");
            exit;
        } else {
            // نستخدم $result['detail'] التي تأتي من FastAPI إذا حدث خطأ
            $error = $result['detail'] ?? "❌ الكود غير صحيح أو انتهت صلاحيته.";
        }
    }
}

// 3. إعادة إرسال الكود
if(isset($_POST['resend_otp']) && !empty($_SESSION['otp_user'])){
    if($email){
        $postData = ['email' => $email];
        $result = callAPI("POST", "/otp/send-code/", $postData);

        if(isset($result['message'])) {
            $success = "📧 " . $result['message'];
            header("Refresh: 2"); 
        } else {
            $error = "❌ فشل إعادة إرسال الكود: " . ($result['detail'] ?? "خطأ غير معروف");
        }
    }
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
// تأكدنا هنا أن المتغير رقم صحيح
let timeLeft = <?php echo (int)$remaining_time; ?>;
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
