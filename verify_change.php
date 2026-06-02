<?php
session_start();
include("conn.php");
include("validation.php");
include("header.php");

if(isset($_POST['verify_btn'])){
    $input_otp = sanStr($_POST['otp']);
    $user_id = $_SESSION['user_id'];

    // جلب البيانات المؤقتة
    $stmt = mysqli_prepare($connect, "SELECT pending_email, reset_code, reset_expire FROM users WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // التحقق من الكود والوقت
    if($input_otp == $data['reset_code'] && strtotime($data['reset_expire']) > time()){
        // التحديث الفعلي
        $stmt2 = mysqli_prepare($connect, "UPDATE users SET email=?, pending_email=NULL, reset_code=NULL WHERE id=?");
        mysqli_stmt_bind_param($stmt2, "si", $data['pending_email'], $user_id);
        mysqli_stmt_execute($stmt2);
        
        echo "✅ تم تحديث الإيميل بنجاح!";
    } else {
        echo "❌ الكود خطأ أو منتهي الصلاحية.";
    }
}
?>
<form method="post">
    <input type="text" name="otp" placeholder="أدخل كود الـ OTP" required>
    <button type="submit" name="verify_btn">تأكيد التغيير</button>
</form>
<?php include("footer.php");
?>
