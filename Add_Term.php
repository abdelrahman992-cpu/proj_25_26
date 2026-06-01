<?php
include("conn.php");
if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}
include("header.php");
include("validation.php");

// 1. دالة الترجمة البرمجية (تستخدم Google Translate API المجاني)
function translate_to_arabic($text) {
    if (empty($text)) return "";
    // تقسيم النص الطويل لأن API الترجمة له حد أقصى في الطلب الواحد
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=ar&dt=t&q=" . urlencode($text);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    $translated_text = "";
    if (isset($result[0])) {
        foreach ($result[0] as $sentence) {
            $translated_text .= $sentence[0];
        }
    }
    return $translated_text;
}

// 2. دالة جلب البيانات من NCBI
function fetch_from_ncbi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$message = "";

// --- قسم استيراد الهيموفيليا ---
if (isset($_POST['import_hemophilia'])) {
    $query = "Hemophilia Gene Therapy";
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=".urlencode($query)."&retmax=3&retmode=json";
    
    $search_res = fetch_from_ncbi($api_search);
    $search_data = json_decode($search_res, true);
    $id_list = $search_data['esearchresult']['idlist'] ?? [];
    
    $count = 0;
    foreach ($id_list as $id) {
        $fetch_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&id=$id&retmode=xml";
        $fetch_res = fetch_from_ncbi($fetch_url);
        $xml = simplexml_load_string($fetch_res);
        
        if ($xml) {
            $title_en = (string)$xml->PubmedArticle->MedlineCitation->Article->ArticleTitle;
            $abstract_parts = [];
            if (isset($xml->PubmedArticle->MedlineCitation->Article->Abstract->AbstractText)) {
                foreach ($xml->PubmedArticle->MedlineCitation->Article->Abstract->AbstractText as $part) {
                    $abstract_parts[] = (string)$part;
                }
            }
            $abstract_en = implode(" ", $abstract_parts);

            // عملية الترجمة قبل الحفظ
            $title_ar = translate_to_arabic($title_en);
            $abstract_ar = translate_to_arabic($abstract_en);

            $clean_title = mysqli_real_escape_string($connect, $title_ar);
            $clean_abstract = mysqli_real_escape_string($connect, $abstract_ar);

            $check = mysqli_query($connect, "SELECT id FROM terms WHERE term = 'PMID: $id'");
            if (mysqli_num_rows($check) == 0) {
                $sql = "INSERT INTO terms (term, trans, defe, picture, status) VALUES ('PMID: $id', '$clean_title', '$clean_abstract', 'pic/ncbi_logo.png', 'approved')";
                if (mysqli_query($connect, $sql)) $count++;
            }
        }
    }
    $message = "<div class='alert alert-success'>تم استيراد وترجمة $count أبحاث عن الهيموفيليا بنجاح!</div>";
}

// --- قسم استيراد الجينات العامة ---
if (isset($_POST['import_genes'])) {
    $query = "human[organism] AND protein_coding[properties]";
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gene&term=".urlencode($query)."&retmax=3&retmode=json";
    
    $search_res = fetch_from_ncbi($api_search);
    $search_data = json_decode($search_res, true);
    $id_list = $search_data['esearchresult']['idlist'] ?? [];
    
    $count = 0;
    foreach ($id_list as $id) {
        $summary_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=gene&id=$id&retmode=json";
        $summary_res = fetch_from_ncbi($summary_url);
        $summary_data = json_decode($summary_res, true);
        
        if (isset($summary_data['result'][$id])) {
            $gene_name = $summary_data['result'][$id]['name'];
            $gene_desc_en = $summary_data['result'][$id]['description'];
            $gene_summary_en = $summary_data['result'][$id]['summary'] ?? "No summary available";

            // ترجمة بيانات الجين
            $desc_ar = translate_to_arabic($gene_desc_en);
            $summary_ar = translate_to_arabic($gene_summary_en);

            $clean_desc = mysqli_real_escape_string($connect, $desc_ar);
            $clean_summary = mysqli_real_escape_string($connect, $summary_ar);

            $check = mysqli_query($connect, "SELECT id FROM terms WHERE term = 'Gene: $gene_name'");
            if (mysqli_num_rows($check) == 0) {
                mysqli_query($connect, "INSERT INTO terms (term, trans, defe, picture, status) VALUES ('Gene: $gene_name', '$clean_desc', '$clean_summary', 'pic/ncbi_logo.png', 'approved')");
                $count++;
            }
        }
    }
    $message = "<div class='alert alert-info'>تم استيراد وترجمة $count جينات بشرية بنجاح!</div>";
}

// إضافة يدوية
if (isset($_POST['Submit1'])) {
    $term = sanStr($_POST['txt_term']);
    $trans = sanStr($_POST['trans']);
    $defe = sanStr($_POST['TextArea1']);
    $picture = "pic/ncbi_logo.png";
    if (!empty($_FILES['File1']['name'])) {
        $file = time() . "_" . $_FILES['File1']['name'];
        move_uploaded_file($_FILES['File1']['tmp_name'], 'pic/' . $file);
        $picture = "pic/" . $file;
    }
    mysqli_query($connect, "INSERT INTO terms (term, trans, defe, picture, status) VALUES ('$term', '$trans', '$defe', '$picture', 'approved')");
    $message = "<div class='alert alert-success'>تمت الإضافة يدوياً بنجاح.</div>";
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <title>إدارة القاموس المترجم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; margin-bottom: 30px; }
        .btn-import { font-weight: bold; padding: 15px; transition: 0.3s; }
        .btn-import:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="container py-5">

    <?php echo $message; ?>

    <div class="text-center mb-5">
        <h1 class="display-4">🧬 القاموس البيولوجي الذكي</h1>
        <p class="lead text-muted">استيراد، ترجمة، وتصنيف آلي من NCBI</p>
    </div>

    <div class="card bg-white">
        <div class="card-header bg-dark text-white text-center">⚙️ أدوات الاستيراد والترجمة الآلية</div>
        <div class="card-body">
            <form method="post">
                <div class="row text-center">
                    <div class="col-md-6 mb-3">
                        <div class="p-3 border rounded shadow-sm">
                            <h5>أبحاث الهيموفيليا</h5>
                            <p class="small text-muted">يجلب الملخصات الكاملة ويترجمها للعربية</p>
                            <button type="submit" name="import_hemophilia" class="btn btn-danger btn-block btn-import">🩸 استيراد أبحاث الهيموفيليا</button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="p-3 border rounded shadow-sm">
                            <h5>الجينات البشرية</h5>
                            <p class="small text-muted">يجلب توصيف الجينات ويترجمها للعربية</p>
                            <button type="submit" name="import_genes" class="btn btn-info btn-block btn-import">🧬 استيراد جينات عامة</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-success text-white text-center">➕ إضافة مصطلح يدوياً</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>المصطلح (English):</label>
                        <input name="txt_term" class="form-control" type="text" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>الترجمة العربية:</label>
                        <input name="trans" class="form-control" type="text" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>الشرح/التعريف:</label>
                    <textarea name="TextArea1" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>صورة تعبيرية:</label>
                    <input name="File1" type="file" class="form-control-file">
                </div>
                <button name="Submit1" type="submit" class="btn btn-success btn-block">حفظ المصطلح</button>
            </form>
        </div>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
