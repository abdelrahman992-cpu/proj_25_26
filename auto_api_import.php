<?php
// منع المهلة الزمنية لأن الجلب قد يستغرق وقتاً
set_time_limit(0); 

function populateNCBIData($connect) {
    // 1. تحديد كلمة البحث لجلب المصطلحات (مثلاً أحدث أبحاث الجينوم)
    $search_query = "genomics[Term]"; 
    $max_results = 20; // يمكنك زيادة هذا الرقم لجلب مئات المصطلحات
    
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gds&term=".urlencode($search_query)."&retmax=$max_results&retmode=json";
    
    $search_res = @file_get_contents($api_search);
    if (!$search_res) return; // فشل الاتصال

    $search_data = json_decode($search_res, true);
    $id_list = $search_data['esearchresult']['idlist'] ?? [];

    foreach ($id_list as $id) {
        // التحقق هل المصطلح موجود مسبقاً لتجنب التكرار في كل مرة يفتح فيها الموقع
        $check_stmt = $connect->prepare("SELECT id FROM terms WHERE term LIKE ?");
        $temp_id = "%$id%";
        $check_stmt->bind_param("s", $temp_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res->num_rows == 0) {
            // جلب تفاصيل كل دراسة
            $summary_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=gds&id=$id&retmode=json";
            $summary_res = @file_get_contents($summary_url);
            $summary_data = json_decode($summary_res, true);

            if (isset($summary_data['result'][$id])) {
                $accession = $summary_data['result'][$id]['accession'];
                $title = $summary_data['result'][$id]['title'];
                $summary = $summary_data['result'][$id]['summary'];
                $pic = "pic/ncbi_logo.png";

                // إدخال البيانات مباشرة
                $insert_stmt = $connect->prepare("INSERT INTO terms (term, trans, defe, picture, status) VALUES (?, ?, ?, ?, 'approved')");
                $insert_stmt->bind_param("ssss", $accession, $title, $summary, $pic);
                $insert_stmt->execute();
            }
        }
    }
}

// تشغيل الدالة تلقائياً
populateNCBIData($connect);
?>
