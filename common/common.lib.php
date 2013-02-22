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
        if (!is_dir($baseURI)) {
            $baseURI = substr($baseURI, 0, strrpos($baseURI, '/'));
        }
        
        if (($s = strpos($path, '//'.$SXMLParams['host'])) !== false) {
            $path = substr($path, $s + strlen('//'.$SXMLParams['host'])); // [http:]//sergets.ru/dir/ -> /dir/
            if ($path == '') $path = '/';
        } else if (strpos($path, '//') !== false) { 
            return false; // ƒругой сайт
        }
        if (strpos($path, '/') === 0) {
            $path = str_replace('\\', '/', $SXMLParams['docroot']).$path;
        } else {
            $path = $baseURI.'/'.$path;
        }
        if (strpos($path, '..') === 0 || strpos($path, '/..') === 0) {
            return false;
        }
        $path = str_replace('/./', '/', $path);
        while (strpos($path, '/..') !== false) {
            $path = preg_replace('/\/([^\/]*[^\/\.][^\/]*)\/\.\./', '', $path);
        }

        if (strpos($path, str_replace('\\', '/', $SXMLParams['localroot'])) !== 0) {
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