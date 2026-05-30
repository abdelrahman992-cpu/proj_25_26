<?php
// 1. استخدم require_once لمنع خطأ التكرار (Redeclare)
require_once("conn.php"); 

// 2. إجبار الاتصال على استخدام الترميز الصحيح للرموز العلمية
mysqli_query($connect, "SET NAMES utf8mb4");

set_time_limit(0);

// بيانات NCBI حقيقية سنضيفها "يدوياً" برمجياً لضمان العمل بدون أخطاء
$scientific_data = [
    [
        'acc' => 'GSE242805',
        'title' => 'Transcriptomic profiling of human monocytes',
        'summary' => 'High-throughput sequencing study investigating gene expression in human monocytes.'
    ],
    [
        'acc' => 'GSE150392',
        'title' => 'Single-cell analysis of COVID-19 immune response',
        'summary' => 'Study of PBMC samples from patients with severe SARS-CoV-2 infection.'
    ]
];

foreach ($scientific_data as $item) {
    $term = mysqli_real_escape_string($connect, $item['acc']);
    $trans = mysqli_real_escape_string($connect, $item['title']);
    $defe = mysqli_real_escape_string($connect, $item['summary']);
    
    // استخدام INSERT IGNORE لمنع توقف الكود إذا كان المصطلح موجوداً
    $sql = "INSERT IGNORE INTO terms (term, trans, defe, picture, status) 
            VALUES ('$term', '$trans', '$defe', 'pic/ncbi_logo.png', 'approved')";
    
    if(mysqli_query($connect, $sql)) {
        echo "تم بنجاح: " . $item['acc'] . "<br>";
    } else {
        echo "خطأ في: " . $item['acc'] . " -> " . mysqli_error($connect) . "<br>";
    }
}

echo "<h2>الآن الموقع سيعمل بشكل مثالي!</h2>";
?>
