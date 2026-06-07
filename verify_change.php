<?php
include("conn.php");
include("validation.php");
include("header.php");

if (isset($_POST['verify_btn'])) {
    $input_otp = $_POST['code'] ?? '';
    
    // إعداد الـ cURL
    $url = "http://127.0.0.1:8000/otp/change/";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['code' => $input_otp]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . ($_SESSION['access_token'] ?? ''),
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    
    // التحقق من وجود خطأ في الاتصال
    if (curl_errno($ch)) {
        echo "❌ خطأ في الاتصال: " . curl_error($ch);
    } else {
        // تحويل الرد إلى مصفوفة (هنا تم تعريف $result)
        $result = json_decode($response, true);
        echo "رد الـ API: " . $response;

        // الآن الشرط يستخدم $result التي تم تعريفها للتو
        if ($result && isset($result['status']) && $result['status'] == 'success') {
            echo "✅ تم التحقق بنجاح!";
        } else {
            $error_msg = $result['detail'] ?? "الكود غير صحيح أو منتهي الصلاحية.";
            echo "❌ " . $error_msg;
        }
    }
    curl_close($ch);
} 


?>

<form method="post" action="">
    <input type="text" name="code" placeholder="أدخل كود التحقق" required>
    <button type="submit" name="verify_btn">تأكيد</button>
</form>
<?php include("footer.php");
?>

