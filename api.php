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



?>
