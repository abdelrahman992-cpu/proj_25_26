<?php
include("conn.php");

// 1. تفعيل السشن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إظهار الأخطاء للتصحيح
ini_set('display_errors', 1);
error_reporting(E_ALL);

// تحديد متغير الاتصال
$db = $connect ?? $conn;
if (!$db) { die("❌ خطأ في الاتصال بقاعدة البيانات"); }

// 2. التحقق من الهوية (بايثون أو مستخدم مسجل)
$is_python = (isset($_POST['api_key']) && $_POST['api_key'] === 'my_secret_key_123');

if (!$is_python && empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}

// --- أولاً: معالجة طلب بايثون (Drug Design) ---
if ($is_python) {
    $term   = $_POST['txt_term'] ?? 'N/A';
    $trans  = $_POST['trans'] ?? $term;
    $desc   = $_POST['TextArea1'] ?? 'N/A';
    $smiles = $_POST['smiles_code'] ?? 'N/A';
    $bot_id = 99; // ID خاص بالبوت

    $sql = "INSERT INTO terms (term, trans, defe, smiles_code, status, user_id, picture) 
            VALUES (?, ?, ?, ?, 'approved', ?, 'pic/ncbi_logo.png')";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $term, $trans, $desc, $smiles, $bot_id);
    
    if (mysqli_stmt_execute($stmt)) {
        die("✅ Success");
    } else {
        die("❌ MySQL Error: " . mysqli_error($db));
    }
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

// نضع الهيدر وبقية الملفات للمستخدم المتصفح فقط
include("header.php");
include("validation.php");
$message = "";

// --- ثانياً: وظائف الترجمة وجلب البيانات من NCBI (كما هي في كودك) ---
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
    return $result[0][0][0] ?? "";
}

function fetch_from_ncbi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// --- ثالثاً: معالجة الإضافة اليدوية (Submit1) ---
if (isset($_POST['Submit1'])) {
    $term   = sanStr($_POST['txt_term']);
    $trans  = sanStr($_POST['trans']);
    $desc   = sanStr($_POST['TextArea1']);
    $smiles = $_POST['smiles_code'] ?? 'N/A';
    $user_id = $_SESSION['user_id'];
    
    // تحديد الحالة بناءً على الرتبة
    $status = ($_SESSION['role'] === 'admin') ? 'approved' : 'pending';

    // معالجة الصورة
    $picture = "pic/ncbi_logo.png";
    if (!empty($_FILES['File1']['name'])) {
        $file = time() . "_" . $_FILES['File1']['name'];
        move_uploaded_file($_FILES['File1']['tmp_name'], 'pic/' . $file);
        $picture = "pic/" . $file;
    }

    $sql = "INSERT INTO terms (term, trans, defe, smiles_code, picture, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssi", $term, $trans, $desc, $smiles, $picture, $status, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        if ($_SESSION['role'] === 'admin') {
            $message = "<div class='alert alert-success'>✅ أضيف بنجاح كأدمن!</div>";
        } else {
            $message = "<div class='alert alert-info'>⏳ تم الإرسال وبانتظار مراجعة الأدمن.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>خطأ: " . mysqli_error($db) . "</div>";
    }
}

// (أكواد استيراد الهيموفيليا والجينات تبقى هنا كما هي مع التأكد من إضافة $user_id لها)
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
</body>
</html>
