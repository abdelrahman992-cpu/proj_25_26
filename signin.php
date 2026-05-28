<?php
session_start();
include("conn.php");
include("header.php");
include("validation.php");

// 1. إذا كان مسجلاً بالفعل، حوله للرئيسية
if(!empty($_SESSION['user_id'])){
    header("Location: index.php");
    exit;
}

$error = "";

// 2. التحقق عند ضغط زر الدخول
if(isset($_POST['submit1'])){
    $usern = sanStr(trim($_POST['user']));
    $passw = $_POST['pass'];

    // أ. حماية الـ IP من كثرة المحاولات (البريوت فورس العام)
    if (getFailedAttemptsCount($connect) >= 5) {
        $error = "❌ لقد تجاوزت عدد المحاولات المسموح بها. يرجى الانتظار 15 دقيقة.";
    } 
    elseif($usern !== "" && $passw !== ""){
        
        // ب. جلب بيانات المستخدم
        $stmt = mysqli_prepare($connect, "SELECT id, username, passwor, login_attempts, last_attempt_time FROM users WHERE username=?");
        mysqli_stmt_bind_param($stmt, "s", $usern);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if($user){
            // ج. التحقق من الحظر الخاص بالحساب
            $max_attempts = 5;
            if ($user['login_attempts'] >= $max_attempts && (time() - strtotime($user['last_attempt_time'])) < 900) {
                $error = "❌ الحساب محظور مؤقتاً. حاول لاحقاً.";
            } 
            // د. التحقق من كلمة المرور
            elseif(password_verify($passw, $user['passwor'])){
                // نجاح الدخول: تصفير المحاولات
                mysqli_query($connect, "UPDATE users SET login_attempts=0 WHERE id=" . $user['id']);
                
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];

                session_regenerate_id(true); // حماية ضد Session Fixation
                header("Location: index.php");
                exit;
            } else {
                // فشل الدخول: زيادة المحاولات
                logFailedAttempt($connect); // للـ IP
                mysqli_query($connect, "UPDATE users SET login_attempts=login_attempts+1, last_attempt_time=NOW() WHERE id=" . $user['id']);
                $error = "❌ اسم المستخدم أو كلمة المرور غير صحيحة.";
                sleep(2); // تأخير متعمد لإحباط بوتات التخمين
            }
        } else {
            logFailedAttempt($connect);
            $error = "❌ اسم المستخدم أو كلمة المرور غير صحيحة.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "❌ من فضلك املأ كل البيانات";
    }
}
?>

<h2>تسجيل دخول مستخدم</h2>
<?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="post" action="">
    <label>اسم المستخدم:</label><br>
    <input type="text" name="user" required style="width:300px;"><br><br>
    <label>كلمة المرور:</label><br>
    <input type="password" name="pass" required style="width:300px;"><br><br>
    <input type="submit" name="submit1" value="دخول">
    &nbsp;&nbsp; <a href="reg.php">مستخدم جديد</a>
    &nbsp;&nbsp; <a href="forgot_password.php">نسيت كلمة المرور؟</a>
</form>

<?php include("footer.php"); ?>
