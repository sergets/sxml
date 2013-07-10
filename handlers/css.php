<?
    // Обработчик файлов CSS
    //
    // Подключает @import в тело, резолвит урлы вида url(//file) в настоящий корень файла,
    // ставит длительное время кеширования. Минифицировать и кешировать на сервере пока не умеет (TODO!)
    
    require_once '../common/setup.php';
    require_once '../common/sxml.lib.php';
    
    $css = file_get_contents($_SXML['file']);
    $css = str_replace('url(//', 'url('.$SXMLParams['root'], $css);
    preg_match_all('/\@import ("|url\()(.*)("|\));/', $css, $imports);
    foreach ($imports[2] as $n => $file) {
        $f = resolvePath($file, $_SXML['file']);
        if (file_exists($f)) {
            $css = str_replace($imports[0][$n], "/* ------- imported from ".$f." ----- */\n".file_get_contents($f)."\n/* --------- import ends -------- */\n\n", $css);
        } else {
            $css = str_replace($imports[0][$n], $imports[0][$n].' /* -- warning! '.$f.' not found */', $css);
        }
    }
    
    header('Content-type: text/css');
    echo $css;
?>