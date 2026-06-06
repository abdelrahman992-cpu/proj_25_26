<?php
 date_default_timezone_set("Africa/Cairo");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("connection.php");
include_once("config.php");
//include_once("auto_api_import.php");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
function callAPI($method, $url, $data = false, $token = null) {
    $curl = curl_init();
    
    $options = [
        CURLOPT_URL => "http://127.0.0.1:8000" . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ];

    if ($token) {
        $options[CURLOPT_HTTPHEADER][] = "Authorization: Bearer " . $token;
    }

    if ($data) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}
?>
