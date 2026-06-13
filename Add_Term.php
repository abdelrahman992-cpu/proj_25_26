<?php
ob_start();
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once("api.php");
include_once("conn.php");
$db = $connect ?? $conn;
$message = "";

// --- التحقق الأمني ---
// نتحقق من وجود الـ API Key أولاً ليتمكن البوت من الدخول
$is_bot = (isset($_POST['api_key']) && $_POST['api_key'] === 'my_secret_key_123');
$is_logged_in = !empty($_SESSION['user_id']);

// إذا لم يكن بوت ولم يكن مسجل دخول، نطرده
if (!$is_bot && !$is_logged_in) {
    header("Location: ask_to_sign_in.php");
    exit;
}

// --- 1. معالجة طلب البوت (عبر API فقط) ---
if ($is_bot) {
    $postData = [
        'term'        => $_POST['txt_term'] ?? 'N/A',
        'trans'       => $_POST['trans'] ?? 'N/A',
        'defe'        => $_POST['TextArea1'] ?? 'N/A',
        'smiles_code' => $_POST['smiles_code'] ?? 'N/A',
        'user_id'     => 46,
        'status'      => 'pending',
        'accession_id'  => $_POST['accession_id'] ?? '',          // 👈 (مهمة جداً) دي اللي هيجيب بيها التسلسل من NCBI
        'disease_class' => $_POST['disease_class'] ?? 'N/A',
        'smiles_code'      => $_POST['smiles_code'] ?? 'N/A',      // 👈 تقدر تبعتها عادي جداً
        'confidence_score' => $_POST['confidence_score'] ?? 'N/A'
    ];

    $result = callAPI("POST", "/api/import-dna-complete/", $postData); 
    
    if (isset($result['status']) && $result['status'] == 'success') {
        echo "✅ Success: تم الإرسال للمراجعة عبر الـ API.";
    } else {
        echo "❌ Error: " . ($result['detail'] ?? "فشل الاتصال بالـ API");
    }
    exit; // إنهاء السكربت للبوت
}

// --- 2. معالجة الإضافة اليدوية (للمستخدمين المسجلين) ---
if (isset($_POST['Submit1'])) {
    $token = $_SESSION['access_token'] ?? "";
    $data = [
        'term'  => $_POST['txt_term'] ?? "",
        'trans' => $_POST['trans'] ?? "",
        'defe'  => $_POST['TextArea1'] ?? ""
    ];

    $ch = curl_init('http://127.0.0.1:8000/terms/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $_SESSION['api_message'] = ($httpCode == 200 || $httpCode == 201) ? "✅ تمت الإضافة بنجاح!" : "❌ فشل الكود ($httpCode): " . $response;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
// --- 2. معالجة الإضافة اليدوية (للمستخدمين المسجلين) ---
if (isset($_POST['Submit2'])) {
    $token = $_SESSION['access_token'] ?? "";
    
    // 1. استلام وتنظيف التسلسل
    $sequence = $_POST['fasta_sequence'] ?? '';
    $clean_sequence = preg_replace('/\s+/', '', strtoupper($sequence));

    // 2. التحقق من أن التسلسل يحتوي فقط على حروف A, C, G, T
    if (!empty($clean_sequence) && !preg_match('/^[ACGT]+$/', $clean_sequence)) {
        // إذا كان هناك نص في الحقل ولكنه لا يطابق الشروط، نوقف العملية ونعرض خطأ
        $_SESSION['api_message'] = "❌ خطأ: تسلسل الـ DNA يجب أن يحتوي على حروف A, C, G, T فقط بدون مسافات أو رموز أخرى.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 3. تجهيز البيانات للإرسال (بما في ذلك التسلسل النظيف)
    $data = [
        'term'          => $_POST['txt_term'] ?? "",
        'trans'         => $_POST['trans'] ?? "",
        'defe'          => $_POST['TextArea1'] ?? "",
        'fasta_seq'     => $clean_sequence, // يتم إرسال التسلسل المعالج والخالي من الأخطاء
        'disease_class' => $_POST['disease_class'] ?? ""
    ];

    $ch = curl_init('http://127.0.0.1:8000/terms/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $_SESSION['api_message'] = ($httpCode == 200 || $httpCode == 201) ? "✅ تمت الإضافة بنجاح!" : "❌ فشل الكود ($httpCode): " . $response;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
if (isset($_POST['fetch_and_analyze'])) {
    // استقبال اسم الجين المدخل
    $gene_name = $_POST['gene_name_to_analyze'] ?? '';
    
    // إرسال الاسم للـ API
    $data = ['gene_name' => $gene_name];
    
$ch = curl_init('http://127.0.0.1:8000/analyze-gene/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    
    if (isset($result['status']) && $result['status'] == 'success') {
        $_SESSION['api_message'] = "📊 **نتيجة التحليل لـ " . htmlspecialchars($result['gene_name']) . ":**<br>" . 
                                   "رقم الجين التلقائي: " . $result['gene_id'] . "<br>" .
                                   "التسلسل (أول 200 قاعدة): " . $result['clean_sequence'] . "<br>" .
                                   "التصنيف: " . $result['disease_classification'];
    } else {
        $_SESSION['api_message'] = "❌ حدث خطأ أثناء التحليل: " . ($result['detail'] ?? 'غير معروف');
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['import_hemophilia'])) {
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
            $abstract = "Research ID: " . $id;
            
            // تجهيز البيانات لإرسالها للـ API
            $data = [
                'term'        => $term_name,
                'trans'       => $t_ar,
                'defe'        => $abstract,
                'picture'     => 'pic/ncbi_logo.png',
                'status'      => 'approved',
                'user_id'     => 46,
                'smiles_code' => 'N/A',
                'fasta_seq'   => 'N/A'
            ];

            // استدعاء الـ API لحفظ البيانات (افترضنا المسار /terms أو المسار الخاص بك)
            $result = callAPI("POST", "/terms/", $data); 
            
            if ($result && isset($result['status']) && $result['status'] == 'success') {
                $count++;
            }
        }
    }
    $message = "<div class='alert alert-danger'>🩸 تم استيراد $count أبحاث بنجاح!</div>";
}



// --- زر استيراد الجينات ---
// في ملف الـ PHP، بدلاً من جلب البيانات يدوياً بـ curl من NCBI
// كل ما عليك فعله هو طلب واحد بسيط للـ API الخاص بك في بايثون:

if (isset($_POST['import_genes'])) {
    // إرسال طلب واحد فقط للـ API ليقوم هو بكل شيء (جلب وحفظ)
    $ch = curl_init('http://127.0.0.1:8000/api/import-ncbi/'); // أو المسار الذي قمت بإنشائه
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['accession_id' => 'NM_000059'])); // مثال
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo "تمت العملية: " . $response;
}
include("header.php");
// باقي ملف الـ HTML يكمل هنا كما هو لديك
include("validation.php");


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
<form method="post">
            <div class="form-group">
                <label>🧬 تسلسل الحمض النووي / البروتين (FASTA Sequence):</label>
                <textarea name="fasta_sequence" class="form-control" rows="3" placeholder=">Seq1&#10;ATCG..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>🏷️ تصنيف المرض (Disease Classification):</label>
                    <select name="disease_class" class="form-control">
                        <option value="Type I">النوع الأول (Type I)</option>
                        <option value="Type II">النوع الثاني (Type II)</option>
                        <option value="Type III">النوع الثالث (Type III)</option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label>🔍 جلب وتحليل جين من NCBI (أدخل الـ ID):</label>
                    <input name="gene_id_to_analyze" class="form-control" type="text" placeholder="أدخل Gene ID هنا">
                   
    <label>🔍 ابحث باسم الجين أو الكلمة المفتاحية من NCBI:</label>
    <input name="gene_name_to_analyze" class="form-control" id="geneSearchInput" type="text" placeholder="مثال: BRCA1, Hemophilia">

                </div>
            </div>
            
            <button name="Submit2" type="submit" class="btn btn-success btn-block mb-2">💾 حفظ التسلسل في القاعدة</button>
            <button name="fetch_and_analyze" type="submit" class="btn btn-primary btn-block">🚀 جلب وتحليل جين</button>
            <datalist id="geneSuggestions"></datalist>
        </form>
       <div class="container mt-4">
    <div class="row">
        <div class="col-md-6">
            <label>السلسلة الأولى (Seq 1):</label>
            <textarea id="seq1" class="form-control" rows="4" placeholder="توليد سلسلة أو لصقها هنا..."></textarea>
        </div>
        <div class="col-md-6">
            <label>السلسلة الثانية (Seq 2):</label>
            <textarea id="seq2" class="form-control" rows="4" placeholder="توليد سلسلة أو لصقها هنا..."></textarea>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary" onclick="generateSequences()">🔄 توليد سلاسل عشوائية</button>
        <button class="btn btn-success" onclick="alignSequences()">⚖️ إجراء محاذاة (Alignment)</button>
        <button class="btn btn-info" onclick="analyzeSequencesWithNcbi()">🧬 تحليل ومطابقة السلاسل بـ NCBI</button>
    </div>

    <div class="mt-4 alert alert-info" id="alignmentResultDiv" style="display:none;">
        <strong>نتيجة المحاذاة (نسبة التطابق):</strong> <span id="alignmentScore"></span><br>
        <pre id="alignmentDetails" class="mt-2"></pre>
    </div>

    <div class="mt-4 alert alert-success" id="ncbiResultDiv" style="display:none;">
        <h5>📊 نتيجة التحليل والمطابقة:</h5>
        <hr>
        <h6>السلسلة الأولى (Seq 1):</h6>
        <p id="resultSeq1"></p>
        <p><strong>التشخيص / الجين:</strong> <span id="disease1"></span> (نسبة الثقة: <span id="score1"></span>%)</p>
        
        <hr>
        <h6>السلسلة الثانية (Seq 2):</h6>
        <p id="resultSeq2"></p>
        <p><strong>التشخيص / الجين:</strong> <span id="disease2"></span> (نسبة الثقة: <span id="score2"></span>%)</p>
        
        <button class="btn btn-dark mt-2" onclick="saveSelectedResults()">💾 حفظ النتيجة الفائزة في قاعدة البيانات</button>
    </div>
</div>
    <?php include('footer.php'); ?>

<script>
document.getElementById('geneSearchInput').addEventListener('input', function() {
    let query = this.value.trim();
    if (query.length < 2) return;

    // الاتصال بمسار الاقتراحات في الـ API الخاص بـ Python
    fetch(`http://127.0.0.1:8000/suggest-genes/?term=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            let datalist = document.getElementById('geneSuggestions');
            datalist.innerHTML = ''; // تفريغ الاقتراحات القديمة
            
            data.length > 0 && data.forEach(item => {
                let option = document.createElement('option');
                // عرض الرمز والشرح في الاقتراح
                option.value = item.symbol;
                option.text = `${item.symbol} - ${item.description}`;
                datalist.appendChild(option);
            });
        });
});

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

const nucleotides = ['A', 'G', 'T', 'C'];

// الزر الأول: توليد سلسلتين عشوائيتين بطول بين 20 و 100 قاعدة
function generateSequences() {
    let rndStr1 = '';
    let rndStr2 = '';
    
   let randomLength1 = Math.floor(Math.random() * (3000 - 1000 + 1)) + 1000;
    let randomLength2 = Math.floor(Math.random() * (3000 - 1000 + 1)) + 1000;
    
    for (let i = 0; i < randomLength1; i++) {
        rndStr1 += nucleotides[Math.floor(Math.random() * nucleotides.length)];
    }
    for (let i = 0; i < randomLength2; i++) {
        rndStr2 += nucleotides[Math.floor(Math.random() * nucleotides.length)];
    }

    document.getElementById('seq1').value = rndStr1;
    document.getElementById('seq2').value = rndStr2;
}

// الزر الثاني: إرسال السلسلتين لسيرفر البايثون (FastAPI) للمقارنة
function alignSequences() {
    let s1 = document.getElementById('seq1').value.trim();
    let s2 = document.getElementById('seq2').value.trim();

    if (!s1 || !s2) {
        alert("الرجاء توليد أو إدخال السلسلتين أولاً!");
        return;
    }

    fetch('http://127.0.0.1:8000/compare-two-sequences/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ seq1: s1, seq2: s2 })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // تم تصحيح المعرف هنا إلى alignmentResultDiv
            document.getElementById('alignmentResultDiv').style.display = 'block';
            document.getElementById('alignmentScore').innerText = data.alignment_score;
            document.getElementById('alignmentDetails').innerText = 
                `السلسلة 1: ${data.sequence_1_aligned}\nالسلسلة 2: ${data.sequence_2_aligned}`;
        } else {
            alert("حدث خطأ: " + data.detail);
        }
    })
    .catch(error => console.error('Error:', error));
}

function compareWithNcbi() {
    let ncbi = document.getElementById('ncbiSeq').value.trim();
    let s1 = document.getElementById('seq1').value.trim();
    let s2 = document.getElementById('seq2').value.trim();

    if (!ncbi || !s1 || !s2) {
        alert("الرجاء إدخال تسلسل NCBI وسلسلتي التكست أريا أولاً!");
        return;
    }

    fetch('http://127.0.0.1:8000/compare-with-ncbi/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ncbi_seq: ncbi, seq1: s1, seq2: s2 })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('alignmentResultDiv').style.display = 'block';
            document.getElementById('score1').innerText = data.score_seq1;
            document.getElementById('score2').innerText = data.score_seq2;
            document.getElementById('closestResult').innerText = data.closest;
        } else {
            alert("حدث خطأ: " + data.detail);
        }
    })
    .catch(error => console.error('Error:', error));
}

function analyzeSequencesWithNcbi() {
    let s1 = document.getElementById('seq1').value.trim();
    let s2 = document.getElementById('seq2').value.trim();

    if (!s1 || !s2) {
        alert("الرجاء إدخال أو توليد السلسلتين أولاً!");
        return;
    }

    fetch('http://127.0.0.1:8000/analyze-sequences/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ seq1: s1, seq2: s2 })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // تم تصحيح المعرف هنا إلى ncbiResultDiv
            document.getElementById('ncbiResultDiv').style.display = 'block';
            
            document.getElementById('resultSeq1').innerText = data.seq1_analysis.sequence;
            document.getElementById('disease1').innerText = data.seq1_analysis.associated_disease;
            document.getElementById('score1').innerText = data.seq1_analysis.confidence_score;

            document.getElementById('resultSeq2').innerText = data.seq2_analysis.sequence;
            document.getElementById('disease2').innerText = data.seq2_analysis.associated_disease;
            document.getElementById('score2').innerText = data.seq2_analysis.confidence_score;
        } else {
            alert("حدث خطأ: " + data.detail);
        }
    })
    .catch(error => console.error('Error:', error));
}

let selectedFasta = "";
let selectedDisease = "";
let selectedScore = "";

function saveSelectedResults() {
    let score1 = parseFloat(document.getElementById('score1').innerText);
    let score2 = parseFloat(document.getElementById('score2').innerText);

    if (score1 >= score2) {
        selectedFasta = document.getElementById('resultSeq1').innerText;
        selectedDisease = document.getElementById('disease1').innerText;
        selectedScore = score1;
    } else {
        selectedFasta = document.getElementById('resultSeq2').innerText;
        selectedDisease = document.getElementById('disease2').innerText;
        selectedScore = score2;
    }

    fetch('http://127.0.0.1:8000/save-term/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            fasta_seq: selectedFasta,
            disease_class: selectedDisease,
            confidence_score: selectedScore
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert("💾 تم حفظ النتيجة في قاعدة البيانات بنجاح!");
        } else {
            alert("حدث خطأ أثناء الحفظ: " + data.detail);
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>
</body>
</html>
