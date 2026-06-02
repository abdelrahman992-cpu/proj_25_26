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

    // 2. التحقق من وجود المستخدم مسبقاً
    if(empty($errors)){
        $stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE phone=? OR email=?");
        mysqli_stmt_bind_param($stmt, "ss", $phone, $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($res) > 0){
            $errors[] = "عذراً، البريد الإلكتروني أو رقم الهاتف مستخدم بالفعل.";
        }
    }

    // 3. التنفيذ (INSERT)
    if(empty($errors)){
        $hashed_pass = password_hash($txt_pass, PASSWORD_DEFAULT);
        $otp = random_int(100000, 999999); 
        $expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));

        $sql = "INSERT INTO users(username, passwor, email, phone, otp_code, otp_expire) VALUES(?,?,?,?,?,?)";
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, "ssssss", $txt_user, $hashed_pass, $email, $phone, $otp, $expire);

        if(mysqli_stmt_execute($stmt)){
            $user_id = mysqli_insert_id($connect);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $txt_user;
            
            // إرسال الـ OTP
            sendOTP($email, $otp, $sender_email, $sender_pass);
            
            $success = "📧 تم إنشاء الحساب بنجاح! سيتم تحويلك لصفحة التحقق...";
 // داخل reg.php بعد تنفيذ الـ INSERT بنجاح:
$_SESSION['otp_user'] = $txt_user; // تأكد من هذا السطر
echo "<script>setTimeout(function(){ window.location.href='verify.php'; }, 3000);</script>";
        } else {
            $errors[] = "حدث خطأ أثناء التسجيل، حاول مجدداً.";
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
