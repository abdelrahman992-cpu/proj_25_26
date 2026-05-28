<?php
session_start();
include("conn.php");
include("validation.php");

// 1. حماية الصفحة
if(!isset($_SESSION['user_id'])) { header("Location: signin.php"); exit; }

$user_id = $_SESSION['user_id'];
$message = "";

// 2. جلب البيانات الحالية
$stmt = mysqli_prepare($connect, "SELECT username, email, phone FROM users WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$old_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// 3. معالجة التحديث (يجب أن تكون قبل تضمين أي HTML)
if(isset($_POST['update_btn'])){
    $new_username = sanStr($_POST['username']);
    $new_email = sanStr($_POST['email']);
    $new_phone = sanStr($_POST['phone']);

    // أ. تحديث الاسم فوراً
    if($new_username !== $old_data['username']){
        $stmt = mysqli_prepare($connect, "UPDATE users SET username=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $new_username, $user_id);
        mysqli_stmt_execute($stmt);
        $_SESSION['username'] = $new_username;
        $message = "✅ تم تحديث الاسم.";
    }

    // ب. معالجة الإيميل أو الهاتف
    if($new_email !== $old_data['email'] || $new_phone !== $old_data['phone']){
        $otp = random_int(100000, 999999);
        $expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));
        
        $stmt = mysqli_prepare($connect, "UPDATE users SET pending_email=?, pending_phone=?, reset_code=?, reset_expire=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssssi", $new_email, $new_phone, $otp, $expire, $user_id);
        mysqli_stmt_execute($stmt);
        
        // إرسال الكود (نرسله للميل كافتراضي حالياً)
        sendOTPe($new_email, $otp, $sender_email, $sender_pass);
        
        $_SESSION['verify_type'] = ($new_email !== $old_data['email']) ? 'email' : 'phone';
        
        // الآن بما أننا لم نطبع HTML بعد، الـ header سيعمل بنجاح
        header("Location: verify_change.php");
        exit;
    }
}

// 4. هنا فقط نقوم بتضمين التصميم (بعد انتهاء المعالجة)
include("header.php");
?>

<h2>تعديل بيانات الحساب</h2>
<p style="color:blue;"><?php echo $message; ?></p>
<form method="post" action="">
    <label>اسم المستخدم:</label><br>
    <input type="text" name="username" value="<?php echo htmlspecialchars($old_data['username']); ?>" required><br><br>
    <label>البريد الإلكتروني:</label><br>
    <input type="email" name="email" value="<?php echo htmlspecialchars($old_data['email']); ?>" required><br><br>
    <label>رقم الهاتف:</label><br>
    <input type="text" name="phone" value="<?php echo htmlspecialchars($old_data['phone']); ?>"><br><br>
    <input type="submit" name="update_btn" value="حفظ التعديلات">
</form>

<?php include('footer.php'); ?>
