<?
    // ���������� ������ CSS
    //
    // ���������� @import � ����, �������� ���� ���� url(//file) � ��������� ������ �����,
    // ������ ���������� ����� �����������. �������������� � ���������� �� ������� ���� �� ����� (TODO!)
    
    require_once '../common/sxml.lib.php';
    
    function getCSSContent($theFile) {
        global $SXMLConfig;
        $css = file_get_contents($theFile);
        $css = str_replace('url(//', 'url('.$SXMLConfig['root'], $css);
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