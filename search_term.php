<?php
require_once("conn.php");
require_once("header.php");

mysqli_set_charset($connect,"utf8");

if(!empty($_SESSION['user_id'])){
    include("post.php");
}
$q = $_GET['q'] ?? ''; 

// 2. تنظيف المدخلات (مهم جداً للحماية من ثغرات SQL Injection)
// لا يهم نوع النظام، هذه الدالة تعمل على كليهما
$q = htmlspecialchars($q); 

// 3. التحقق قبل الاستخدام
if (!empty($q)) {
    // كود الاستعلام الخاص بك هنا
    echo "جاري البحث عن: " . $q;
} else {
    // كود في حال عدم وجود بحث
    echo "الرجاء إدخال كلمة للبحث.";
}
// قم بتنظيف المتغير قبل وضعه في الاستعلام
$q_safe = mysqli_real_escape_string($connect, $q);

$sql = "SELECT term, trans, defe, picture, smiles_code FROM terms 
        WHERE status = 'approved' AND (term LIKE '%$q_safe%' OR trans LIKE '%$q_safe%') LIMIT 20";
$res = mysqli_query($connect, $sql);
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

    <div class="input-group mb-4" style="max-width: 600px; margin: auto;">
    <input type="text" id="liveSearch" class="form-control form-control-lg" 
           placeholder="🔍 ابحث عن أي مصطلح أو دواء..." onkeyup="liveFilter()">
</div>

<table class="table table-dark table-hover text-center" id="searchTable">
    <tbody id="resultBody">
        </tbody>
</table>

<script>
function liveFilter() {
    let input = document.getElementById('liveSearch').value;
    
    // إذا كان النص أقل من حرفين لا تبحث
    if (input.length < 1) {
        document.getElementById('resultBody').innerHTML = '';
        return;
    }

    // إرسال طلب للملف الجديد للبحث
    fetch('search_ajax.php?q=' + encodeURIComponent(input))
        .then(response => response.json())
        .then(data => {
            let html = '';
            data.forEach(row => {
                // منطق عرض الصورة
                let img = (row.smiles_code && row.smiles_code !== 'N/A') 
                    ? "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/smiles/" + encodeURIComponent(row.smiles_code) + "/PNG" 
                    : row.picture;

                html += `<tr>
                    <td><strong>${row.term}</strong></td>
                    <td>${row.trans}</td>
                    <td><small>${row.defe}</small></td>
                    <td><img src="${img}" width="80" class="molecule-preview"></td>
                    <td><div class='smiles-box'>${row.smiles_code || '---'}</div></td>
                </tr>`;
            });
            document.getElementById('resultBody').innerHTML = html;
        });
}
</script>


<?php include("footer.php"); ?>
</body>
</html>
