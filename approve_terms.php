<?php
session_start();
include("conn.php");
include("header.php");

// 1. حماية الصفحة: التأكد من أن المستخدم أدمن فقط
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    echo "<div class='container mt-5'><h3>❌ عذراً، لا تملك صلاحية الوصول لهذه الصفحة.</h3></div>";
    include("footer.php");
    exit;
}

// 2. معالجة الموافقة عند ضغط زر "موافقة"
if(isset($_GET['approve_id'])){
    $id = intval($_GET['approve_id']);
    $update_stmt = mysqli_prepare($connect, "UPDATE terms SET status='approved' WHERE id=?");
    mysqli_stmt_bind_param($update_stmt, "i", $id);
    mysqli_stmt_execute($update_stmt);
    
    echo "<div class='alert alert-success'>✅ تم قبول المصطلح بنجاح!</div>";
}

// 3. جلب المصطلحات المعلقة (status = 'pending')
$query = mysqli_query($connect, "SELECT * FROM terms WHERE status='pending'");
?>

<div class="container mt-4">
    <h2>المصطلحات بانتظار الموافقة</h2>
    <table class="table table-dark table-striped mt-3">
        <tr>
            <th>المصطلح</th>
            <th>الترجمة</th>
            <th>التعريف</th>
            <th>الإجراء</th>
        </tr>
        
        <?php if(mysqli_num_rows($query) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($query)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['term']); ?></td>
                <td><?php echo htmlspecialchars($row['trans']); ?></td>
                <td><?php echo htmlspecialchars($row['defe']); ?></td>
                <td>
                    <a href="approve_terms.php?approve_id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">موافقة</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center">لا توجد مصطلحات جديدة بانتظار المراجعة حالياً.</td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php include("footer.php"); ?>
