<?php
include("conn.php");
// زيادة وقت تنفيذ السكربت لضمان اكتمال الترجمة (دقيقتان)
set_time_limit(120);

if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}
include("header.php");
include("validation.php");

// 1. دالة الترجمة البرمجية من الإنجليزية للعربية
function translate_to_arabic($text) {
    if (empty($text)) return "";
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

// 2. دالة جلب البيانات عبر cURL
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

// --- أولاً: استيراد أبحاث الهيموفيليا ---
if (isset($_POST['import_hemophilia'])) {
    $query = "Hemophilia Gene Therapy";
    $random_start = rand(0, 50); // تخطي عدد عشوائي من النتائج لجلب أبحاث جديدة
    
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=".urlencode($query)."&retmax=3&retstart=$random_start&retmode=json";
    
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

            // ترجمة البيانات
            $title_ar = translate_to_arabic($title_en);
            $abstract_ar = translate_to_arabic($abstract_en);

            $clean_title = mysqli_real_escape_string($connect, $title_ar);
            $clean_abstract = mysqli_real_escape_string($connect, $abstract_ar);

            // منع التكرار بناءً على الـ ID
            $check = mysqli_query($connect, "SELECT id FROM terms WHERE term = 'PMID: $id'");
            if (mysqli_num_rows($check) == 0) {
                $sql = "INSERT INTO terms (term, trans, defe, picture, status) VALUES ('PMID: $id', '$clean_title', '$clean_abstract', 'pic/ncbi_logo.png', 'approved')";
                if (mysqli_query($connect, $sql)) $count++;
            }
        }
    }
    $message = ($count > 0) ? "<div class='alert alert-success'>تم استيراد وترجمة $count أبحاث هيموفيليا جديدة!</div>" : "<div class='alert alert-warning'>لم يتم العثور على أبحاث جديدة في هذه الصفحة، جرب الضغط مرة أخرى.</div>";
}

// --- ثانياً: استيراد جينات عشوائية ---
if (isset($_POST['import_genes'])) {
    // قائمة بكلمات بحث مختلفة لضمان التنوع
    $topics = ['Human Gene', 'Cancer Biology', 'Neural Protein', 'Genetic Mutation'];
    $query = $topics[array_rand($topics)]; 
    $random_start = rand(0, 100);

    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gene&term=".urlencode($query)."&retmax=3&retstart=$random_start&retmode=json";
    
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
            $gene_summary_en = $summary_data['result'][$id]['summary'] ?? "No description available";

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
    $message = ($count > 0) ? "<div class='alert alert-info'>تم استيراد وترجمة $count جينات جديدة من موضوع ($query)!</div>" : "<div class='alert alert-warning'>لم يتم العثور على جينات جديدة حالياً.</div>";
}

// --- ثالثاً: الإضافة اليدوية ---
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
    <title>إدارة القاموس الذكي المترجم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border-radius: 15px; border: none; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .btn-main { padding: 15px; font-weight: bold; border-radius: 10px; transition: all 0.3s ease; }
        .btn-main:hover { transform: scale(1.02); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .header-title { color: #2c3e50; font-weight: 800; }
    </style>
</head>
<body class="container py-5">

    <?php echo $message; ?>

    <div class="text-center mb-5">
        <h1 class="header-title">📋 لوحة تحكم القاموس البيولوجي</h1>
        <p class="text-secondary">جلب البيانات العلمية من NCBI وترجمتها فورياً</p>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card p-4">
                <h4 class="mb-4 text-primary">⚡ استيراد تلقائي مترجم</h4>
                <form method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <button type="submit" name="import_hemophilia" class="btn btn-danger btn-block btn-main">
                                🩸 جلب أبحاث الهيموفيليا (أجزاء عشوائية)
                            </button>
                            <small class="text-muted d-block mt-2">سيقوم النظام بالبحث في أجزاء مختلفة من قاعدة Pubmed في كل مرة.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <button type="submit" name="import_genes" class="btn btn-info btn-block btn-main">
                                🧬 جلب جينات عشوائية متنوعة
                            </button>
                            <small class="text-muted d-block mt-2">يختار موضوعاً عشوائياً (سرطان، بروتين، جينات) ويترجمه.</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card p-4">
                <h4 class="mb-4 text-success">✍️ إضافة مصطلح يدوي</h4>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>المصطلح الأصلي (English):</label>
                            <input name="txt_term" class="form-control" type="text" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الترجمة المعتمدة:</label>
                            <input name="trans" class="form-control" type="text" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>الشرح العلمي الكامل:</label>
                        <textarea name="TextArea1" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>إرفاق صورة (اختياري):</label>
                        <input name="File1" type="file" class="form-control-file border p-1 rounded">
                    </div>
                    <button name="Submit1" type="submit" class="btn btn-success btn-block btn-main">💾 حفظ المصطلح في القاعدة</button>
                </form>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
