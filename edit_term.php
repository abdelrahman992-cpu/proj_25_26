<?php
ob_start();
session_start();
include_once("api.php"); 
include_once("conn.php");
include_once("validation.php"); 

// --- 1. معالج التعديل السريع (الحالة) ---
// --- 1. معالج التعديل السريع (الحالة) ---
if (isset($_GET['status_id']) && isset($_GET['new_status'])) {
    $id = intval($_GET['status_id']);
    $new_status = $_GET['new_status'];
    
    // استدعاء الـ API مع طباعة النتيجة للتأكد

    
    // تنفيذ التعديل
// بدلاً من استخدام السلسلة النصية "{term_id}"
// سنقوم بدمج الـ ID مباشرة في الرابط
$result = callAPI("PUT", "/terms/update", ['id' => $id, 'status' => $_GET['new_status']]);

// أضف هذا السطر فقط للتجربة:
// var_dump($result); exit;
    
    // إيقاف أي مخرجات سابقة للتأكد من نظافة الصفحة
    ob_clean(); 

    // بدلاً من الاعتماد على جافا سكريبت فقط، سنستخدم التوجيه المباشر
    header("Location: edit_term.php?msg=success");
    exit;
}
// --- 2. معالج التعديل الكامل ---
if (isset($_POST['Submit2'])) {
    $id = intval($_POST['iddata']);
    $data = [
        'term'        => $_POST['txt_term'],
        'trans'       => $_POST['trans'],
        'defe'        => $_POST['TextArea1'],
        'smiles_code' => $_POST['smiles_code']
    ];

$result = callAPI("PUT", "/terms/update/" . $id, $data);

// أضف هذا السطر فقط للتجربة:
// var_dump($result); exit;
    
    if (isset($result['status']) && $result['status'] == 'success') {
        echo "<script>alert('تم التعديل بنجاح'); window.location.href='edit_term.php';</script>";
    } else {
        echo "<script>alert('فشل التعديل: " . ($result['detail'] ?? "خطأ غير معروف") . "');</script>";
    }
    exit;
}

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
        $terms = callAPI("GET", "/terms/");

// التحقق مما إذا كان الرد مصفوفة صحيحة
if (!is_array($terms)) {
    $terms = [];
    $num = 0;
} else {
    $num = count($terms);
}
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
                   <th>التسلسل الجيني</th>
                <th>التسلسل الفاستا</th>
                   <th>فئة المرض</th>
                <th>نسبة الثقة</th>
                <th>التاريخ</th>
            </tr>
        </thead>
 <tbody>
            <?php foreach($terms as $row) { 
                $smiles = $row['smiles_code'] ?? 'N/A';
                $status_class = ($row['status'] == 'approved') ? 'badge-approved' : 'badge-pending';
                
                $display_img = (!empty($smiles) && $smiles != 'N/A') 
                    ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" 
                    : $row['picture'];
            ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><b><?php echo htmlspecialchars($row['term']); ?></b></td>
                <td><?php echo htmlspecialchars($row['trans']); ?></td>
                <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                <td><img src="<?php echo $display_img; ?>" class="molecule-img" width="50" height="50"></td>
                <td>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="edit_term.php?id=<?php echo $row['id']; ?>#edit-form" class="btn btn-warning btn-sm">📝 تعديل</a>
                        
                        <?php if($row['status'] == 'approved'): ?>
                            <a href="edit_term.php?status_id=<?php echo $row['id']; ?>&new_status=pending" class="btn btn-outline-secondary btn-sm">⏳ تعطيل</a>
                        <?php else: ?>
                            <a href="edit_term.php?status_id=<?php echo $row['id']; ?>&new_status=approved" class="btn btn-outline-success btn-sm">✅ تفعيل</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['accession_id'] ?? 'N/A'); ?></td>
                <td>
                    <?php 
                    // إظهار حجم التسلسل بدلاً من النص الخام لكي لا يفسد شكل الجدول
                    echo (!empty($row['fasta_seq']) && $row['fasta_seq'] != 'N/A') 
                        ? "موجود (" . strlen($row['fasta_seq']) . " bp)" 
                        : 'N/A'; 
                    ?>
                </td>
                <td><?php echo htmlspecialchars($row['disease_class'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['confidence_score'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['created_at'] ?? 'N/A'); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table> </div>
<?php
        // عرض نموذج التعديل فقط عند اختيار ID
    if(isset($_GET['id'])) {
            $id_to_edit = intval($_GET['id']);
            
            $data = callAPI("GET", "/terms/" . $id_to_edit);

            if($data && !isset($data['error'])) {
                $smiles = $data['smiles_code'] ?? 'N/A';
                
                // تم تصحيح $data['filedata'] إلى $data['picture']
                $edit_display_img = (!empty($smiles) && $smiles != 'N/A') 
                    ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" 
                    : ($data['picture'] ?? 'pic/dna_drug_logo.png');
        ?>
        <div id="edit-form" class="card bg-light text-dark p-4 mt-5 mb-5 shadow mx-auto" style="max-width: 800px;">
            <h2 class="text-primary border-bottom pb-2">📝 تعديل بيانات المصطلح</h2>
            <form method="post" enctype="multipart/form-data" class="text-right mt-3">
                <input name="iddata" type="hidden" value="<?php echo htmlspecialchars($data['id'] ?? ''); ?>" />
                <input name="pic" type="hidden" value="<?php echo htmlspecialchars($data['picture'] ?? ''); ?>" />
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>المصطلح الإنجليزي:</label>
                        <input name="txt_term" type="text" class="form-control" value="<?php echo htmlspecialchars($data['term'] ?? ''); ?>" required />
                    </div>
                    <div class="form-group col-md-6">
                        <label>الترجمة العربية:</label>
                        <input name="trans" type="text" class="form-control" value="<?php echo htmlspecialchars($data['trans'] ?? ''); ?>" required />
                    </div>
                </div>

                <div class="form-group">
                    <label>SMILES Code (للمركبات الكيميائية):</label>
                    <input name="smiles_code" type="text" class="form-control" value="<?php echo htmlspecialchars($data['smiles_code'] ?? ''); ?>" />
                </div>
                
                <div class="form-group">
                    <label>التعريف / الوصف العلمي:</label>
                    <textarea name="TextArea1" class="form-control" rows="5" required><?php echo htmlspecialchars($data['defe'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row align-items-center bg-white p-3 rounded border">
                    <div class="form-group col-md-4 text-center">
                        <label>المعاينة الحالية:</label><br>
                        <img src="<?php echo htmlspecialchars($edit_display_img ?? ''); ?>" width="120" class="img-thumbnail">
                    </div>
                    <div class="form-group col-md-8">
                        <label>تحديث الصورة يدوياً (اختياري):</label>
                        <input name="filedata" type="file" class="form-control-file border p-1 rounded">
                    </div>
                </div>
                
                <div class="mt-4">
                    <button name="Submit2" class="btn btn-success btn-lg btn-block" type="submit">💾 حفظ التعديلات النهائية</button>
                    <a href="edit_term.php" class="btn btn-secondary btn-block">إلغاء وإغلاق النموذج</a>
                </div>
            </form>
        </div>
                
                <div class="mt-4">
                    <button name="Submit2" class="btn btn-success btn-lg btn-block" type="submit">💾 حفظ التعديلات النهائية</button>
                    <a href="edit_term.php" class="btn btn-secondary btn-block">إلغاء وإغلاق النموذج</a>
                </div>
            </form>
        </div>
        <?php 
            } else {
                echo "<div class='alert alert-danger'>خطأ: لم يتم العثور على المصطلح أو تعذر الاتصال بالـ API</div>";
            }
        } 
        ?>
        
    <?php include('footer.php'); ?>
</body>
</html>
