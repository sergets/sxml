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
    
    // TODO: проверка allow_xml и наложение клиентского xsl, если надо.
    
    processDocument($doc, $hash);

    header('Content-type: application/xml');
    print $doc->saveXML();
    
?>