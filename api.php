<?php
// api.php - المكتبة والمحرك الشامل
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

if (basename($_SERVER['PHP_SELF']) == 'api.php') {
    header("Content-Type: application/json; charset=UTF-8");
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    $action = $_GET['action'] ?? $input['action'] ?? '';

    switch ($action) {
        // --- 1. المصطلحات ---
        case 'search_terms':
            $search = $_GET['search'] ?? '';
            $stmt = mysqli_prepare($db, "SELECT id, term, trans, defe, smiles_code, picture FROM terms WHERE (term LIKE ? OR trans LIKE ?) AND status = 'approved'");
            $param = "%$search%";
            mysqli_stmt_bind_param($stmt, "ss", $param, $param);
            mysqli_stmt_execute($stmt);
            echo json_encode(["status" => "success", "data" => mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC)]);
            break;

        case 'add_term':
            $term = $input['term'] ?? '';
            $trans = $input['trans'] ?? '';
            $defe = $input['defe'] ?? '';
            $smiles = $input['smiles_code'] ?? 'N/A';
            $user_id = $input['user_id'] ?? 46;
            $stmt = mysqli_prepare($db, "INSERT INTO terms (term, trans, defe, smiles_code, status, user_id, picture) VALUES (?, ?, ?, ?, 'approved', ?, 'pic/ncbi_logo.png')");
            mysqli_stmt_bind_param($stmt, "ssssi", $term, $trans, $defe, $smiles, $user_id);
            if(mysqli_stmt_execute($stmt)) echo json_encode(["status" => "success", "id" => mysqli_insert_id($db)]);
            else echo json_encode(["status" => "error", "message" => mysqli_error($db)]);
            break;

        case 'delete_term':
            $id = $input['id'] ?? $_GET['id'] ?? null;
            $stmt = mysqli_prepare($db, "DELETE FROM terms WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if(mysqli_stmt_execute($stmt)) echo json_encode(["status" => "success"]);
            else echo json_encode(["status" => "error", "message" => mysqli_error($db)]);
            break;

        // --- 2. المستخدمين ---
        case 'update_profile':
            // ... (الكود الخاص بك) ...
                        $user_id = $input['user_id'] ?? null; // يفضل جلبه من الـ Session
            $username = $input['username'] ?? '';
            $email = $input['email'] ?? '';
            $phone = $input['phone'] ?? '';

            if (!$user_id) {
                echo json_encode(["status" => "error", "message" => "غير مصرح"]);
                break;
            }

            $sql = "UPDATE users SET username=?, email=?, phone=? WHERE id=?";
            $stmt = mysqli_prepare($db, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $username, $email, $phone, $user_id);
            
            if(mysqli_stmt_execute($stmt)) echo json_encode(["status" => "success", "message" => "تم تحديث البيانات"]);
            else echo json_encode(["status" => "error", "message" => "فشل التحديث"]);
            break;

        case 'request_otp':
            // ... (الكود الخاص بك) ...
             $email = $input['email'] ?? '';
            $type = $input['type'] ?? 'general'; // نوع العملية: 'profile' أو 'password'
            
            // التحقق من وجود المستخدم
            $stmt = mysqli_prepare($db, "SELECT id FROM users WHERE email=?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            
            if(mysqli_num_rows($res) > 0) {
                $otp = rand(100000, 999999);
                $expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));
                
                // حفظ الكود في قاعدة البيانات (يمكنك إضافة عمود otp_type في جدول المستخدمين إذا أردت)
                $update = mysqli_prepare($db, "UPDATE users SET otp_code=?, otp_expire=? WHERE email=?");
                mysqli_stmt_bind_param($update, "sss", $otp, $expire, $email);
                mysqli_stmt_execute($update);
                
                // إرسال الإيميل
                $subject = ($type == 'password') ? "إعادة تعيين كلمة المرور" : "كود تأكيد تعديل البيانات";
                sendOTP($email, "مرحباً، كود التأكيد الخاص بك هو: $otp", $subject, $sender_email, $sender_pass);
                
                echo json_encode(["status" => "success", "message" => "تم إرسال الكود لبريدك"]);
            } else {
                echo json_encode(["status" => "error", "message" => "البريد غير موجود"]);
            }
            break;

        case 'reset_password_confirm':
            // ... (الكود الخاص بك) ...
                       $email = $input['email'] ?? '';
            $otp = $input['otp'] ?? '';
            $new_pass = password_hash($input['new_password'], PASSWORD_DEFAULT);

            // التحقق من الكود
            $sql = "UPDATE users SET passwor=?, otp_code=NULL WHERE email=? AND otp_code=? AND otp_expire > NOW()";
            $stmt = mysqli_prepare($db, $sql);
            mysqli_stmt_bind_param($stmt, "sss", $new_pass, $email, $otp);
            mysqli_stmt_execute($stmt);

            if(mysqli_stmt_affected_rows($stmt) > 0) {
                echo json_encode(["status" => "success", "message" => "تم تغيير كلمة المرور بنجاح"]);
            } else {
                echo json_encode(["status" => "error", "message" => "الكود غير صحيح أو انتهى"]);
            }
            break;
             case 'forgot_password':
            $email = $input['email'] ?? '';
            $sql = "SELECT id FROM users WHERE email=?";
            $stmt = mysqli_prepare($db, $sql);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if(mysqli_num_rows($res) > 0) {
                $new_otp = rand(100000, 999999);
                // تحديث الـ OTP في قاعدة البيانات ليتم استخدامه للتحقق
                $update = mysqli_prepare($db, "UPDATE users SET otp_code=?, otp_expire=DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE email=?");
                mysqli_stmt_bind_param($update, "ss", $new_otp, $email);
                mysqli_stmt_execute($update);

                sendOTP($email, "كود إعادة تعيين كلمة المرور هو: " . $new_otp, $sender_email, $sender_pass);
                echo json_encode(["status" => "success", "message" => "تم إرسال كود التعيين للإيميل"]);
            } else {
                echo json_encode(["status" => "error", "message" => "البريد غير مسجل"]);
            }
            break;

        // --- 6. تعيين كلمة مرور جديدة (بعد التحقق من الـ OTP) ---
        case 'reset_password_confirm':
            $email = $input['email'] ?? '';
            $otp = $input['otp'] ?? '';
            $new_pass = password_hash($input['new_password'], PASSWORD_DEFAULT);

            // التحقق من الكود
            $sql = "UPDATE users SET passwor=?, otp_code=NULL WHERE email=? AND otp_code=? AND otp_expire > NOW()";
            $stmt = mysqli_prepare($db, $sql);
            mysqli_stmt_bind_param($stmt, "sss", $new_pass, $email, $otp);
            mysqli_stmt_execute($stmt);

            if(mysqli_stmt_affected_rows($stmt) > 0) {
                echo json_encode(["status" => "success", "message" => "تم تغيير كلمة المرور بنجاح"]);
            } else {
                echo json_encode(["status" => "error", "message" => "الكود غير صحيح أو انتهى"]);
            }
            break;
            // --- 7. طلب OTP لأي عملية (تعديل بيانات أو استعادة كلمة مرور) ---

        default:
            echo json_encode(["status" => "error", "message" => "Action not found"]);
    }
    exit;
}
?>
