<?php
 include("conn.php");
if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}
include("header.php");
include("validation.php");
?>
<html dir="rtl">
<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
    <title>ادخال مصطلح للقاموس</title>
</head>

<body dir="rtl">
    <div style="text-align:center"> <h1>إدخال مصطلح</h1></div>
    <form method="post" action="" enctype="multipart/form-data"> <!-- تم ترك الاكشن فارغاً ليفحص في نفس الصفحة -->

        <div class="style2">
            <br />
            <div class="form-group">
                المصطلح &nbsp; &nbsp;
                <input name="txt_term" class="form-control" type="text" style="width: 482px" required /> <br />
            </div>
            <br />
            <div class="form-group">
                ترجمته &nbsp; &nbsp;
                <input name="trans" class="form-control" type="text" style="width: 482px" required /> <br />
            </div>
            <br />
            <div class="form-group">
                وصف المصطلح &nbsp; &nbsp;
                <textarea name="TextArea1" class="form-control" style="width: 480px; height: 31px" required></textarea> <br />
            </div>
            <br />
            <div class="form-group">
                الصورة &nbsp; &nbsp;
                <input name="File1" class="form-control" style="width: 488px" type="file" required/> <br />
            </div>
            <br />
            <div class="form-group">
                <input name="Submit1" class="btn btn-primary" style="width: 76px" type="submit" value="إضافة" /><br />
            </div>
        </div>
    </form>

    <?php
 if(isset($_POST['Submit1'])) {
    $term = sanStr($_POST['txt_term']);
    $trans = sanStr($_POST['trans']);
    $defe = sanStr($_POST['TextArea1']);
    
    // إعدادات الملف
    $file_name = $_FILES['File1']['name'];
    $file_tmp  = $_FILES['File1']['tmp_name'];
    $file_error = $_FILES['File1']['error'];
    
    // التأكد من عدم وجود أخطاء في الملف المرفوع من المتصفح
    if($file_error === 0) {
        $file_name = str_replace(' ', '_', $file_name); // إزالة المسافات من الاسم
        $target_path = "pic/" . $file_name;

        // محاولة نقل الملف للمجلد الذي أنشأته يدوياً
        if(move_uploaded_file($file_tmp, $target_path)) {
            // النجاح: تخزين المسار في قاعدة البيانات بدون مسافات زائدة
            $query = mysqli_query($connect, "INSERT INTO terms (`term`, `trans`, `defe`, `picture`, `status`) VALUES ('$term', '$trans', '$defe', '$target_path', 'pending')");
            
            if($query) {
                echo "<p style='color:green; font-size:large;'>تم رفع الصورة وحفظ البيانات بنجاح!</p>";
            } else {
                echo "<p style='color:red;'>خطأ في قاعدة البيانات: " . mysqli_error($connect) . "</p>";
            }
        } else {
            echo "<p style='color:red;'>فشل نقل الملف إلى مجلد pic. تأكد من أن المجلد ليس 'ل للقراءة فقط'.</p>";
        }
    } else {
        echo "<p style='color:red;'>خطأ في رفع الملف من جهازك، رمز الخطأ: $file_error</p>";
    }
}
    include('footer.php');
    ?>
</body>
</html>
