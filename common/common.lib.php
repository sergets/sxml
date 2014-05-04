<?
    //////////
    // Параметры
    /////////
    
    function parseSXMLConfig($file) {
        global $SXMLConfig;

        if(!isset($SXMLConfig)) {
            $SXMLConfig = array();
        }

        if(!file_exists($file)) {
            return false;
        }

        $configDoc = DOMDocument::load($file);
        $vars = $configDoc->documentElement->childNodes;
        $ns = $configDoc->documentElement->namespaceURI;

        for ($i = 0; $i < $vars->length; $i++) {
            $var = $vars->item($i);
            $name = $var->localName;
            if ($var->hasChildNodes())  {
                if ($var->childNodes->length == 1 && $var->firstChild->nodeType == XML_TEXT_NODE) {
                    $SXMLConfig[$name] = $var->textContent;
                } else {
                    $arr = array();
                    $items = $var->childNodes;
                    for ($j = 0; $j < $items->length; $j++) {
                        $item = $items->item($j);
                        if ($item->nodeType == XML_ELEMENT_NODE) {
                            if ($item->localName == 'item' && $item->namespaceURI == $ns) {
                                $arr[] = $item->textContent;
                            } else {
                                $arr[$item->localName] = $item->textContent;
                            }
                        }
                    }
                    $SXMLConfig[$name] = $arr;
                }
            } else {
                $SXMLConfig[$name] = true; 
            }
        }

        isset($SXMLConfig['docroot']) || ($SXMLConfig['docroot'] = $_SERVER['DOCUMENT_ROOT']); // /var/www/site_ru
        isset($SXMLConfig['host'])    || ($SXMLConfig['host'] = $_SERVER['HTTP_HOST']);        // site.ru
        
        if (!isset($SXMLConfig['folder']) || !isset($SXMLConfig['root'])) {
            $whereAmI = explode('/', substr($_SERVER['PHP_SELF'], 1));
            do {
                array_pop($whereAmI); // handler.php
                $curfldr = $SXMLConfig['docroot'] . '/' . join('/', $whereAmI);
            } while (!file_exists($curfldr . '/client'));
        }
        
        isset($SXMLConfig['folder'])    || ($SXMLConfig['folder'] = array_pop($whereAmI));                                       // sxml
        isset($SXMLConfig['root'])      || ($SXMLConfig['root'] = '/'.join('/', $whereAmI).((count($whereAmI) > 0) ? '/' : '')); // / or /project/
        isset($SXMLConfig['localroot']) || ($SXMLConfig['localroot'] = $SXMLConfig['docroot'].$SXMLConfig['root']);              // /var/www/site_ru/project/
        isset($SXMLConfig['sxml'])      || ($SXMLConfig['sxml'] = $SXMLConfig['localroot'].$SXMLConfig['folder']);
        
        isset($SXMLConfig['login'])     || ($SXMLConfig['login'] = $SXMLConfig['sxml'].$SXMLConfig['paths']['login']);
        isset($SXMLConfig['data'])      || ($SXMLConfig['data'] = $SXMLConfig['localroot'].$SXMLConfig['paths']['dataRoot']);
        isset($SXMLConfig['db'])        || ($SXMLConfig['db'] = $SXMLConfig['data'].$SXMLConfig['paths']['dataFile']);
       
        // разрешённые типы данных для загрузки файлов
        isset($SXMLConfig['uploaddir']) || ($SXMLConfig['uploaddir'] = $SXMLConfig['localroot'].$SXMLConfig['paths']['uploadRoot']);
    }
    
    parseSXMLConfig('../config.xml');
    parseSXMLConfig('../local.xml');
    
    parseSXMLConfig($SXMLConfig['localroot'].'config.xml');
    parseSXMLConfig($SXMLConfig['localroot'].'local.xml');

    ///////////
    // Утилиты
    ///////////

    // Парсит указания блока в том виде, в каком они фигурируют в адресной строке. Возвращает $hash, принимаемый findBlock
    function parseHash($query) {
        $hash = array();
        if (strpos($query, '/') !== false) {
            $q = explode('/', $query);
            $hash['range'] = $q[1];
            $bl = $q[0];
        } else {
            $bl = $query;
        }
        if (strpos($bl, ':') !== false) {
            $q = explode(':', $bl);
            $hash['class'] = $q[0];
            $hash['inst'] = $q[1];
        } elseif ($bl != '') {
            $hash['id'] = $bl;
        }
        return $hash;
    }    
    
    // Разбирает $path на три части: path - путь до знака вопроса, массив get-параметров и sxml-локатор (т. е. последний get-параметр без знака равенства). 
    function splitGetString($path) {
        $result = array(
            'path' => false,
            'get' => array(),
            'hash' => false
        );
        if (false !== ($p = strpos($path, '?'))) {
            $result['path'] = substr($path, 0, $p);
            $querystring = substr($path, $p + 1);
            $gets = explode('&', $querystring);
            if (strpos($gets[count($gets)-1], '=') === false) {
                $result['hash'] = parseHash($gets[count($gets)-1]);
                array_pop($gets);
            } else {
                $result['hash'] = array();
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
    
    // Проверяет, являются ли переданные пути одинаковыми с точностью до порядка атрибутов get без учёта служебных. get передаются в массивах ключ = значение.
    function isSamePath($path1, $get1, $path2, $get2) {
        if ($path1 !== $path2) return false;
        ksort($get1);
        ksort($get2);
        $difference = array_merge(array_diff_key($get1, $get2), array_diff_key($get2, $get1));
        foreach ($difference as $key => $value) {
            if (substr($key, 0, 5) !== 'sxml:') return false;
        }
        return true;
    }
 
    // Определяет, является ли $path относительным адресом и в любом случае преобразует его к локальному пути. Если путь не преобразуется к локальному,
    // то возвращает false
    function resolvePath($path, $baseURI = '') {
        global $SXMLConfig;
        // Возможные варианты $path: 
        // - [http:]//sergets.ru/dir/dir[/../..]/file.txt
        // - /dir/dir[/../..]/path (от DOCUMENT_ROOT)
        // - dir[/..]/path
        // Если baseURI - пустая строка, то это $_SERVER['PHP_SELF'];
        // baseURI приводится к UNIX-виду (прямые слеши).
        
        if ($baseURI == '') {
            $baseURI = $SXMLConfig['docroot'];
        }
        $baseURI = str_replace('\\', '/', $baseURI);
        if (!is_dir($baseURI)) {
            $baseURI = substr($baseURI, 0, strrpos($baseURI, '/'));
        }
        
        if (($s = strpos($path, '//'.$SXMLConfig['host'])) !== false) {
            $path = substr($path, $s + strlen('//'.$SXMLConfig['host'])); // [http:]//sergets.ru/dir/ -> /dir/
            if ($path == '') $path = '/';
        } else if (strpos($path, '//') !== false) { 
            return false; // Другой сайт
        }
        if (strpos($path, '/') === 0) {
            $path = str_replace('\\', '/', $SXMLConfig['docroot']).$path;
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

        if (strpos($path, str_replace('\\', '/', $SXMLConfig['localroot'])) !== 0) {
            return false;
        } else {
            return $path;
        }
    }
    
    function local2global($path) {
        global $SXMLConfig;
        
        return 'http://'.str_replace(str_replace('\\', '/', $SXMLConfig['localroot']), $SXMLConfig['host'].$SXMLConfig['root'], $path);
    }
    
    //////
    // Всякий строковой мусор
    //////
    
    function strrstr($h, $n) {
        return array_shift(explode($n, $h, 2));
    }
    
    //////
    
    function needsSubstitution($txt) {
        return preg_match('/\{\$([\w\-\:\/]+)\}/', $grandchild->wholeText);
    }
    
    function substituteVars($txt, $arr, $fallback = array()) {
        $matches = false;
        while(preg_match('/\{\$([\w\-\:\/]+)\}/', $txt, $matches)) {
            $varname = $matches[1];
            if (isset($arr[$varname]) || isset($fallback[$varname])) {
                $txt = str_replace('{$'.$varname.'}', isset($arr[$varname])? $arr[$varname] : $fallback[$varname], $txt);
            } else {
                $txt = str_replace('{$'.$varname.'}', '', $txt);
            }
        }
        return $txt;
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