<?php
// ============================
// تشغيل الاتصال والسيشن أولاً
// ============================
require_once("conn.php");
require_once("header.php");

// ضبط الترميز العربي
mysqli_set_charset($connect,"utf8");

// عرض post لو المستخدم مسجل دخول
if(!empty($_SESSION['user_id'])){
    include("post.php");
}
?>

<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="utf-8">
<title>بحث عن مصطلح</title>
</head>

<body>

<p class="style1">
<strong>بحث عن مصطلح</strong>
</p>

<form method="post" action="">
    <div class="form-group">
        مصطلح البحث
        <input name="txt_search"
               class="form-control"
               type="text"
               style="width:332px;height:25px;">
    </div>

    <div class="form-group">
        <input name="submit1"
               class="btn btn-primary"
               type="submit"
               value="البحث بالكلمة">
    </div>
</form>

<br>

<table class="table table-dark">
<tr>
    <td>المصطلح</td>
    <td>الترجمة</td>
    <td>التعريف</td>
    <td>الصورة</td>
</tr>

<?php

    // حماية من SQL Injection

// ============================
// تنفيذ البحث (معدل لعرض المقبول فقط)
// ============================
if(isset($_POST['submit1']) && !empty($_POST['txt_search'])){

    $txt_search = $_POST['txt_search'];

    // التعديل هنا: أضفنا شرط status = 'approved'
    $stmt = $connect->prepare(
        "SELECT * FROM terms WHERE status = 'approved' AND term LIKE ? ORDER BY term ASC"
    );

    $search = "%$txt_search%";

    $stmt->bind_param("s", $search);
    $stmt->execute();

    $result = $stmt->get_result();
    

    if($result->num_rows > 0){

        while($row = $result->fetch_assoc()){

            $term = htmlspecialchars($row['term']);
            $trans = htmlspecialchars($row['trans']);
            $defe = htmlspecialchars($row['defe']);
            $picture = $row['picture'];

            echo "
            <tr>
                <td>$term</td>
                <td>$trans</td>
                <td>$defe</td>
<td><img src='$picture' width='80' height='80' alt='معاينة'></td>
            </tr>";
        }

    }else{
        echo "<tr><td colspan='4'>لا توجد نتائج</td></tr>";
    }
}
?>

</table>

<?php include("footer.php"); ?>

</body>
</html>
