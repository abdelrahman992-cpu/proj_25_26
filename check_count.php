<?php
include("conn.php");
$res = mysqli_query($conn, "SELECT COUNT(id) as total FROM terms");
$row = mysqli_fetch_assoc($res);
echo $row['total'];
?>
