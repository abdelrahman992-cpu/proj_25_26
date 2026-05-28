<?php
session_start(); // يجب أن تكون هنا في أول سطر
ob_start();

include("conn.php");


if(empty($_SESSION['user_id'])){
    header("Location: signin.php");
    exit;
}
include("header.php");
include("post.php");
include("footer.php");
?>
