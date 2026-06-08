<?php
session_start();
include("conn.php");
include("header.php");

if(empty($_SESSION['user_id'])){
    header("Location: signin.php");
    exit;
}

$my_id = $_SESSION['user_id'];

// استدعاء الـ API بدلاً من SQL
// نفترض أن دالة callAPI تقوم بإرسال طلب GET
$terms = callAPI("GET", "/terms/user/" . $my_id);
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
            <?php 
            // التحقق من أن النتيجة مصفوفة وليست خطأ
            if(is_array($terms) && !isset($terms['detail'])): 
                foreach($terms as $row): 
            ?>
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
            <?php 
                endforeach; 
            else:
                echo "<tr><td colspan='3'>لا توجد مصطلحات أو حدث خطأ في الاتصال.</td></tr>";
            endif; 
            ?>
        </tbody>
    </table>
</div>
<?php include("footer.php"); ?>
