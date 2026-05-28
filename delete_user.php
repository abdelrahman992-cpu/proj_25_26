<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: signin.php");
    exit;
}

$_SESSION['delete_user'] = $_SESSION['user_id'];

header("Location: confirm_delete.php");
exit;
?>