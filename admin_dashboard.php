<?php
session_start();
include("conn.php");
include("header.php");


// حماية الصفحة: التأكد من أنه أدمن فقط
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    die("❌ ليس لديك صلاحية الوصول لهذه الصفحة.");
}

// تنفيذ الموافقة (Update)
if(isset($_GET['approve_id'])){
    $id = intval($_GET['approve_id']);
    mysqli_query($connect, "UPDATE terms SET status='approved' WHERE id=$id");
    echo "<p style='color:green;'>✅ تم قبول المصطلح بنجاح!</p>";
}

// عرض المصطلحات المعلقة
$query = mysqli_query($connect, "SELECT * FROM terms WHERE status='pending'");
?>

<h2>قائمة المصطلحات بانتظار الموافقة</h2>
<table class="table table-bordered">
    <tr>
        <th>المصطلح</th>
        <th>الترجمة</th>
        <th>الإجراء</th>
    </tr>
    <?php while($row = mysqli_fetch_assoc($query)): ?>
    <tr>
        <td><?php echo $row['term']; ?></td>
        <td><?php echo $row['trans']; ?></td>
        <td>
            <a href="admin_dashboard.php?approve_id=<?php echo $row['id']; ?>" class="btn btn-success">موافقة</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php include("footer.php"); ?>
