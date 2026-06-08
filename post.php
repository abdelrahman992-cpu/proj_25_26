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

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg p-3 mb-5 bg-white rounded">
                <div class="card-body text-center">
                    <h4 class="card-title mb-4">الملف الشخصي</h4>
                    
                    <?php if (isset($user)) : ?>
                        <div class="text-right d-inline-block">
                            <p><strong>مرحباً:</strong> <span class="notranslate"><?php echo htmlspecialchars($_SESSION['username'] ?? ""); ?></span></p>
                            <p><strong>البريد الالكتروني:</strong> <span class="notranslate"><?php echo htmlspecialchars($_SESSION['email'] ?? ""); ?></span></p>
                            <p><strong>رقم الهاتف:</strong> <?php echo htmlspecialchars($_SESSION['phone'] ?? ""); ?></p>
                            <p><strong>الدور:</strong> <?php echo htmlspecialchars($_SESSION['role'] ?? ""); ?></p>
                        </div>
                    <?php endif; ?>

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

