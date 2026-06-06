<?php
include("conn.php");




$error = "";
$success = "";

// التأكد من الجلسة
if(!isset($_SESSION['reset_user'])){
    $error = "❌ غير مسموح بالدخول لهذه الصفحة مباشرة";
} else {
    $email = $_SESSION['reset_user'];
    $stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE email=?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if($row = mysqli_fetch_assoc($result)){
        $user_id = $row['id'];
    }
}

// معالجة النموذج
if(isset($_POST['submite']) && isset($user_id)){
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $new_password)){
        $error = "❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل وتحتوي على حرف كبير وصغير ورقم";
    } elseif($new_password != $confirm_password){
        $error = "❌ كلمة المرور وتأكيدها غير متطابقين";
    } else {
        $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
// 1. تحديث كلمة المرور فقط في جدول users
$stmt2 = mysqli_prepare($connect, "UPDATE users SET passwor=? WHERE id=?");
mysqli_stmt_bind_param($stmt2, "si", $hashed_pass, $user_id);
mysqli_stmt_execute($stmt2);

// 2. إذا كنت تريد حذف الكود من جدول otp_codes (الجدول الصحيح للأكواد)
$stmt3 = mysqli_prepare($connect, "DELETE FROM otp_codes WHERE user_id=?");
mysqli_stmt_bind_param($stmt3, "i", $user_id);
mysqli_stmt_execute($stmt3);

// التحقق من نجاح العمليتين
if(mysqli_stmt_affected_rows($stmt2) >= 0) {
    $success = "✅ تم تغيير كلمة المرور بنجاح.";
    unset($_SESSION['reset_user']);
    header("Location: index.php");
    exit;
}
        mysqli_stmt_close($stmt2);
    }
}
include("header.php");
echo ("مرحبا") . " " .htmlspecialchars($_SESSION['username'] ?? "");
?>

<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تغيير كلمة المرور</title>

</head>
<body>

<!-- عرض الـ Navbar هنا بعد تعريف الـ CSS -->
<?php 
// يمكنك هنا وضع كود الـ Navbar الذي كتبته، مع التأكد من تصحيح الـ li داخل الـ dropdown
?>

<div class="container mt-5">
    <h2>تغيير كلمة المرور</h2>
    <?php if(!empty($error)) echo "<p class='text-danger'>$error</p>"; ?>
    <?php if(!empty($success)) echo "<p class='text-success'>$success</p>"; ?>

    <form method="post">
        <div class="form-group">
            <label>كلمة المرور الجديدة:</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label>تأكيد كلمة المرور:</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <input type="submit" name="submite" class="btn btn-primary" value="تغيير كلمة المرور">
    </form>
</div>
<?php include("footer.php"); ?>
</body>
</html>
