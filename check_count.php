<?php
// استدعاء دالة callAPI التي قمت بإضافتها سابقاً في api.php
include_once("api.php"); 

$result = callAPI("GET", "/terms/count/");

if (isset($result['total'])) {
    echo $result['total'];
} else {
    // في حال فشل الاتصال، نرجع 0 أو القيمة السابقة لتجنب حدوث خطأ في الـ JS
    echo "0";
}
?>
