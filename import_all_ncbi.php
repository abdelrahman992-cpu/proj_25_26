<?php
require_once("conn.php"); // تأكد أن conn.php نظيف ولا يحتوي على أي أخطاء
mysqli_query($connect, "SET NAMES utf8mb4");

set_time_limit(0); // منع توقف السكربت مهما طال وقت الجلب

echo "بدء عملية الاستيراد الضخم... قد تستغرق دقائق...\n";

// يمكنك تغيير الكلمة لجلب تخصصات مختلفة (genomics, bioinformatics, proteomics)
$search_query = "genomics"; 
$max_results = 50; 

$api_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gds&term=".urlencode($search_query)."&retmax=$max_results&retmode=json";

$data = json_decode(file_get_contents($api_url), true);
$ids = $data['esearchresult']['idlist'] ?? [];

$success_count = 0;
foreach ($ids as $id) {
    $summary_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=gds&id=$id&retmode=json";
    $item_res = json_decode(file_get_contents($summary_url), true);
    
    if (isset($item_res['result'][$id])) {
        $acc = $item_res['result'][$id]['accession'];
        $title = mysqli_real_escape_string($connect, $item_res['result'][$id]['title']);
        $summary = mysqli_real_escape_string($connect, $item_res['result'][$id]['summary']);
        
        $sql = "INSERT IGNORE INTO terms (term, trans, defe, picture, status) 
                VALUES ('$acc', '$title', '$summary', 'pic/ncbi_logo.png', 'approved')";
        
        if (mysqli_query($connect, $sql)) {
            $success_count++;
            echo "[$success_count] تم استيراد: $acc \n";
        }
    }
}

echo "تم بنجاح استيراد $success_count مصطلح علمي لمشروعك!\n";
?>
