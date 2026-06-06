<?php
include("conn.php"); // تأكد من تضمين دالة callAPI

if(!isset($_SESSION['user_id'])){
    header("Location: signin.php");
    exit;
}

// طلب إرسال كود حذف من الـ API
// نفترض أن لديك مسار في الـ API اسمه /otp/send-delete/
$email = $_SESSION['email']; // تأكد أن الإيميل مخزن في السيشن
$result = callAPI("POST", "/otp/send-delete/?email=" . urlencode($email));

if(isset($result['message'])) {
    $_SESSION['delete_pending'] = true;
    header("Location: confirm_delete.php");
    exit;
} else {
    echo "❌ فشل طلب كود الحذف: " . ($result['detail'] ?? "خطأ غير معروف");
}
?>
