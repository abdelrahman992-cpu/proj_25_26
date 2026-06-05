<?php
require_once("conn.php");
$q = $_GET['q'] ?? '';
$q_safe = mysqli_real_escape_string($connect, $q);

$sql = "SELECT term, trans, defe, picture, smiles_code FROM terms 
        WHERE status = 'approved' AND (term LIKE '%$q_safe%' OR trans LIKE '%$q_safe%') LIMIT 20";
$res = mysqli_query($connect, $sql);

$results = [];
while ($row = mysqli_fetch_assoc($res)) {
    $results[] = $row;
}
echo json_encode($results); // إرجاع البيانات بصيغة JSON للجافاسكريبت
?>
