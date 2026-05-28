<?php
include("conn.php");

/* حماية الصفحة */
if(empty($_SESSION['username'])){
    header("Location: ask_to_sign_in.php");
    exit;
}

/* حذف المصطلح */
if(isset($_GET['id'])){
    $id = intval($_GET['id']);
    mysqli_query($connect,"DELETE FROM terms WHERE id=$id");

    header("Location: Del_Term.php");
    exit;
}
?>

<?php include("header.php"); ?>

<h1>حذف مصطلح</h1>

<?php
mysqli_query($connect,"SET NAMES 'utf8'");

$sql="SELECT * FROM terms";
$query=mysqli_query($connect,$sql);
$num=mysqli_num_rows($query);

echo "<h1>عدد المصطلحات $num</h1>";
?>

<table class="table table-dark">
<tr>
<td>المسلسل</td>
<td>المصطلح</td>
<td>الترجمة</td>
<td>التعريف</td>
<td>الصورة</td>
<td>الخصائص</td>
</tr>

<?php
while($row=mysqli_fetch_assoc($query)){
?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['term'] ?></td>
<td><?= $row['trans'] ?></td>
<td><?= $row['defe'] ?></td>
<td><img src="<?= $row['picture'] ?>" width="80"></td>
<td><a href="Del_Term.php?id=<?= $row['id'] ?>">حذف</a></td>
</tr>
<?php } ?>

</table>

<?php include("footer.php"); ?>
