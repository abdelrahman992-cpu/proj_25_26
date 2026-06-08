<?php


?>

<!DOCTYPE html>

<html dir="rtl" lang="ar">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">

<link rel="stylesheet"
href="https://use.fontawesome.com/releases/v5.15.2/css/all.css">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{
font-family:Arial,sans-serif;
}

/* Banner */
.banner img{
width:100%;
height:160px;
object-fit:cover;
}

/* NAVBAR */
.navbar{
background:#333;
width:100%;
}

/* القائمة */
.nav-links{
list-style:none;
display:flex;
align-items:center;
flex-wrap:wrap;
}

/* العناصر */
.nav-links li{
position:relative;
}

/* الروابط */
.nav-links li a{
color:white;
text-decoration:none;
padding:14px 18px;
display:block;
}

/* Hover */
.nav-links li a:hover{
background:#555;
}

/* عناصر السوشيال */
.social{
margin-right:auto;
display:flex;
}

/* Dropdown */
.dropdown-content{
display:none;
position:absolute;
background:#160855;
min-width:180px;
top:100%;
right:0;
z-index:1000;
}

.dropdown-content a{
color:white;
padding:10px;
display:block;
}

.dropdown-content a:hover{
background:#080202;
}

.dropdown:hover .dropdown-content{
display:block;
}

/* Footer */
footer{
background:#555;
color:white;
padding:15px;
text-align:center;
margin-top:40px;
}
.goog-te-banner-frame {
display:none !important;
}

body{
top:0 !important;
}
</style>

</head>

<body>
<div id="google_translate_element" style="display:none;"></div>

<script>
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'ar',
        autoDisplay:false
    }, 'google_translate_element');
}
</script>
<script>
function changeLanguage(lang){

    var select = document.querySelector(".goog-te-combo");

    if(!select){
        alert("Translator not loaded yet");
        return;
    }

    select.value = lang;
    select.dispatchEvent(new Event("change"));
}
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
<div>
    <img height="161" src="images/banner.jpg" width="100%">
</div>

<div style="width: 100%; text-align:right;">


<?php if(empty($_SESSION['username'])): ?>
<nav class="navbar">

    <ul class="nav-links">
<li class="left"><a href="signin.php">تسجيل الدخول</a></li>

<li class="dropdown">
<a href="#">🌐 اللغة</a>

<ul class="dropdown-content">

<li><a href="#" onclick="changeLanguage('ar')">🇪🇬 العربية</a></li>

<li><a href="#" onclick="changeLanguage('en')">🇺🇸 English</a></li>

<li><a href="#" onclick="changeLanguage('fr')">🇫🇷 Français</a></li>

<li><a href="#" onclick="changeLanguage('de')">🇩🇪 Deutsch</a></li>

<li><a href="#" onclick="changeLanguage('es')">🇪🇸 Español</a></li>

<li><a href="#" onclick="changeLanguage('it')">🇮🇹 Italiano</a></li>

<li><a href="#" onclick="changeLanguage('tr')">🇹🇷 Türkçe</a></li>

<li><a href="#" onclick="changeLanguage('ru')">🇷🇺 Русский</a></li>

<li><a href="#" onclick="changeLanguage('zh-CN')">🇨🇳 中文</a></li>

<li><a href="#" onclick="changeLanguage('ja')">🇯🇵 日本語</a></li>

</ul>
</li>


<li class="left"><a href="index.php">الرئيسية</a></li>
<li class="left"><a href="Add_Term.php">إضافة مصطلح</a></li>

<li class="left"><a href="search_term.php">البحث عن مصطلح</a></li>
<li class="left"><a href="Del_Term.php">حذف مصطلح</a></li>
<li class="left"><a href="edit_user.php">تعديل مصطلح</a></li>
<li class="left"><a href="user_history.php"> سجل المصطلحات</a></li>
<li class="left"><a href="Help.php">مساعدة</a></li>


    </ul>

</nav>
<?php endif; ?>
<?php if(!empty($_SESSION['username'])): ?>

<?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>

<nav class="navbar">

    <ul class="nav-links">
  
<li class="left"><a href="index.php">الرئيسية</a></li>

<li class="dropdown">
<a href="#">🌐 اللغة</a>

<ul class="dropdown-content">

<li><a href="#" onclick="changeLanguage('ar')">🇪🇬 العربية</a></li>

<li><a href="#" onclick="changeLanguage('en')">🇺🇸 English</a></li>

<li><a href="#" onclick="changeLanguage('fr')">🇫🇷 Français</a></li>

<li><a href="#" onclick="changeLanguage('de')">🇩🇪 Deutsch</a></li>

<li><a href="#" onclick="changeLanguage('es')">🇪🇸 Español</a></li>

<li><a href="#" onclick="changeLanguage('it')">🇮🇹 Italiano</a></li>

<li><a href="#" onclick="changeLanguage('tr')">🇹🇷 Türkçe</a></li>

<li><a href="#" onclick="changeLanguage('ru')">🇷🇺 Русский</a></li>

<li><a href="#" onclick="changeLanguage('zh-CN')">🇨🇳 中文</a></li>

<li><a href="#" onclick="changeLanguage('ja')">🇯🇵 日本語</a></li>

</ul>
</li>

<li class="left"><a href="Add_Term.php">إضافة مصطلح</a></li>
<li class="left"><a href="search_term.php">البحث عن مصطلح</a></li>

<li class="left"><a href="Del_Term.php">حذف مصطلح</a></li>
<li class="left"><a href="edit_term.php">تعديل مصطلح</a></li>
<li class="left"><a href="edit_user.php">تعديل مستخدم</a></li>

<li class="left"><a href="Help.php">مساعدة</a></li>
<li class="left"><a href="admin_dashboard.php">لوحة التحكم</a></li>
<li class="left"><a href="approve_terms.php"> موافقة أو رفض</a></li>
<li class="left"><a href="user_history.php"> سجل المصطلحات</a></li>
<li class="left"><a href="signout.php">تسجيل الخروج</a></li>



    </ul>
</nav>
<?php else : ?>
<nav class="navbar">

    <ul class="nav-links">
  
<li class="left"><a href="index.php">الرئيسية</a></li>

<li class="dropdown">
<a href="#">🌐 اللغة</a>

<ul class="dropdown-content">

<li><a href="#" onclick="changeLanguage('ar')">🇪🇬 العربية</a></li>

<li><a href="#" onclick="changeLanguage('en')">🇺🇸 English</a></li>

<li><a href="#" onclick="changeLanguage('fr')">🇫🇷 Français</a></li>

<li><a href="#" onclick="changeLanguage('de')">🇩🇪 Deutsch</a></li>

<li><a href="#" onclick="changeLanguage('es')">🇪🇸 Español</a></li>

<li><a href="#" onclick="changeLanguage('it')">🇮🇹 Italiano</a></li>

<li><a href="#" onclick="changeLanguage('tr')">🇹🇷 Türkçe</a></li>

<li><a href="#" onclick="changeLanguage('ru')">🇷🇺 Русский</a></li>

<li><a href="#" onclick="changeLanguage('zh-CN')">🇨🇳 中文</a></li>

<li><a href="#" onclick="changeLanguage('ja')">🇯🇵 日本語</a></li>

</ul>
</li>

<li class="left"><a href="Add_Term.php">إضافة مصطلح</a></li>
<li class="left"><a href="search_term.php">البحث عن مصطلح</a></li>

<li class="left"><a href="Del_Term.php">حذف مصطلح</a></li>
<li class="left"><a href="edit_term.php">تعديل مصطلح</a></li>
<li class="left"><a href="edit_user.php">تعديل مستخدم</a></li>
<li class="left"><a href="user_history.php"> سجل المصطلحات</a></li>
<li class="left"><a href="admin_requests.php">طلب الترقي لمسؤول</a></li>
<li class="left"><a href="Help.php">مساعدة</a></li>
<li class="left"><a href="signout.php">تسجيل الخروج</a></li>




    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>


</ul>
</nav>

