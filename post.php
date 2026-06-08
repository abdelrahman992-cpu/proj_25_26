<?php
$token = $_SESSION['access_token'] ?? null;

// نجلب البيانات مرة واحدة فقط من المسار الصحيح والموجود
$user = callAPI("GET", "/users/mee/", false, $token); 




if (isset($user['username'])) {
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['phone']    = $user['phone'];
    $_SESSION['role']     = $user['role'];
} else {
    $error = "❌ فشل جلب بيانات المستخدم.";
}

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

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg p-3 mb-5 bg-white rounded">
                <div class="card-body text-center">
                    <h4 class="card-title mb-4">الملف الشخصي</h4>
                    
                    <?php if (isset($user)) : ?>
  <div class="profile-info">
    <p>مرحباً: <?php echo htmlspecialchars($user['username'] ?? $_SESSION['username']); ?></p>
    <p>البريد الالكتروني: <?php echo htmlspecialchars($user['email'] ?? 'غير متوفر'); ?></p>
    <p>رقم الهاتف: <?php echo htmlspecialchars($user['phone'] ?? 'غير متوفر'); ?></p>
    <p>الدور: <?php echo htmlspecialchars($user['role'] ?? 'مستخدم'); ?></p>
</div>            <?php endif; ?>

                    <hr>

                    <form method="post" onsubmit="return confirm('هل أنت متأكد من حذف الحساب؟')" class="mt-3">
                        <div class="form-group">
                            <label class="font-weight-bold">ادخل كلمة المرور لتأكيد حذف الحساب:</label>
                            <input type="password" name="password" class="form-control text-center" required>
                        </div>
                        <button type="submit" name="deleteAccount" class="btn btn-danger btn-block mt-3">حذف الحساب</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

