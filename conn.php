<?php
 date_default_timezone_set("Africa/Cairo");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("connection.php");
//include_once("config.php");
//include_once("auto_api_import.php");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

function callAPI($method, $url, $data = false, $token = null) {
    $full_url = "http://127.0.0.1:8000" . $url;
    $curl = curl_init($full_url);
    
    // تأكد من وجود هذه الأسطر:
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($data) {
        $json_data = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);
        // سطر للـ Debug (احذفه لاحقاً):
        // file_put_contents('debug.log', "URL: $full_url | Data: $json_data\n", FILE_APPEND);
    }
    $response = curl_exec($curl);
    
    // إضافة فحص للخطأ البرمجي
    if (curl_errno($curl)) {
        return ['error' => curl_error($curl)];
    }
    
    curl_close($curl);
    return json_decode($response, true);
}


?>

