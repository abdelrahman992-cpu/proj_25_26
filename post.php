

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

// ================= حذف الحساب =================
if (isset($_POST['deleteAccount'])) {
    if (!empty($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $pass = $_POST['password'];

        $stmt = mysqli_prepare($connect, "SELECT * FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && password_verify($pass, $row['passwor'])) {
            $otp = rand(100000, 999999);
            $expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));

            $stmt2 = mysqli_prepare($connect, "UPDATE users SET delete_otp=?, delete_expire=? WHERE id=?");
            mysqli_stmt_bind_param($stmt2, "ssi", $otp, $expire, $user_id);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            $_SESSION['delete_user'] = $user_id;

            if (sendOTPo($row['email'], $otp, $sender_email, $sender_pass)) {
                header("Location: confirm_delete.php");
                exit;
            } else {
                $error = "❌ فشل إرسال الإيميل";
            }

        } else {
            $error = "❌ كلمة المرور غلط";
        }

    } else {
        $error = "❌ لم يتم التعرف على المستخدم";
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

