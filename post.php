<?php
$token = $_SESSION['access_token'] ?? null;
$user = callAPI("GET", "/users/mee/", false, $token); // المسار الذي أنشأناه في الخطوة 1

if (isset($user['username'])) {
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['phone']    = $user['phone'];
    $_SESSION['role']     = $user['role'];
} else {
    // إذا لم ينجح الجلب، ربما التوكن انتهى أو المستخدم غير مسجل
    $error = "❌ فشل جلب بيانات المستخدم.";
}

// 2. معالجة الحذف (كما هي مع تعديل بسيط للـ API)
if (isset($_POST['deleteAccount'])) {
    $pass = $_POST['password'];
    $email = $_SESSION['email'];
    $token = $_SESSION['access_token']; // تأكد من جلب التوكن هنا

    $postData = ['email' => $email, 'password' => $pass];
    
    // مرر التوكن للدالة هنا أيضاً
    $result = callAPI("POST", "/otp/send-delete/", $postData, $token); 

    if (isset($result['message'])) {
        $_SESSION['delete_user'] = $_SESSION['user_id'];
        header("Location: confirm_delete.php");
        exit;
    } else {
        // طباعة الخطأ الفعلي القادم من البايثون
        $error = "❌ " . ($result['detail'] ?? "فشل إرسال كود الحذف");
    }
}
?>

<?php if (isset($user)) : ?>
    مرحبا: <?php echo htmlspecialchars($_SESSION['username'] ?? ""); ?><br>
    البريد الالكتروني: <?php echo htmlspecialchars($_SESSION['email'] ?? ""); ?><br>
    رقم الهاتف: <?php echo htmlspecialchars($_SESSION['phone'] ?? ""); ?><br>
    الدور: <?php echo htmlspecialchars($_SESSION['role'] ?? ""); ?><br>
<?php endif; ?>
<?php
    echo '
    <form method="post" onsubmit="return confirm(\'هل أنت متأكد من حذف الحساب؟\')">
        <label>'. "ادخل كلمة المرور لتأكيد حذف الحساب".'</label><br>
        <input type="password" name="password" required><br><br>
<input type="submit" name="deleteAccount" value="'."حذف الحساب".'">
    </form>
    ';
?>
