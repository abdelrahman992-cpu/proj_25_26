<?php
include("conn.php");
if (empty($_SESSION['username'])) {
    header("Location: ask_to_sign_in.php");
    exit;
}
include("header.php");
include("validation.php");
if (isset($_POST['bulk_import'])) {
    $search_query = "human monocyte";
    $api_search = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=gds&term=".urlencode($search_query)."&retmax=5&retmode=json";

    // استخدام cURL كبديل لـ file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_search);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // لتجنب مشاكل الـ SSL في localhost
    $search_res = curl_exec($ch);
    curl_close($ch);

    if ($search_res) {
        $search_data = json_decode($search_res, true);
        $id_list = $search_data['esearchresult']['idlist'] ?? [];
        
        if (empty($id_list)) {
            echo "<div class='alert alert-warning'>لم يتم العثور على نتائج من NCBI.</div>";
        }

        $count = 0;
        foreach ($id_list as $id) {
            $summary_url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=gds&id=$id&retmode=json";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $summary_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $summary_res = curl_exec($ch);
            curl_close($ch);

            $summary_data = json_decode($summary_res, true);
            
            if (isset($summary_data['result'][$id])) {
                $accession = $summary_data['result'][$id]['accession'];
                $title = mysqli_real_escape_string($connect, $summary_data['result'][$id]['title']);
                $summary = mysqli_real_escape_string($connect, $summary_data['result'][$id]['summary']);
                
                $check = mysqli_query($connect, "SELECT id FROM terms WHERE term = '$accession'");
                if (mysqli_num_rows($check) == 0) {
                    $sql = "INSERT INTO terms (term, trans, defe, picture, status) VALUES ('$accession', '$title', '$summary', 'pic/ncbi_logo.png', 'approved')";
                    if (mysqli_query($connect, $sql)) {
                        $count++;
                    } else {
                        echo "خطأ في القاعدة: " . mysqli_error($connect) . "<br>";
                    }
                }
            }
        }
        echo "<div class='alert alert-success'>تم استيراد $count مصطلحات بنجاح! راجع صفحة التعديل الآن.</div>";
    } else {
        echo "<div class='alert alert-danger'>فشل الاتصال بـ NCBI. تأكد من الإنترنت أو إعدادات cURL.</div>";
    }
}
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

    $query = mysqli_query($connect, "INSERT INTO terms (term, trans, defe, picture, status) VALUES ('$term', '$trans', '$defe', '$picture', 'approved')");
    if ($query) echo "<div class='alert alert-info'>تم إضافة المصطلح يدوياً بنجاح.</div>";
}
?>

<html dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إدارة المصطلحات</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">

    <div class="card mb-5 border-primary">
        <div class="card-header bg-primary text-white">الاستيراد الذكي (NCBI Auto-Import)</div>
        <div class="card-body text-center">
            <p>سيقوم النظام بجلب أحدث الدراسات المتعلقة بالبيولوجيا الحسابية وإضافتها لقاعدة بياناتك مباشرة.</p>
            <form method="post">
                <button type="submit" name="bulk_import" class="btn btn-lg btn-outline-primary">📦 استيراد مصطلحات جديدة الآن</button>
            </form>
        </div>
    </div>

    <hr>

    <div class="card border-secondary">
        <div class="card-header bg-secondary text-white">إضافة مصطلح يدوي جديد</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>المصطلح:</label>
                    <input name="txt_term" class="form-control" type="text" required>
                </div>
                <div class="form-group">
                    <label>الترجمة/العنوان:</label>
                    <input name="trans" class="form-control" type="text" required>
                </div>
                <div class="form-group">
                    <label>التعريف/الملخص:</label>
                    <textarea name="TextArea1" class="form-control" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label>الصورة:</label>
                    <input name="File1" type="file" class="form-control-file">
                </div>
                <button name="Submit1" type="submit" class="btn btn-success btn-block">➕ حفظ المصطلح يدوياً</button>
            </form>
        </div>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
