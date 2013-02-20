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
    processDocument($doc);

    header('Content-type: application/xml');
    print $doc->saveXML();
?>