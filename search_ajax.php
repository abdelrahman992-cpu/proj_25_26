<?php
include("conn.php");
$q = mysqli_real_escape_string($connect, $_GET['q']);
// جلب كافة الحقول التي يحتاجها الجدول
$sql = "SELECT term, trans, defe, picture, smiles_code FROM terms 
        WHERE status = 'approved' AND (term LIKE '%$q%' OR trans LIKE '%$q%') LIMIT 20";
$res = mysqli_query($connect, $sql);

$results = [];
while ($row = mysqli_fetch_assoc($res)) {
    $results[] = $row;
}
echo json_encode($results);
?>
