<?php
session_start(); // يجب أن تكون هنا في أول سطر
ob_start();

include("conn.php");
$check_count = mysqli_query($connect, "SELECT COUNT(*) as total FROM terms");
$row = mysqli_fetch_assoc($check_count);

if ($row['total'] < 10) { 
    // إذا كان القاموس فارغاً، استدعي ملف الاستيراد فوراً وبصمت
    include_once("import_all_ncbi.php");
}

if(empty($_SESSION['user_id'])){
    header("Location: signin.php");
    exit;
}
include("header.php");
include("post.php");
include("footer.php");
?>
