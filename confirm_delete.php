<?php
include("conn.php");

if(!isset($_SESSION['delete_user'])){
    header("Location: delete_user.php");
    exit;
}

if(isset($_POST['SubmitConfirm'])){

    $otp_input = trim($_POST['otp']);

    // ✅ تحقق من شكل OTP
    if(!preg_match('/^[0-9]{6}$/', $otp_input)){
        $error = translate("❌ كود غير صالح");
    } else {

        $user_id = $_SESSION['delete_user'];

        $stmt = mysqli_prepare($connect, "SELECT delete_otp, delete_expire FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if($row && 
           hash_equals($row['delete_otp'], $otp_input) && 
           strtotime($row['delete_expire']) > time()){

            $stmt2 = mysqli_prepare($connect, "DELETE FROM users WHERE id=?");
            mysqli_stmt_bind_param($stmt2, "i", $user_id);
            mysqli_stmt_execute($stmt2);

            session_destroy();

            echo translate("✅ تم حذف الحساب نهائياً");
            exit;

        } else {
            $error = translate("❌ كود غير صحيح أو منتهي");
        }
    }
}
?>

<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title><?php  echo translate("تأكيد حذف الحساب") ?></title>
</head>
<body>
<div>
    <img height="161" src="images/banner.jpg" width="1550">
</div>
<h2><?php  echo translate("تأكيد حذف الحساب") ?></h2>

<?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php
echo '
<form method="post">
    <label>'.translate("ادخل كود التأكيد المرسل على الإيميل").':</label><br>
    <input type="text" name="otp" required><br><br>
    <input type="submit" name="SubmitConfirm" value="'.translate("تأكيد الحذف").'">
</form>';
?>

