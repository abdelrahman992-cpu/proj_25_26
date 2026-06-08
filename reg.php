<?php
ob_start();
session_start();
include("conn.php");
include("validation.php");
include("header.php");

$errors = []; 
$success = "";



if(isset($_POST['Submit1'])){
    $txt_user = sanStr(trim($_POST['txt_user']));
    $txt_pass = $_POST['txt_pass'];
    $txt_con  = $_POST['txt_con'];
    $email    = sanStr(trim($_POST['email']));
    $phone    = sanStr(trim($_POST['phone']));

    // 1. التحقق من صحة المدخلات
    if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $txt_pass)){
        $errors[] = "كلمة المرور يجب أن تحتوي على 6 أحرف على الأقل (حرف كبير، حرف صغير، ورقم).";
    }
    if($txt_pass !== $txt_con){
        $errors[] = "كلمة المرور وتأكيدها غير متطابقين.";
    }
    if(!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)){
        $errors[] = "رقم الهاتف غير صحيح (مثال: +201234567890).";
    }


   if(empty($errors)){
        // 1. تخزين بيانات المستخدم مؤقتاً في الجلسة لكي نستخدمها لاحقاً في verify.php
        $_SESSION['temp_user_data'] = [
            'username' => $txt_user,
            'password' => $txt_pass,
            'email'    => $email,
            'phone'    => $phone
        ];
        $_SESSION['otp_email'] = $email;

        // 2. استدعاء دالة إرسال الكود فقط
        $postData = ['email' => $email]; 
        $result = callAPI("POST", "/otp/send-code/", $postData);

        // 3. التحقق من نجاح إرسال الكود
        if(isset($result['message'])) {
            $success = "📧 " . $result['message'] . " سيتم تحويلك لصفحة التحقق...";
            echo "<script>setTimeout(function(){ window.location.href='verify.php'; }, 3000);</script>";
        } else {
            $errors[] = $result['detail'] ?? "حدث خطأ أثناء إرسال كود التحقق.";
        }
    }
}
?>

<div class="container mt-5">
    <h2>تسجيل مستخدم جديد</h2>

    <?php 
    foreach($errors as $err) echo "<div class='alert alert-danger'>$err</div>"; 
    if($success) echo "<div class='alert alert-success'>$success</div>";
    ?>

    <form method="post">
        <div class="form-group">
            <label>اسم المستخدم:</label>
            <input type="text" name="txt_user" class="form-control" required style="width:300px;">
        </div>
        <div class="form-group">
            <label>كلمة المرور:</label>
            <input type="password" name="txt_pass" class="form-control" required style="width:300px;">
        </div>
        <div class="form-group">
            <label>تأكيد كلمة المرور:</label>
            <input type="password" name="txt_con" class="form-control" required style="width:300px;">
        </div>
        <div class="form-group">
            <label>البريد الإلكتروني:</label>
            <input type="email" name="email" class="form-control" required style="width:300px;">
        </div>
        <div class="form-group">
            <label>رقم الهاتف:</label>
            <input type="text" name="phone" class="form-control" placeholder="+201234567890" required style="width:300px;">
        </div>
        <input type="submit" name="Submit1" class="btn btn-primary" value="تسجيل">
    </form>
</div>

<?php include("footer.php"); ?>
