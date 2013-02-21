<?
    // ���������� ������ XML
    //
    // SXML-����������� ���������� �������� handler.php, ������� ��������
    // ��� ����� 'file' � ��������� ��������� 'query'
    // � ����� �� � ���������� ������ $_SXML, � ����� ������ ��������� 200 OK

    require_once '../common/settings.php';
    require_once '../common/proc.lib.php';
    
    $doc = new DOMDocument();
    $doc->load($_SXML['file']);
    
    $hash = array();
    
    if (strpos($_SXML['query'], '/') !== false) {
        $q = explode($_SXML['query'], '/');
        $hash['range'] = $q[1];
        $bl = $q[0];
    } else {
        $bl = $_SXML['query'];
    }
    
    if (strpos($bl, ':') !== false) {
        $q = explode($bl, ':');
        $hash['class'] = $q[0];
        $hash['inst'] = $q[1];
    } else {
        $hash['id'] = $bl;
    }
    
    // TODO: �������� allow_xml � ��������� ����������� xsl, ���� ����.
    
    processDocument($doc, $hash);

    header('Content-type: application/xml');
    print $doc->saveXML();
    
?>