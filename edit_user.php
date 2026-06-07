<?php
session_start();
// لا تقم بتضمين conn.php لأننا لا نريد mysqli هنا

if(!isset($_SESSION['access_token'])) { header("Location: signin.php"); exit; }

// --- 1. تعريف $ch أولاً قبل استخدامه ---
$api_url = "http://127.0.0.1:8000/users/me/";
$ch = curl_init($api_url); 

// --- 2. إعدادات الـ cURL ---
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $_SESSION['access_token'],
    'Content-Type: application/json'
]);

// --- 3. التنفيذ ---
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch); // أغلق المقبض بعد الانتهاء

// فك التشفير
$old_data = ($http_code == 200) ? json_decode($response, true) : ['username' => 'خطأ في الاتصال', 'email' => '', 'phone' => ''];

// --- معالجة الضغط على زر الحفظ ---
if(isset($_POST['update_btn'])){
    // إعادة تعريف $ch مرة أخرى لطلب التحديث
    $ch_update = curl_init('http://127.0.0.1:8000/user/request-update/');
    curl_setopt($ch_update, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_update, CURLOPT_POST, true);
    curl_setopt($ch_update, CURLOPT_POSTFIELDS, json_encode([
        'new_name' => $_POST['username'],
        'new_email' => $_POST['email'],
        'new_phone' => $_POST['phone']
    ]));
    curl_setopt($ch_update, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $_SESSION['access_token'],
        'Content-Type: application/json'
    ]);
    
    curl_exec($ch_update);
    curl_close($ch_update);
    
    header("Location: verify_change.php");
    exit;
}
?>

<?php include("header.php"); ?>
<h2>تعديل بيانات الحساب</h2>
<form method="post" action="">
    <label>اسم المستخدم:</label><br>
    <input type="text" name="username" value="<?php echo htmlspecialchars($old_data['username'] ?? ''); ?>" required><br><br>
    <label>البريد الإلكتروني:</label><br>
    <input type="email" name="email" value="<?php echo htmlspecialchars($old_data['email'] ?? ''); ?>" required><br><br>
    <label>رقم الهاتف:</label><br>
    <input type="text" name="phone" value="<?php echo htmlspecialchars($old_data['phone'] ?? ''); ?>"><br><br>
    <input type="submit" name="update_btn" value="حفظ التعديلات">
</form>
<?php include("footer.php"); ?>
