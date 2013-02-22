<?
    ///////////
    // ”тилиты
    ///////////
    
    require_once 'setup.php';

    // ќпредел€ет, €вл€етс€ ли $path относительным адресом и в любом случае преобразует его к локальному пути. ≈сли путь не преобразуетс€ к локальному,
    // то возвращает false
    function resolvePath($path, $baseURI = '') {
        global $SXMLParams;
        // ¬озможные варианты $path: 
        // - [http:]//sergets.ru/dir/dir[/../..]/file.txt
        // - /dir/dir[/../..]/path (от DOCUMENT_ROOT)
        // - dir[/..]/path
        // ≈сли baseURI - пуста€ строка, то это $_SERVER['PHP_SELF'];
        // baseURI приводитс€ к UNIX-виду (пр€мые слеши).
        
        if ($baseURI == '') {
            $baseURI = $SXMLParams['docroot'];
        }
        $baseURI = str_replace('\\', '/', $baseURI);
        echo('1 > '.$baseURI.'<br>');
        if (!is_dir($baseURI)) {
            $baseURI = substr($baseURI, 0, strrpos($baseURI, '/'));
            echo('2 > '.$baseURI.'<br>');
        }
        
        if (($s = strpos($path, '//'.$SXMLParams['host'])) !== false) {
            $path = substr($path, $s + strlen('//'.$SXMLParams['host'])); // [http:]//sergets.ru/dir/ -> /dir/
            if ($path == '') $path = '/';
            echo('3 > '.$path.'<br>');
        } else if (strpos($path, '//') !== false) { 
            return false; // ƒругой сайт
        }
        if (strpos($path, '/') === 0) {
            $path = str_replace('\\', '/', $SXMLParams['docroot']).$path;
            echo('4 > '.$path.'<br>');
        } else {
            $path = $baseURI.'/'.$path;
            echo('5 > '.$path.'<br>');
        }
        if (strpos($path, '..') === 0 || strpos($path, '/..') === 0) {
            return false;
        }
        $path = str_replace('/./', '/', $path);
        while (strpos($path, '/..') !== false) {
            $path = preg_replace('/\/([^\/]*[^\/\.][^\/]*)\/\.\./', '', $path);
            echo('6 > '.$path.'<br>');
        }

        echo('7 > '.$path.'<br>');
        if (strpos($path, str_replace('\\', '/', $SXMLParams['localroot'])) !== 0) {
            echo('8 > '.str_replace('\\', '/', $SXMLParams['localroot']).' is at '.strpos($path, str_replace('\\', '/', $SXMLParams['localroot'])).'<br>');
            return false;
        } else {
            return $path;
        }
    }

    function local2global($path) {
        // TODO
        return 'http://?/'.$path;
    }
?>