<?
    ///////////
    // Утилиты
    ///////////
    
    require_once 'setup.php';
    
    // Разбирает $path на три части: path - путь до знака вопроса, массив get-параметров и sxml-локатор (т. е. последний get-параметр без знака равенства). 
    function splitGetString($path) {
        $result = array(
            'path' => false,
            'get' => array(),
            'sxml' => false
        );
        if (false !== ($p = strpos($path, '?'))) {
            $result['path'] = substr($path, 0, $p);
            $querystring = substr($path, $p + 1);
            $gets = explode('&', $querystring);
            if (strpos($gets[count($gets)-1], '=') === false) {
                $result['sxml'] = $gets[count($gets)-1];
                array_pop($gets);
            } else {
                $result['sxml'] = '';
            }
            foreach($gets as $i => $get) {
                if (false !== ($p = strpos($get, '='))) { // Не explode, потому что может быть больше одного знака равенства, а значим только первый.
                    $result['get'][urldecode(substr($get, 0, $p))] = urldecode(substr($get, $p + 1));
                } else {
                    $result['get'][urldecode(substr($get))] = '';
                }
            }
        } else {
            $result['path'] = $path;
        }
        return $result;
    }
 

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
        
        return 'http://'.str_replace(str_replace('\\', '/', $SXMLParams['localroot']), $SXMLParams['host'].$SXMLParams['root'], $path);
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
    
    /*function dLog($file, $line, $text, $var = null) {
        global $_SXMLLog; 
        if (isset($var)) {
            $v = print_r($var, true);
            $_SXMLLog[] = array($file, $line, $text, $v);
        } else {
            $_SXMLLog[] = array($file, $line, $text);
        }
        //echo "&rarr; [<i>" . $file .':'.$line . "</i>] " . $text. ": ". $v . '<br/>';
    }*/

?>