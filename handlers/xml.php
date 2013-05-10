<?
    // Обработчик файлов XML
    //
    // SXML-обработчики вызываются скриптом handler.php, который выясняет
    // имя файла 'file' и требуемые параметры 'query'
    // и кладёт их в глобальный массив $_SXML, а также ставит заголовок 200 OK
    
    require_once '../common/setup.php';
    require_once '../common/sxml.lib.php';
    
    $doc = new DOMDocument();
    $doc->load($_SXML['file']);

    // Выполняем действия
    if (isset($_SXML_POST['sxml:action'])) {
        // Проверяем токен
        if ($_SXML_POST['sxml:token'] !== $_SXML['token']) {
            $doc->replaceChild(createError($doc, 5), $doc->documentElement);
        } elseif (!($actions = evaluateXPath($doc, '//sxml:action[@name=\''.addslashes($_SXML_POST['sxml:action']).'\']', true)) || ($actions->length < 1)) {
            $doc->replaceChild(createError($doc, 7), $doc->documentElement); // Нет такого действия в документе
        } else {
            $_SXML['action'] = $_SXML_POST['sxml:action'];
            $_SXML['laconic'] = !isset($_SXML_POST['sxml:verbose']);
        }
s    }
    
    // Запоминаем, какие страницы нужно будет показать
    if (isset($_SXML_GET['sxml:ranges'])) {
        $pageEntries = explode(':', $_SXML_GET['sxml:ranges']);
        $_SXML['ranges'] = array(); 
        foreach ($pageEntries as $i => $entry) {
            $p = explode('/', $entry);
            $splitted = splitGetString(urldecode($p[0]));
            $_SXML['ranges'][] = array_merge(splitGetString(urldecode($p[0])), array(
                'range' => urldecode($p[1])
            ));
        }
    }
  
    // Обрабатываем документ
    processDocument($doc, $_SXML['hash']);
    
    // Лог отладки
    /*$debug = $doc->createElementNS($SXMLParams['ns'], 'debug-log');
    foreach($_SXMLLog as $i => $entry) {
        $e = $doc->createElementNS($SXMLParams['ns'], 'debug-entry');
        $e->setAttribute('file', $entry[0].':'.$entry[1]);
        $e->setAttribute('text', $entry[2]);
        if (isset($entry[3])) {
            $e->appendChild($doc->createTextNode($entry[3]));
        }
        $debug->appendChild($e);
    }
    $doc->documentElement->appendChild($debug);*/
    
    if ($_SXML_POST['sxml:expect-xml'] || $_COOKIE['sxml:allow-xml']) {
        header('Content-type: application/xml');
        print $doc->saveXML();
    } else {
        $children = $doc->childNodes;
        for ($i = 0; $i < $children->length; $i++) {
            if ($children->item($i)->nodeType == XML_PI_NODE && $children->item($i)->target == 'xml-stylesheet') {
                if (preg_match('/href=\"(.*)\"/', $children->item($i)->data, $matches) > 0) {
                    $stylesheet = resolvePath($matches[1], $_SXML['file']);
                }
            }
        }
        if (!isset($stylesheet) || !file_exists($stylesheet)) {
            header('Content-type: application/xml');
            print $doc->saveXML();
        } else {
            header('Content-type: text/html');
            $proc = new XSLTProcessor();
            $ssheet = DOMDocument::load($stylesheet);
            $proc->importStyleSheet($ssheet);
            echo $proc->transformToXML($doc);
            if (!strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && !$_SXML_POST['sxml:expect-xml']) {
                echo '<!-- Проверка на XSLT --><iframe src="'.$SXMLParams['root'].$SXMLParams['folder'].'/misc/allow_xml.xml" style="display:none"/>';
            }
        }
    }
?>