<?php
include("conn.php");
include("validation.php"); // تم تضمينه مرة واحدة فقط في البداية

if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}

include("header.php");

// معالجة عملية التحديث عند الضغط على زر الإضافة (التعديل)
if(isset($_POST['Submit2'])) {
    $iddata = sanStr($_POST['iddata']);
    $terma = sanStr($_POST['txt_term']);
    $transa = sanStr($_POST['trans']);
    $defea = sanStr($_POST['TextArea1']);
    $old_pic = $_POST['pic'];

    // فحص إذا تم رفع صورة جديدة
    if(!empty($_FILES['filedata']['name'])) {
        if(!is_dir('pic')){ mkdir('pic'); }
        $fileName = $_FILES['filedata']['name'];
        $tmpName  = $_FILES['filedata']['tmp_name'];
        move_uploaded_file($tmpName, 'pic/'.$fileName);
        $picturea = "pic/" . $fileName; // إزالة المسافة الزائدة
    } else {
        $picturea = $old_pic; // الاحتفاظ بالصورة القديمة
    }

    mysqli_query($connect, "SET NAMES 'utf8'");
    $update_sql = "UPDATE terms SET term='$terma', trans='$transa', defe='$defea', picture='$picturea' WHERE id='$iddata'";
    $update_query = mysqli_query($connect, $update_sql);

    if($update_query) {
        echo "<script>alert('تم التعديل بنجاح'); window.location.href='update_term.php';</script>";
        exit;
    } else {
        echo "خطأ في التعديل: " . mysqli_error($connect);
    }
}
?>

<html dir="rtl">
<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
    <title>تحديث مصطلح</title>
</head>
<body>
    <div class="container text-center">
        <h1>تعديل مصطلح</h1>
        <?php
        mysqli_query($connect, "SET NAMES 'utf8'");
        $sql = "SELECT * FROM terms";
        $query = mysqli_query($connect, $sql);
        $num = mysqli_num_rows($query);
        echo "<h3>عدد المصطلحات حالياً: $num</h3>";
        ?>

        <table class="table table-bordered table-dark" dir="rtl">
            <thead>
                <tr>
                    <th>المسلسل</th>
                    <th>المصطلح</th>
                    <th>الترجمة</th>
                    <th>التعريف</th>
                    <th>الصورة</th>
                    <th>الخصائص</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_array($query)) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['term']; ?></td>
                    <td><?php echo $row['trans']; ?></td>
                    <td><?php echo $row['defe']; ?></td>
                    <td><img src="<?php echo $row['picture']; ?>" width="50" height="50"></td>
                    <td><a href="update_term.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">تعديل</a></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>

        <?php
        // إظهار نموذج التعديل فقط عند الضغط على رابط "تعديل"
        if(isset($_GET['id'])) {
            $id_to_edit = $_GET['id'];
            $res = mysqli_query($connect, "SELECT * FROM terms WHERE id='$id_to_edit'");
            $data = mysqli_fetch_array($res);
            
            if($data) {
        ?>
        <hr style="border: 2px solid maroon;">
        <h2>تعديل بيانات المصطلـح: <?php echo $data['term']; ?></h2>
        
        <form method="post" action="update_term.php" enctype="multipart/form-data" class="text-right" style="display:inline-block; margin-top:20px;">
            <input name="iddata" type="hidden" value="<?php echo $data['id']; ?>" />
            <input name="pic" type="hidden" value="<?php echo $data['picture']; ?>" />
            
            <label>المصطلح:</label><br>
            <input name="txt_term" type="text" class="form-control" style="width: 400px" value="<?php echo $data['term']; ?>" required /><br>
            
            <label>الترجمة:</label><br>
            <input name="trans" type="text" class="form-control" style="width: 400px" value="<?php echo $data['trans']; ?>" required /><br>
            
            <label>التعريف:</label><br>
            <textarea name="TextArea1" class="form-control" style="width: 400px; height: 80px;" required><?php echo $data['defe']; ?></textarea><br>
            
            <label>الصورة الحالية:</label><br>
            <img src="<?php echo $data['picture']; ?>" width="80" height="80"><br>
            <label>تغيير الصورة (اختياري):</label><br>
            <input name="filedata" type="file" class="form-control" style="width: 400px;"><br>
            
            <input name="Submit2" class="btn btn-success" type="submit" value="حفظ التعديلات" />
        </form>
        <?php 
            } 
        } 
        ?>
    </div>
    <?php include('footer.php'); ?>
</body>
</html>
