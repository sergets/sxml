<?
    // ќбработчик файлов XML
    //
    // SXML-обработчики вызываютс€ скриптом handler.php, который вы€сн€ет
    // им€ файла 'file' и требуемые параметры 'query'
    // и кладЄт их в глобальный массив $_SXML, а также ставит заколовок 200 OK

    require_once '../common/settings.php';
    require_once '../common/proc.lib.php';
    
    $doc = new DOMDocument();
    $doc->load($_SXML['file']);
    processDocument($doc);

    header('Content-type: application/xml');
    print $doc->saveXML();
?>