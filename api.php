<?php
include_once("conn.php");
$db = $connect ?? $conn;

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


if (basename($_SERVER['PHP_SELF']) == 'api.php') {
    header("Content-Type: application/json; charset=UTF-8");
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    $action = $_GET['action'] ?? $input['action'] ?? '';

    switch ($action) {
        case 'search_terms':
            $search = $_GET['search'] ?? '';
            $stmt = mysqli_prepare($db, "SELECT id, term, trans, defe, smiles_code, picture FROM terms WHERE (term LIKE ? OR trans LIKE ?) AND status = 'approved'");
            $param = "%$search%";
            mysqli_stmt_bind_param($stmt, "ss", $param, $param);
            mysqli_stmt_execute($stmt);
            echo json_encode(["status" => "success", "data" => mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC)]);
            break;

        case 'add_term':
            $stmt = mysqli_prepare($db, "INSERT INTO terms (term, trans, defe, smiles_code, status, user_id, picture) VALUES (?, ?, ?, ?, 'approved', ?, 'pic/ncbi_logo.png')");
            mysqli_stmt_bind_param($stmt, "ssssi", $input['term'], $input['trans'], $input['defe'], $input['smiles_code'], $input['user_id']);
            if(mysqli_stmt_execute($stmt)) echo json_encode(["status" => "success", "id" => mysqli_insert_id($db)]);
            else echo json_encode(["status" => "error", "message" => mysqli_error($db)]);
            break;

        case 'delete_term':
            $stmt = mysqli_prepare($db, "DELETE FROM terms WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $input['id']);
            if(mysqli_stmt_execute($stmt)) echo json_encode(["status" => "success"]);
            else echo json_encode(["status" => "error", "message" => mysqli_error($db)]);
            break;

        case 'update_profile':
            $stmt = mysqli_prepare($db, "UPDATE users SET username=?, email=?, phone=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssi", $input['username'], $input['email'], $input['phone'], $input['user_id']);
            if(mysqli_stmt_execute($stmt)) echo json_encode(["status" => "success"]);
            else echo json_encode(["status" => "error"]);
            break;

        case 'request_otp':
            // منطق طلب الـ OTP الموحد
            $otp = rand(100000, 999999);
            mysqli_query($db, "UPDATE users SET otp_code='$otp', otp_expire=DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE email='".$input['email']."'");
            sendOTP($input['email'], "كودك هو: $otp", "تأكيد", $sender_email, $sender_pass);
            echo json_encode(["status" => "success"]);
            break;

        case 'reset_password_confirm':
            $new_pass = password_hash($input['new_password'], PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($db, "UPDATE users SET passwor=?, otp_code=NULL WHERE email=? AND otp_code=? AND otp_expire > NOW()");
            mysqli_stmt_bind_param($stmt, "sss", $new_pass, $input['email'], $input['otp']);
            mysqli_stmt_execute($stmt);
            if(mysqli_stmt_affected_rows($stmt) > 0) echo json_encode(["status" => "success"]);
            else echo json_encode(["status" => "error"]);
            break;

        default:
            echo json_encode(["status" => "error", "message" => "Action not found"]);
    }
    exit;
}
?>
