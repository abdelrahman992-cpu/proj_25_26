<?php
include("conn.php");

$error = "";
$success = "";

// التأكد من أن المستخدم وصل لهذه الصفحة بعد التحقق من الكود (عبر الجلسة)
if(!isset($_SESSION['reset_user'])){
    $error = "❌ غير مسموح بالدخول لهذه الصفحة مباشرة";
}

// معالجة النموذج
if(isset($_POST['submite']) && isset($_SESSION['reset_user'])){
    $email = $_SESSION['reset_user'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // ملاحظة: الـ API في main.py يحتاج الكود أيضاً ليحذفه، 
    // لذا يفضل تمرير الكود عبر الجلسة (Session) أيضاً من صفحة التحقق
    $code = $_SESSION['reset_code']; 

    if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $new_password)){
        $error = "❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل وتحتوي على حرف كبير وصغير ورقم";
    } elseif($new_password != $confirm_password){
        $error = "❌ كلمة المرور وتأكيدها غير متطابقين";
    } else {
        // إعداد البيانات للـ API
        $postData = [
            'email' => $email,
            'code' => $code,
            'new_password' => $new_password
        ];

        // الاتصال بالـ API وتغيير كلمة المرور
        $result = callAPI("POST", "/password/reset/", $postData);

        // التحقق من رد الـ API
        if(isset($result['message']) && $result['message'] == "تم تغيير كلمة المرور بنجاح") {
            $success = "✅ تم تغيير كلمة المرور بنجاح.";
            unset($_SESSION['reset_user']);
            unset($_SESSION['reset_code']);
            header("Location: index.php");
            exit;
        } else {
            $error = $result['detail'] ?? "❌ حدث خطأ أثناء التغيير.";
        }
    }
}
include("header.php");
?>

<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تغيير كلمة المرور</title>

</head>
<body>



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
