<?php
 date_default_timezone_set("Africa/Cairo");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("connection.php");
include_once("config.php");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
