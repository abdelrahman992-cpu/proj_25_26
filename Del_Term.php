<?php
include("conn.php");

/* 1. حماية الصفحة */
if(empty($_SESSION['username'])){
    header("Location: ask_to_sign_in.php");
    exit;
}

if($_SESSION['role']==="user"){
require_once("conn.php");
require_once("header.php");
    require_once("post.php");
 echo("أنت لست مسؤل");
    exit;
}

/* 2. معالجة الحذف */
if(isset($_GET['id'])){
    $id = intval($_GET['id']);
    // استخدام $connect كما في ملفاتك السابقة
    mysqli_query($connect, "DELETE FROM terms WHERE id=$id");

    header("Location: Del_Term.php?msg=deleted");
    exit;
}

include("header.php"); 
?>

<div class="container mt-4">
    <div class="text-center mb-5">
        <h1 class="display-4"> إدارة حذف المصطلحات</h1>
        <?php
        mysqli_query($connect, "SET NAMES 'utf8'");
        $sql = "SELECT * FROM terms ORDER BY id DESC";
        $query = mysqli_query($connect, $sql);
        $num = mysqli_num_rows($query);
        
        if(isset($_GET['msg'])) {
            echo "<div class='alert alert-danger'>✅ تم حذف المصطلح بنجاح من قاعدة البيانات.</div>";
        }
        ?>
        <h3 class="text-secondary">إجمالي المصطلحات الحالية: <span class="badge badge-danger"><?= $num ?></span></h3>
    </div>

    <table class="table table-dark table-hover text-center shadow-lg">
        <thead class="thead-light">
            <tr style="color: black;">
                <th>#</th>
                <th>المصطلح</th>
                <th>الترجمة</th>
                <th>صورة المركب</th>
                <th>SMILES Code</th>
                <th>التحكم</th>
            </tr>
        </thead>
        <tbody>

        <?php
        while($row = mysqli_fetch_assoc($query)){
            // منطق عرض الصورة الذكي (PubChem للمركبات و Local للمصطلحات)
            $smiles = $row['smiles_code'];
            $img_src = (!empty($smiles) && $smiles != 'N/A') 
                ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" 
                : $row['picture'];
        ?>
        <tr>
            <td class="align-middle"><?= $row['id'] ?></td>
            <td class="align-middle"><strong><?= htmlspecialchars($row['term']) ?></strong></td>
            <td class="align-middle"><?= htmlspecialchars($row['trans']) ?></td>
            <td class="align-middle">
                <img src="<?= $img_src ?>" class="img-thumbnail" width="80" style="background: white;">
            </td>
            <td class="align-middle">
                <small><code class="text-success"><?= $smiles ?: '---' ?></code></small>
            </td>
            <td class="align-middle">
                <a href="Del_Term.php?id=<?= $row['id'] ?>" 
                   class="btn btn-outline-danger btn-sm" 
                   onclick="return confirm('⚠️ هل أنت متأكد من حذف هذا المصطلح نهائياً؟');">
                 حذف
                </a>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<?php include("footer.php"); ?>
