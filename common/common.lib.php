<?
    ///////////
    // Утилиты
    ///////////
    
    require_once 'setup.php';

    // Определяет, является ли $path относительным адресом и в любом случае преобразует его к локальному пути. Если путь не преобразуется к локальному,
    // то возвращает false
    function resolvePath($path, $baseURI = '') {
        global $SXMLParams;
        // Возможные варианты $path: 
        // - [http:]//sergets.ru/dir/dir[/../..]/file.txt
        // - /dir/dir[/../..]/path (от DOCUMENT_ROOT)
        // - dir[/..]/path
        // Если baseURI - пустая строка, то это $_SERVER['PHP_SELF'];
        // baseURI приводится к UNIX-виду (прямые слеши).
        
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
            return false; // Другой сайт
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
        global $SXMLParams;
        
        return str_replace(str_replace('\\', '/', $SXMLParams['localroot']).'/', $SXMLParams['host'].$SXMLParams['root'], $path);
    }
    
    //////
    // Всякий строковой мусор
    //////
    
    function strrstr($h, $n) {
        return array_shift(explode($n, $h, 2));
    }
    
    //////
    // Отладка
    /////
    
    $_SXMLLog = array();
    
    function dLog($text, $var = null) {
        global $_SXMLLog; 
        if (isset($var)) {
            $v = print_r($var, true);
            $_SXMLLog[] = array($text, $v);
        } else {
            $_SXMLLog[] = array($text, $v);
        }
    }
?>