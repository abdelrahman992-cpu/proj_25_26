<?php
session_start();
include("conn.php");
include("header.php");
include("validation.php");

$error = "";

if(isset($_POST['submit1'])){
    $usern = sanStr(trim($_POST['user']));
    $passw = $_POST['pass'];

    if($usern !== "" && $passw !== ""){
        // تحضير البيانات لإرسالها للـ API
        $postData = http_build_query([
            'username' => $usern,
            'password' => $passw
        ]);

        // إرسال الطلب للـ API (الذي قمت ببرمجته في main.py)
        $ch = curl_init('http://127.0.0.1:8000/login/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result = json_decode($response, true);
        curl_close($ch);

        // التحقق من نجاح الاتصال بالـ API
        if ($httpCode == 200 && isset($result['access_token'])) {
            // نجاح: حفظ البيانات في الجلسة
$_SESSION['access_token'] = $result['access_token'];
            $_SESSION['username'] = $usern;
            
            // جلب الـ ID من قاعدة البيانات المحلية لربطه بالجلسة
            $stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE username=?");
            mysqli_stmt_bind_param($stmt, "s", $usern);
            mysqli_stmt_execute($stmt);
            $user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $_SESSION['user_id'] = $user_row['id'];

            header("Location: index.php");
            exit;
        } else {
            // خطأ: الرسالة القادمة من الـ API أو خطأ اتصال
            $error = "❌ خطأ في الدخول: " . ($result['detail'] ?? "فشل الاتصال بالخادم");
        }
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
