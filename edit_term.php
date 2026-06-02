<?php
include("conn.php");
include("validation.php"); 

if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}
/*
if($_SESSION['role']==="user"){
require_once("conn.php");
require_once("header.php");
    require_once("post.php");
 echo("أنت لست مسؤل");
    exit;
}
*/
include("header.php");

// 1. معالجة التحديث ليشمل عمود smiles_code
if(isset($_POST['Submit2'])) {
    $iddata = sanStr($_POST['iddata']);
    $terma = sanStr($_POST['txt_term']);
    $transa = sanStr($_POST['trans']);
    $defea = sanStr($_POST['TextArea1']);
    $smilesa = sanStr($_POST['smiles_code']); // استلام الكود الجديد
    $old_pic = $_POST['pic'];

    if(!empty($_FILES['filedata']['name'])) {
        if(!is_dir('pic')){ mkdir('pic'); }
        $fileName = $_FILES['filedata']['name'];
        $tmpName  = $_FILES['filedata']['tmp_name'];
        move_uploaded_file($tmpName, 'pic/'.$fileName);
        $picturea = "pic/" . $fileName;
    } else {
        $picturea = $old_pic;
    }

    mysqli_query($connect, "SET NAMES 'utf8'");
    // تحديث الاستعلام ليشمل smiles_code
    $update_sql = "UPDATE terms SET term='$terma', trans='$transa', defe='$defea', picture='$picturea', smiles_code='$smilesa' WHERE id='$iddata'";
    $update_query = mysqli_query($connect, $update_sql);

    if($update_query) {
        echo "<script>alert('تم التعديل بنجاح'); window.location.href='edit_term.php';</script>";
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
    <style>
        .molecule-img { background: white; border-radius: 8px; padding: 5px; border: 1px solid #444; }
    </style>
</head>
<body>
    <div class="container-fluid text-center mt-4">
        <h1>🛠️ إدارة وتعديل المصطلحات البيولوجية</h1>
        <?php
        mysqli_query($connect, "SET NAMES 'utf8'");
     // لجعل الأدمن يعدل فقط على المصطلحات المقبولة أو المعلقة، ويخفي المرفوضة نهائياً:
$sql = "SELECT * FROM terms WHERE status != 'rejected'";
        $query = mysqli_query($connect, $sql);
        $num = mysqli_num_rows($query);
        echo "<h3>إجمالي المصطلحات: <span class='badge badge-info'>$num</span></h3>";
        ?>

        <table class="table table-bordered table-dark table-striped mt-3" dir="rtl">
            <thead>
                <tr>
                    <th>المسلسل</th>
                    <th>المصطلح</th>
                    <th>الترجمة</th>
                    <th>كود SMILES</th>
                    <th>صورة المركب</th>
                    <th>الخصائص</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_array($query)) { 
                    // منطق عرض الصورة: إذا وجد SMILES نستخدم PubChem، وإلا نستخدم الصورة المخزنة
                    $smiles = $row['smiles_code'];
                    $display_img = (!empty($smiles) && $smiles != 'N/A') 
                        ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" 
                        : $row['picture'];
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><b><?php echo $row['term']; ?></b></td>
                    <td><?php echo $row['trans']; ?></td>
                    <td><small><code><?php echo $smiles; ?></code></small></td>
                    <td><img src="<?php echo $display_img; ?>" class="molecule-img" width="70" height="70"></td>
                    <td><a href="edit_term.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">📝 تعديل</a></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>

        <?php
        if(isset($_GET['id'])) {
            $id_to_edit = $_GET['id'];
            $res = mysqli_query($connect, "SELECT * FROM terms WHERE id='$id_to_edit'");
            $data = mysqli_fetch_array($res);
            
            if($data) {
        ?>
        <div class="card bg-light text-dark p-4 mt-5 mb-5 shadow" style="max-width: 600px; margin: auto;">
            <h2>تعديل بيانات: <?php echo $data['term']; ?></h2>
            <form method="post" action="edit_term.php" enctype="multipart/form-data" class="text-right">
                <input name="iddata" type="hidden" value="<?php echo $data['id']; ?>" />
                <input name="pic" type="hidden" value="<?php echo $data['picture']; ?>" />
                
                <div class="form-group">
                    <label>المصطلح (English):</label>
                    <input name="txt_term" type="text" class="form-control" value="<?php echo $data['term']; ?>" required />
                </div>
                
                <div class="form-group">
                    <label>الترجمة العربية:</label>
                    <input name="trans" type="text" class="form-control" value="<?php echo $data['trans']; ?>" required />
                </div>

                <div class="form-group">
                    <label>SMILES Code (لصور المركبات الكيميائية):</label>
                    <input name="smiles_code" type="text" class="form-control" placeholder="مثال: CN(C)C(=N)N=C(N)N" value="<?php echo $data['smiles_code']; ?>" />
                    <small class="text-muted">هذا الكود سيقوم بتوليد صورة المركب تلقائياً.</small>
                </div>
                
                <div class="form-group">
                    <label>التعريف العلمي:</label>
                    <textarea name="TextArea1" class="form-control" style="height: 100px;" required><?php echo $data['defe']; ?></textarea>
                </div>
                
                <div class="form-group text-center">
                    <label>الصورة الحالية:</label><br>
                    <img src="<?php echo (!empty($data['smiles_code'])) ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/".urlencode($data['smiles_code'])."/PNG" : $data['picture']; ?>" width="100" class="img-thumbnail"><br>
                    <label class="mt-2">تغيير الصورة يدوياً (اختياري):</label>
                    <input name="filedata" type="file" class="form-control-file border p-1">
                </div>
                
                <button name="Submit2" class="btn btn-success btn-block mt-4" type="submit">✅ حفظ التعديلات النهائية</button>
            </form>
        </div>
        <?php 
            } 
        } 
        ?>
    </div>
    <?php include('footer.php'); ?>
</body>
</html>
