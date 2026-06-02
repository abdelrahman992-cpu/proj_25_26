<?php
require_once("conn.php");
require_once("header.php");

mysqli_set_charset($connect,"utf8");

if(!empty($_SESSION['user_id'])){
    include("post.php");
}

?>


<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>بحث عن مصطلح</title>
    <style>
        .molecule-preview { background: white; border-radius: 5px; padding: 2px; border: 1px solid #666; }
        .smiles-box { background: #333; color: #0f0; padding: 4px; border-radius: 4px; font-family: monospace; font-size: 0.85em; }
    </style>
</head>

<body>

<div class="container mt-4">
    <h2 class="text-center mb-4">🔍 البحث عن المصطلحات والأدوية</h2>

    <form method="post" action="" class="mb-4">
        <div class="input-group mb-3" style="max-width: 500px; margin: auto;">
            <input name="txt_search" type="text" class="form-control" placeholder="اكتب اسم الدواء أو المصطلح هنا..." required>
            <div class="input-group-append">
                <input name="submit1" class="btn btn-primary" type="submit" value="بحث">
            </div>
        </div>
    </form>

    <table class="table table-dark table-hover text-center">
        <thead>
            <tr>
                <th>المصطلح</th>
                <th>الترجمة</th>
                <th>التعريف</th>
                <th>صورة المركب</th>
                <th>SMILES Code</th>
            </tr>
        </thead>
        <tbody>

    <?php
    if(isset($_POST['submit1']) && !empty($_POST['txt_search'])){
        $txt_search = $_POST['txt_search'];
        $search = "%$txt_search%";

        $stmt = $connect->prepare("SELECT * FROM terms WHERE status = 'approved' AND term LIKE ? ORDER BY term ASC");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0){
            while($row = $result->fetch_assoc()){
                $term = htmlspecialchars($row['term']);
                $trans = htmlspecialchars($row['trans']);
                $defe = htmlspecialchars($row['defe']);
                $smiles = htmlspecialchars($row['smiles_code']);
                
                // منطق جلب الصورة من PubChem لو الكود موجود
                $img_src = (!empty($smiles) && $smiles != 'N/A') 
                    ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" 
                    : $row['picture'];

                echo "
                <tr>
                    <td class='align-middle'><strong>$term</strong></td>
                    <td class='align-middle'>$trans</td>
                    <td class='align-middle'><small>$defe</small></td>
                    <td class='align-middle'>
                        <img src='$img_src' class='molecule-preview' width='100' height='100' alt='Chemical Structure'>
                    </td>
                    <td class='align-middle'>
                        <div class='smiles-box'>" . ($smiles ?: 'لا يوجد') . "</div>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='5' class='text-warning'>لا توجد نتائج تطابق بحثك.</td></tr>";
        }
        $stmt->close();
    }
    ?>
        </tbody>
    </table>
</div>

<?php include("footer.php"); ?>
</body>
</html>
