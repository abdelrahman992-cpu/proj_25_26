<?php
ob_start();
session_start();
include_once("conn.php");
include_once("validation.php"); 
include_once("api.php"); // يحتوي على الدوال المساعدة

$db = $connect ?? $conn;
$message = "";

// --- 1. معالج التعديل السريع (تغيير الحالة فقط) ---
// يعمل عند الضغط على أزرار (تفعيل / تعطيل) في الجدول
if (isset($_GET['status_id']) && isset($_GET['new_status'])) {
    $id = intval($_GET['status_id']);
    $status = mysqli_real_escape_string($db, $_GET['new_status']);

    $update_sql = "UPDATE terms SET status = '$status' WHERE id = $id";
    
    if (mysqli_query($db, $update_sql)) {
        echo "<script>alert('تم تحديث حالة المصطلح بنجاح!'); window.location.href='edit_term.php';</script>";
        exit;
    } else {
        die("خطأ في تحديث الحالة: " . mysqli_error($db));
    }
}

// --- 2. معالج التعديل الكامل (نموذج التعديل) ---
// يعمل عند الضغط على زر "حفظ التغييرات" في النموذج الأسفل
if (isset($_POST['Submit2'])) {
    $iddata = intval($_POST['iddata']); // المعرف القادم من الحقل المخفي
    $terma  = sanStr($_POST['txt_term']);
    $transa = sanStr($_POST['trans']);
    $defea  = sanStr($_POST['TextArea1']);
    $smilesa = sanStr($_POST['smiles_code']);
    $old_pic = $_POST['pic'];

    // معالجة رفع الصورة
    if (!empty($_FILES['filedata']['name'])) {
        $fileName = time() . "_" . $_FILES['filedata']['name'];
        if(move_uploaded_file($_FILES['filedata']['tmp_name'], 'pic/' . $fileName)) {
            $picturea = "pic/" . $fileName;
        } else {
            $picturea = $old_pic;
        }
    } else {
        $picturea = $old_pic;
    }

    $sql = "UPDATE terms SET term=?, trans=?, defe=?, picture=?, smiles_code=? WHERE id=?";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "sssssi", $terma, $transa, $defea, $picturea, $smilesa, $iddata);

    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('تم تعديل بيانات المصطلح بنجاح'); window.location.href='edit_term.php';</script>";
        exit;
    } else {
        die("خطأ في التعديل الكامل: " . mysqli_error($db));
    }
}

// التحقق من تسجيل الدخول
if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}

include("header.php");
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <title>إدارة القاموس البيولوجي</title>
    <style>
        .molecule-img { background: white; border-radius: 8px; padding: 5px; border: 1px solid #444; object-fit: contain; }
        .table-v-align td { vertical-align: middle !important; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-approved { background-color: #28a745; color: #fff; }
    </style>
</head>
<body class="bg-dark text-light">
    <div class="container-fluid text-center mt-4">
        <h1 class="mb-4">🛠️ لوحة التحكم في المصطلحات</h1>
        
        <?php
        // جلب المصطلحات (عرض المقبول والمعلق فقط)
        $sql = "SELECT * FROM terms WHERE status != 'rejected' ORDER BY id DESC";
        $query = mysqli_query($db, $sql);
        $num = mysqli_num_rows($query);
        ?>
        <h3>المصطلحات النشطة: <span class="badge badge-info"><?php echo $num; ?></span></h3>

        <div class="table-responsive mt-4">
            <table class="table table-bordered table-dark table-striped table-v-align">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>المصطلح</th>
                        <th>الترجمة</th>
                        <th>الحالة</th>
                        <th>العرض المرئي</th>
                        <th>العمليات السريعة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_array($query)) { 
                        $smiles = $row['smiles_code'];
                        $status_class = ($row['status'] == 'approved') ? 'badge-approved' : 'badge-pending';
                        
                        $display_img = (!empty($smiles) && $smiles != 'N/A') 
                            ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" 
                            : $row['picture'];
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><b><?php echo $row['term']; ?></b></td>
                        <td><?php echo $row['trans']; ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo $row['status']; ?></span></td>
                        <td><img src="<?php echo $display_img; ?>" class="molecule-img" width="50" height="50"></td>
                        <td>
                            <a href="edit_term.php?id=<?php echo $row['id']; ?>#edit-form" class="btn btn-warning btn-sm">📝 تعديل</a>

                            <?php if($row['status'] == 'approved'): ?>
                                <a href="edit_term.php?status_id=<?php echo $row['id']; ?>&new_status=pending" class="btn btn-outline-secondary btn-sm">⏳ تعطيل</a>
                            <?php else: ?>
                                <a href="edit_term.php?status_id=<?php echo $row['id']; ?>&new_status=approved" class="btn btn-outline-success btn-sm">✅ تفعيل</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php
        // عرض نموذج التعديل فقط عند اختيار ID
        if(isset($_GET['id'])) {
            $id_to_edit = intval($_GET['id']);
            $res = mysqli_query($db, "SELECT * FROM terms WHERE id='$id_to_edit'");
            $data = mysqli_fetch_array($res);
            if($data) {
        ?>
        <div id="edit-form" class="card bg-light text-dark p-4 mt-5 mb-5 shadow mx-auto" style="max-width: 800px;">
            <h2 class="text-primary border-bottom pb-2">📝 تعديل بيانات المصطلح</h2>
            <form method="post" enctype="multipart/form-data" class="text-right mt-3">
                <input name="iddata" type="hidden" value="<?php echo $data['id']; ?>" />
                <input name="pic" type="hidden" value="<?php echo $data['picture']; ?>" />
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>المصطلح الإنجليزي:</label>
                        <input name="txt_term" type="text" class="form-control" value="<?php echo $data['term']; ?>" required />
                    </div>
                    <div class="form-group col-md-6">
                        <label>الترجمة العربية:</label>
                        <input name="trans" type="text" class="form-control" value="<?php echo $data['trans']; ?>" required />
                    </div>
                </div>

                <div class="form-group">
                    <label>SMILES Code (للمركبات الكيميائية):</label>
                    <input name="smiles_code" type="text" class="form-control" value="<?php echo $data['smiles_code']; ?>" />
                </div>
                
                <div class="form-group">
                    <label>التعريف / الوصف العلمي:</label>
                    <textarea name="TextArea1" class="form-control" rows="5" required><?php echo $data['defe']; ?></textarea>
                </div>
                
                <div class="form-row align-items-center bg-white p-3 rounded border">
                    <div class="form-group col-md-4 text-center">
                        <label>المعاينة الحالية:</label><br>
                        <img src="<?php echo $display_img; ?>" width="120" class="img-thumbnail">
                    </div>
                    <div class="form-group col-md-8">
                        <label>تحديث الصورة يدوياً (اختياري):</label>
                        <input name="filedata" type="file" class="form-control-file border p-1 rounded">
                        <small class="text-muted">ملاحظة: إذا كان كود SMILES موجوداً، فسيتم استخدامه للعرض تلقائياً.</small>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button name="Submit2" class="btn btn-success btn-lg btn-block" type="submit">💾 حفظ التعديلات النهائية</button>
                    <a href="edit_term.php" class="btn btn-secondary btn-block">إلغاء وإغلاق النموذج</a>
                </div>
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
