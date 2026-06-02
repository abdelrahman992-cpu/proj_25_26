<?php
ob_start();
session_start();
include_once("conn.php");
include_once("validation.php");

// 1. حماية الصفحة
if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}

if ($_SESSION['role'] === "user") {
    include("header.php");
    echo "<div class='container mt-5 text-center'><h3 class='alert alert-danger'>عذراً، أنت لا تملك صلاحيات المسؤول.</h3></div>";
    include("footer.php");
    exit;
}

$db = $connect ?? $conn;

// 2. معالجة الحذف
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "DELETE FROM terms WHERE id = ?";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('تم الحذف!'); window.location.href='Del_Term.php';</script>";
        exit;
    }
}

include("header.php"); 
?>

<div class="container-fluid mt-4">
    <div class="text-center mb-4">
        <h1 class="text-warning"> إدارة حذف المصطلحات</h1>
        <p>ابحث عن المصطلح ثم اضغط حذف نهائي</p>
    </div>

    <div class="row justify-content-center mb-4">
        <div class="col-md-6">
            <input type="text" id="searchInput" class="form-control" placeholder="🔍 ابحث عن مصطلح (تصفية فورية)..." onkeyup="filterTable()">
        </div>
    </div>

    <div class="table-responsive">
        <table id="termsTable" class="table table-dark table-hover text-center shadow-lg">
            <thead class="thead-light text-dark">
                <tr>
                    <th>#</th>
                    <th>المصطلح</th>
                    <th>الترجمة العربية</th>
                    <th>العرض</th>
                    <th>SMILES</th>
                    <th>القرار</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = mysqli_query($db, "SELECT * FROM terms ORDER BY id DESC");
                while ($row = mysqli_fetch_assoc($query)) {
                    $smiles = $row['smiles_code'];
                    $img = (!empty($smiles) && $smiles != 'N/A') ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" . urlencode($smiles) . "/PNG" : $row['picture'];
                ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['term']) ?></strong></td>
                    <td><?= htmlspecialchars($row['trans']) ?></td>
                    <td><img src="<?= $img ?>" width="50" class="molecule-img"></td>
                    <td><small><code><?= $smiles ?: '---' ?></code></small></td>
                    <td>
                        <a href="Del_Term.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" 
                           onclick="return confirm('تأكيد الحذف؟');">حذف</a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTable() {
    var input = document.getElementById("searchInput");
    var filter = input.value.toUpperCase();
    var table = document.getElementById("termsTable");
    var tr = table.getElementsByTagName("tr");

    for (var i = 1; i < tr.length; i++) {
        var rowText = tr[i].textContent || tr[i].innerText;
        tr[i].style.display = (rowText.toUpperCase().indexOf(filter) > -1) ? "" : "none";
    }
}
</script>

<?php include("footer.php"); ?>
