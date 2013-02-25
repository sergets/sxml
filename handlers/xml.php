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
    $hash = parseHash($_SXML['query']);
    
    processDocument($doc, $hash);
    
    if ($_COOKIE['sxml:allow-xml'] || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
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
            echo '<!-- Проверка на XSLT --><iframe src="'.$SXMLParams['root'].'/'.$SXMLParams['folder'].'/misc/allow_xml.xml" style="display:none"/>';
        }
    }
?>