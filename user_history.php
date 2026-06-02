<?php
session_start();
include("conn.php");
include("header.php");

if(empty($_SESSION['user_id'])){
    header("Location: signin.php");
    exit;
}

$my_id = $_SESSION['user_id'];
// جلب مصطلحاتي فقط
$query = mysqli_query($connect, "SELECT * FROM terms WHERE user_id = $my_id ORDER BY id DESC");
?>

<div class="container mt-4">
    <h2 class="text-right">📜 سجل المصطلحات الخاصة بي</h2>
    <table class="table table-bordered text-center mt-4">
        <thead class="thead-light">
            <tr>
                <th>المصطلح</th>
                <th>تاريخ التقديم</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($query)): ?>
            <tr>
                <td><?= htmlspecialchars($row['term']) ?></td>
                <td><?= $row['created_at'] ?? '---' ?></td>
                <td>
                    <?php 
                    if($row['status'] == 'approved') echo '<span class="badge badge-success">تم القبول ✅</span>';
                    elseif($row['status'] == 'pending') echo '<span class="badge badge-warning">قيد المراجعة ⏳</span>';
                    else echo '<span class="badge badge-danger">مرفوض ❌</span>';
                    ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php include("footer.php"); ?>
