<?
    require_once('../common/setup.php');
    require_once('../common/sxml.lib.php');
    require_once('../common/db.lib.php');
    require_once($SXMLParams['login'].'/login.inc.php');
    
    $hash = substr(strrchr($_SXML['file'], '/'), 1);
    $contentType = simpleSelect('type', '"sxml:uploads"', '"hash" = \''.$hash.'\'');
    $filename = simpleSelect('original', '"sxml:uploads"', '"hash" = \''.$hash.'\'');
    $contents = file_get_contents($_SXML['file']);

    header('Content-Type: '.$contentType);    
    if (isset($_SXML_GET['s']) && $contentType == 'image/jpeg') {
        if (file_exists($_SXML['file'] . '_' . $_SXML_GET['s'])) {
            echo file_get_contents($_SXML['file'] . '_' . $_SXML_GET['s']);
        } elseif (strpos($_SXML_GET['s'], 'x') !== -1 && function_exists('imagecreatefromjpeg')) {
            $src = imagecreatefromjpeg($_SXML['file']);
            $sw = ImageSX($src);
            $sh = ImageSY($src);
            $srcAspectRatio = $sh/$sw;
            $dims = explode('x', $_SXML_GET['s']);
            $w = $dims[0];
            $h = $dims[1];
            if (($w && is_numeric($w)) && (!$h || !is_numeric($h))) {
                $h = $w * $srcAspectRatio;
            } elseif (($h && is_numeric($h)) && (!$w || !is_numeric($w))) {
                $w = $h / $srcAspectRatio;
            } elseif ((!$w || !is_numeric($w)) && (!$h || !is_numeric($h))) {
                echo $contents;
            }
            $dstAspectRatio = $h/$w;
            if ($dstAspectRatio > $srcAspectRatio) {
                $dh = $h;
                $dw = $h / $srcAspectRatio;
            } else {
                $dw = $w;
                $dh = $w * $srcAspectRatio;
            }
            $dest = imagecreatetruecolor($w, $h);
            imagecopyresampled($dest, $src, $w/2 - $dw/2, $h/2 - $dh/2, 0, 0, $dw, $dh, $sw, $sh);
            imagejpeg($dest, $_SXML['file'] . '_' . $_SXML_GET['s'], 90);
            imagejpeg($dest, NULL, 90);
            imagedestroy($dest);
        } else {
            echo $contents;
        }
    } else {
        echo $contents;
    }

?>