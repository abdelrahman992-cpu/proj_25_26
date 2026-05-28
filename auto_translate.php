<?php

function autoTranslatePage($html){

    $lang = $_SESSION['lang'] ?? 'ar';

    if($lang === 'ar'){
        return $html;
    }

    $cacheFile = __DIR__."/cache/translate_$lang.json";

    if(!file_exists($cacheFile)){
        return $html;
    }

    $translations = json_decode(file_get_contents($cacheFile),true);

    if(!is_array($translations)){
        return $html;
    }

    foreach($translations as $ar=>$tr){
        $html = str_replace($ar,$tr,$html);
    }

    return $html;
}
