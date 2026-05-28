<?php 

function sanStr($value){
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function reqVal($value){
    return !empty($value);
}
?>