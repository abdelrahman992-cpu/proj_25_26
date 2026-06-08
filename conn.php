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
    $curl = curl_init();
    $full_url = "http://127.0.0.1:8000" . $url;
    
    // إعدادات الـ Headers
    $headers = ['Content-Type: application/json'];
    
    // هنا الجزء الأهم: إضافة التوكن كـ Bearer
    if ($token) {
        $headers[] = "Authorization: Bearer " . $token;
    }
    
    curl_setopt($curl, CURLOPT_URL, $full_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}

?>

