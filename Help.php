
<html xmlns="http://www.w3.org/1999/xhtml" dir="rtl">

<head>
<meta content="en-us" http-equiv="Content-Language" />
<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<title>مساعدة</title>
<style type="text/css">
.style1 {
	font-size: xx-large;
	text-align: center;
}
</style>
</head>

<body>
<div>

  <?php
  include("conn.php");
include("header.php");

if(!empty($_SESSION['user_id'])){
    include("post.php");
}


      ?>
<p class="style1"><span lang="ar-eg">المساعدة</span></p>
<p class="style1">المراجع نموذج موقع قاموس المصطلحات للصف الثاني الثانوي نظام قديم</p>

<?php include("footer.php"); ?>
</body>

</html>
