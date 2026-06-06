

  <?php
  $stmt = mysqli_prepare($connect, 
    "SELECT id, username, email, phone ,role FROM users WHERE id=?");

    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if($row = mysqli_fetch_assoc($result)){
        $_SESSION['username'] = $row['username'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['phone'] = $row['phone'];
                $_SESSION['role'] = $row['role'];
    }

    mysqli_stmt_close($stmt);


if (isset($_POST['deleteAccount'])) {
    $pass = $_POST['password'];
    $email = $_SESSION['email'];

    // نرسل الإيميل وكلمة المرور للـ API
    $result = callAPI("POST", "/otp/send-delete/?email=" . urlencode($email) . "&password=" . urlencode($pass));

    if (isset($result['message'])) {
        $_SESSION['delete_user'] = $_SESSION['user_id'];
        header("Location: confirm_delete.php");
        exit;
    } else {
        // عرض الخطأ القادم من الـ API (مثل "كلمة المرور غير صحيحة")
        $error = "❌ " . ($result['detail'] ?? "فشل إرسال كود الحذف");
    }
}

echo ("مرحبا") . " " .htmlspecialchars($_SESSION['username'] ?? "");
echo "<br>";
echo ("البريد الالكتروني") . " : " . $_SESSION['email'];
echo "<br>";
echo ("رقم الهاتف") . " : " . $_SESSION['phone'];
echo "<br>";
echo ("الدور") . " : " . $_SESSION['role'];

    if (!empty($error)) {
        echo "<p style='color:red;'>$error</p>";
    }

    echo '
    <form method="post" onsubmit="return confirm(\'هل أنت متأكد من حذف الحساب؟\')">
        <label>'. "ادخل كلمة المرور لتأكيد حذف الحساب".'</label><br>
        <input type="password" name="password" required><br><br>
<input type="submit" name="deleteAccount" value="'."حذف الحساب".'">
    </form>
    ';
?>

