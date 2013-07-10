<?
    // Обработчик файлов CSS
    //
    // Подключает @import в тело, резолвит урлы вида url(//file) в настоящий корень файла,
    // ставит длительное время кеширования. Минифицировать и кешировать на сервере пока не умеет (TODO!)
    
    require_once '../common/setup.php';
    require_once '../common/sxml.lib.php';
    
    function getCSSContent($theFile) {
        global $SXMLParams;
        $css = file_get_contents($theFile);
        $css = str_replace('url(//', 'url('.$SXMLParams['root'], $css);
        preg_match_all('/\@import ("|url\()(.*)("|\));/', $css, $imports);
        foreach ($imports[2] as $n => $file) {
            $f = resolvePath($file, $theFile);
            if (file_exists($f)) {
                $css = str_replace($imports[0][$n], "/* ------- imported from ".$f." ----- */\n".getCSSContent($f)."\n/* --------- import ends -------- */\n\n", $css);
            } else {
                $css = str_replace($imports[0][$n], $imports[0][$n].' /* -- warning! '.$f.' not found */', $css);
            }
        }
        return $css;
    }
    
    header('Content-type: text/css');
    echo getCSSContent($_SXML['file']);
?>