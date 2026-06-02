<?php
session_start();
include("conn.php");
include("header.php");

// 1. حماية الصفحة
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    echo "<div class='container mt-5'><h3>❌ عذراً، لا تملك صلاحية الوصول لهذه الصفحة.</h3></div>";
    include("footer.php");
    exit;
}

// 2. معالجة الموافقة (Update Status)
if(isset($_GET['approve_id'])){
    $id = intval($_GET['approve_id']);
    $update_stmt = mysqli_prepare($connect, "UPDATE terms SET status='approved' WHERE id=?");
    mysqli_stmt_bind_param($update_stmt, "i", $id);
    mysqli_stmt_execute($update_stmt);
    echo "<div class='alert alert-success text-center'>✅ تم قبول المصطلح بنجاح!</div>";
}

// 3. معالجة الرفض (Delete Term)
if(isset($_GET['reject_id'])){
    $id = intval($_GET['reject_id']);
    // نقوم بحذف المصطلح لأنه مرفوض

    echo "<div class='alert alert-danger text-center'> تم رفض وحذف المصطلح بنجاح.</div>";
    mysqli_query($connect, "UPDATE terms SET status='rejected' WHERE id=$id");
   header("Location: approve_terms.php?msg=rejected");
}

// 4. جلب المصطلحات المعلقة (status = 'pending')
$query = mysqli_query($connect, "SELECT * FROM terms WHERE status='pending'");
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">⚖️ مراجعة المصطلحات الجديدة</h2>
    <table class="table table-dark table-striped table-hover shadow text-center">
     
               <thead class="thead-light">
                <tr>
                    <th>المسلسل</th>
                    <th>المصطلح</th>
                    <th>الترجمة</th>
                    <th>كود SMILES</th>
                    <th>صورة المركب</th>
                    <th>الخصائص</th>
                </tr>
            </thead>
        
        <?php if(mysqli_num_rows($query) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($query)): 
                // عرض صورة المركب لو وجد SMILES
                $smiles = $row['smiles_code'];
                $img_src = (!empty($smiles) && $smiles != 'N/A') 
                    ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" 
                    : $row['picture'];
            ?>
            <tr>
                <td class="align-middle"><strong><?php echo htmlspecialchars($row['term']); ?></strong></td>
                <td class="align-middle"><?php echo htmlspecialchars($row['trans']); ?></td>
                <td class="align-middle"><small><?php echo htmlspecialchars($row['defe']); ?></small></td>
                <td class="align-middle">
                    <img src="<?= $img_src ?>" width="60" class="bg-white rounded">
                </td>
                <td class="align-middle">
                    <a href="approve_terms.php?approve_id=<?php echo $row['id']; ?>" 
                       class="btn btn-success btn-sm mb-1">✅ موافقة</a>
                    
                    <br>

                    <a href="approve_terms.php?reject_id=<?php echo $row['id']; ?>" 
                       class="btn btn-danger btn-sm" 
                       onclick="return confirm('هل أنت متأكد من رفض وحذف هذا المصطلح؟');">❌ رفض</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="p-5 text-muted">لا توجد مصطلحات جديدة بانتظار المراجعة حالياً.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php include("footer.php"); ?>
