<?php
// api.php - نسخة "صامتة" للعمل كمكتبة دوال
include_once("conn.php");
$db = $connect ?? $conn;

// دالة جلب البيانات من NCBI
if (!function_exists('fetch_from_ncbi')) {
    function fetch_from_ncbi($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}

// دالة الترجمة
if (!function_exists('translate_to_arabic')) {
    function translate_to_arabic($text) {
        if (empty($text)) return "N/A";
        $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=ar&dt=t&q=" . urlencode($text);
        $res = fetch_from_ncbi($url);
        if (!$res) return $text;
        $json = json_decode($res, true);
        return $json[0][0][0] ?? $text;
    }
}

// حماية الكود التنفيذي: لا يعمل إلا إذا تم استدعاء الملف مباشرة كـ API وليس بـ include
if (basename($_SERVER['PHP_SELF']) == 'api.php') {
    header("Content-Type: application/json; charset=UTF-8");
    $method = $_SERVER['REQUEST_METHOD'];
    
    // ... ضع كود الـ switch هنا (GET, POST, DELETE) ...
    // هذا الجزء لن يعمل الآن إلا إذا طلب البوت رابط api.php مباشرة
}

switch($method) {
    // --- 1. جلب البيانات (Read) ---
    case 'GET':
        $search = $_GET['search'] ?? '';
        $sql = "SELECT id, term, trans, defe, smiles_code, picture FROM terms WHERE (term LIKE ? OR trans LIKE ?) AND status = 'approved'";
        $stmt = mysqli_prepare($db, $sql);
        $param = "%$search%";
        mysqli_stmt_bind_param($stmt, "ss", $param, $param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode(["status" => "success", "data" => $data]);
        break;

    // --- 2. إضافة بيانات جديدة (Create) ---
    case 'POST':
        // استقبال بيانات JSON أو POST عادية
        $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        
        $term   = $input['term'] ?? '';
        $trans  = $input['trans'] ?? '';
        $defe   = $input['defe'] ?? '';
        $smiles = $input['smiles_code'] ?? 'N/A';
        $user_id = $input['user_id'] ?? 46; // ID افتراضي للبوت

        if(empty($term) || empty($trans)) {
            echo json_encode(["status" => "error", "message" => "Missing required fields"]);
            break;
        }

        $sql = "INSERT INTO terms (term, trans, defe, smiles_code, status, user_id, picture) VALUES (?, ?, ?, ?, 'approved', ?, 'pic/ncbi_logo.png')";
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $term, $trans, $defe, $smiles, $user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "id" => mysqli_insert_id($db)]);
        } else {
            echo json_encode(["status" => "error", "message" => mysqli_error($db)]);
        }
        break;

    // --- 3. حذف مصطلح (Delete) ---
    case 'DELETE':
        parse_str(file_get_contents("php://input"), $_DELETE);
        $id = $_GET['id'] ?? $_DELETE['id'] ?? null;
        
        if($id) {
            $sql = "DELETE FROM terms WHERE id = ?";
            $stmt = mysqli_prepare($db, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            if(mysqli_stmt_execute($stmt)) {
                echo json_encode(["status" => "success", "message" => "Term deleted"]);
            }
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
        
}

?>
