<?php
session_start();
include("connection.php");

// حذف السيشن من جدول sections
$session_id = session_id();

$stmt = mysqli_prepare($connect, "DELETE FROM sections WHERE deta=?");
mysqli_stmt_bind_param($stmt, "s", $session_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// تدمير السيشن
session_unset();
session_destroy();

// تحويل للوجين
header("Location: signin.php");
exit;
?>