<?php
ob_start();
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once("api.php"); // تأكد من الـ clean داخل api.php كما فعلنا فوق
include_once("conn.php"); // تأكد من الـ clean داخل api.php كما فعلنا فوق
$db = $connect ?? $conn;
$message = "";
$term    = $_POST['txt_term'] ?? ""; 
$trans   = $_POST['trans'] ?? "";
$defe    = $_POST['Text'] ?? ""; // تأكد أن اسم الحقل في الفورم هو 'defe'
$term_id = $_POST['term_id'] ?? null; // إذا كنت في صفحة تعديل
$user_id = $_SESSION['user_id'] ?? 1; // قيمة افتراضية للتجربة

if (isset($_POST['Submit1'])) {
    $url = 'http://127.0.0.1:8000/terms/';
    
    // تأكد من تطابق هذه الأسماء مع ما يتوقعه الـ API (schemas.TermSchema)
    $data = [
        'term'    => $_POST['txt_term'] ?? "",
        'trans'   => $_POST['trans'] ?? "",
        'defe'    => $_POST['TextArea1'] ?? "", // تم تغييرها لتطابق اسم الـ input
        'status'  => 'pending',
        'user_id' => (int)($_SESSION['user_id'] ?? 1)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $_SESSION['api_message'] = "✅ تم حفظ المصطلح بنجاح عبر الـ API!";
    } else {
        $_SESSION['api_message'] = "❌ فشل الاتصال بالـ API. كود الحالة: $httpCode | الرد: $response";
    }
    curl_close($ch);
    
    // إعادة توجيه لمنع إعادة إرسال النموذج عند تحديث الصفحة
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// التحقق من أن الطلب هو طلب إرسال (POST/PUT)
$functions_ready = function_exists('fetch_from_ncbi') && function_exists('translate_to_arabic');

// --- الجزء الخاص بالبوت (Python API) ---
$json_input = json_decode(file_get_contents("php://input"), true);
$data = array_merge($_POST, (array)$json_input);
// 2. التحقق من الهوية (سواء كان بوت أو مستخدم مسجل دخوله)
$is_python = (isset($data['api_key']) && $data['api_key'] === 'my_secret_key_123');
$is_logged_in = !empty($_SESSION['username']);

// السماح بالدخول فقط إذا كان بوت أو مستخدم مسجل
if (!$is_python && !$is_logged_in) {
    // إذا كان الطلب قادم من "متصفح" وليس لديه سشن، حوله لتسجيل الدخول
    header("Location: ask_to_sign_in.php");
    exit;
}


// 1. تحديد نوع الزائر
// البوت يرسل دائماً api_key، أما المتصفح فيرسل اسم الزر (import_genes)


// 2. معالجة طلب البوت (Python)
if ($is_python) {
    header('Content-Type: application/json');
    $term   = $data['term'] ?? 'N/A';
    $trans  = $data['trans'] ?? 'N/A';
    $defe   = $data['defe'] ?? 'N/A';
    $smiles = $data['smiles_code'] ?? 'N/A';
    $bot_id = 46;

    $sql = "INSERT INTO terms (term, trans, defe, smiles_code, status, user_id, picture) VALUES (?, ?, ?, ?, 'approved', ?, 'pic/ncbi_logo.png')";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $term, $trans, $defe, $smiles, $bot_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($db)]);
    }
    exit; // إنهاء السكربت للبوت فوراً
}


// --- زر استيراد الهيموفيليا ---
if (isset($_POST['import_hemophilia']) && $functions_ready) {
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=Hemophilia%20Gene%20Therapy&retmax=3&retmode=json";
    $search_res = fetch_from_ncbi($api_search);
    $search_data = json_decode($search_res, true);
    $id_list = $search_data['esearchresult']['idlist'] ?? [];
    
    $count = 0;
    foreach ($id_list as $id) {
        $xml_string = fetch_from_ncbi("https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&id=$id&retmode=xml");
        $xml = @simplexml_load_string($xml_string);
        if ($xml) {
            $t_en = (string)$xml->PubmedArticle->MedlineCitation->Article->ArticleTitle;
            $t_ar = translate_to_arabic($t_en);
            $term_name = "PMID: $id";
            
            $stmt = mysqli_prepare($db, "INSERT IGNORE INTO terms (term, trans, defe, picture, status, user_id) VALUES (?, ?, ?, 'pic/ncbi_logo.png', 'approved', 46)");
            $abstract = "Research ID: " . $id; // ملخص مبدئي لتقليل الوقت
            mysqli_stmt_bind_param($stmt, "sss", $term_name, $t_ar, $abstract);
            if (mysqli_stmt_execute($stmt)) $count++;
        }
    }
    $message = "<div class='alert alert-danger'>🩸 تم استيراد $count أبحاث بنجاح!</div>";
}

// --- زر استيراد الجينات ---
if (isset($_POST['import_genes']) && $functions_ready) {
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gene&term=Human%20Gene&retmax=3&retmode=json";
    $search_data = json_decode(fetch_from_ncbi($api_search), true);
    $id_list = $search_data['esearchresult']['idlist'] ?? [];
    
    $count = 0;
    foreach ($id_list as $id) {
        $summary = json_decode(fetch_from_ncbi("https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=gene&id=$id&retmode=json"), true);
        if (isset($summary['result'][$id])) {
            $name = "Gene: " . $summary['result'][$id]['name'];
            $ar_name = translate_to_arabic($name);
            $desc = translate_to_arabic($summary['result'][$id]['description'] ?? "No desc");
            
            $stmt = mysqli_prepare($db, "INSERT IGNORE INTO terms (term, trans, defe, picture, status, user_id) VALUES (?, ?, ?, 'pic/ncbi_logo.png', 'approved', 46)");
            mysqli_stmt_bind_param($stmt, "sss", $name, $ar_name, $desc);
            if (mysqli_stmt_execute($stmt)) $count++;
        }
    }
    $message = "<div class='alert alert-info'>🧬 تم جلب $count جينات!</div>";
}

include("header.php");
// باقي ملف الـ HTML يكمل هنا كما هو لديك
include("validation.php");


if (isset($_POST['import_hemophilia'])) {
    $query = "Hemophilia Gene Therapy";
    $random_start = rand(0, 100); 
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=".urlencode($query)."&retmax=3&retstart=$random_start&retmode=json";
    
    $search_data = json_decode(fetch_from_ncbi($api_search), true);
    $id_list = $search_data['esearchresult']['idlist'] ?? [];
    
    $count = 0;
    foreach ($id_list as $id) {
        $fetch_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&id=$id&retmode=xml";
        $xml_string = fetch_from_ncbi($fetch_url);
        $xml = simplexml_load_string($xml_string);
        
        if ($xml) {
            $title_en = (string)$xml->PubmedArticle->MedlineCitation->Article->ArticleTitle;
            $abstract_en = "";
            if (isset($xml->PubmedArticle->MedlineCitation->Article->Abstract->AbstractText)) {
                foreach ($xml->PubmedArticle->MedlineCitation->Article->Abstract->AbstractText as $part) {
                    $abstract_en .= (string)$part . " ";
                }
            }

            // الترجمة
            $title_ar = translate_to_arabic($title_en);
            $abstract_ar = translate_to_arabic(substr($abstract_en, 0, 1000)); // نترجم أول 1000 حرف لتجنب البطء

            $term_name = "PMID: $id"; // تعريف المتغير قبل الاستخدام
            $check = mysqli_query($db, "SELECT id FROM terms WHERE term = '$term_name'");
            
            if (mysqli_num_rows($check) == 0) {
                $sql = "INSERT INTO terms (term, trans, defe, picture, status, user_id) VALUES (?, ?, ?, 'pic/ncbi_logo.png', 'approved', 46)";
                $stmt = mysqli_prepare($db, $sql);
                mysqli_stmt_bind_param($stmt, "sss", $term_name, $title_ar, $abstract_ar);
                if (mysqli_stmt_execute($stmt)) $count++;
            }
        }
    }
    $message = "<div class='alert alert-danger'>🩸 تم استيراد $count أبحاث هيموفيليا وترجمتها!</div>";
}

// --- ثانياً: استيراد الجينات العشوائية ---
if (isset($_POST['import_genes'])) {
    $topics = ['Human Gene', 'Cancer Biology', 'Genetics'];
    $query = $topics[array_rand($topics)]; 
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gene&term=".urlencode($query)."&retmax=3&retmode=json";
    
    $search_data = json_decode(fetch_from_ncbi($api_search), true);
    $id_list = $search_data['esearchresult']['idlist'] ?? [];
    
    $count = 0;
    foreach ($id_list as $id) {
        $summary_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=gene&id=$id&retmode=json";
        $summary_data = json_decode(fetch_from_ncbi($summary_url), true);
        
        if (isset($summary_data['result'][$id])) {
            $name_en = "Gene: " . $summary_data['result'][$id]['name'];
            $summary_en = $summary_data['result'][$id]['summary'] ?? "No summary";

            $name_ar = translate_to_arabic($name_en);
            $summary_ar = translate_to_arabic(substr($summary_en, 0, 1000));

            $check = mysqli_query($db, "SELECT id FROM terms WHERE term = '$name_en'");
            if (mysqli_num_rows($check) == 0) {
                $sql = "INSERT INTO terms (term, trans, defe, picture, status, user_id) VALUES (?, ?, ?, 'pic/ncbi_logo.png', 'approved', 46)";
                $stmt = mysqli_prepare($db, $sql);
                mysqli_stmt_bind_param($stmt, "sss", $name_en, $name_ar, $summary_ar);
                if (mysqli_stmt_execute($stmt)) $count++;
            }
        }
    }
    $message = "<div class='alert alert-info'>🧬 تم جلب $count جينات جديدة عن ($query)!</div>";
}

?>


<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <title>إدارة القاموس الذكي</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-5">

    <?php echo $message; ?>

    <div class="text-center mb-5">
        <h1>📋 لوحة تحكم القاموس البيولوجي</h1>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow p-4">
                <form method="post">
                    <button type="submit" name="import_hemophilia" class="btn btn-danger m-1">🩸 جلب أبحاث الهيموفيليا</button>
                    <button type="submit" name="import_genes" class="btn btn-info m-1">🧬 جلب جينات عشوائية</button>
                </form>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card shadow p-4">
                <h4 class="text-success mb-3">✍️ إضافة مصطلح يدوي</h4>
                <?php 
if (isset($_SESSION['api_message'])) {
    echo "<div class='alert alert-info'>" . $_SESSION['api_message'] . "</div>";
    unset($_SESSION['api_message']); // حذف الرسالة بعد عرضها لمرة واحدة
}
?>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>المصطلح (English):</label>
                            <input name="txt_term" class="form-control" type="text" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>الترجمة:</label>
                            <input name="trans" class="form-control" type="text" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>الشرح العلمي:</label>
                        <textarea name="TextArea1" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>SMILES Code:</label>
                            <input name="smiles_code" class="form-control" type="text">
                        </div>
                        <div class="form-group col-md-6">
                            <label>الصورة:</label>
                            <input name="File1" type="file" class="form-control-file border p-1 rounded">
                        </div>
                    </div>
                    <button name="Submit1" type="submit" class="btn btn-success btn-block">💾 حفظ في القاعدة</button>
                </form>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>
<script>
// جلب العدد الحالي عند تحميل الصفحة مباشرة
let lastCount = <?php 
    $res = mysqli_query($db, "SELECT COUNT(id) as total FROM terms");
    $row = mysqli_fetch_assoc($res);
    echo $row['total']; 
?>;

function checkNewTerms() {
    fetch('check_count.php')
        .then(response => response.text())
        .then(count => {
            count = parseInt(count);
            if (lastCount > 0 && count > lastCount) {
                alert("🔔 تنبيه: البوت أضاف مصطلحاً جديداً الآن!");
                location.reload(); 
            }
            lastCount = count;
        });
}
setInterval(checkNewTerms, 10000);
</script>
</body>
</html>
