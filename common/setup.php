<?
    ///////////
    // ���������
    ///////////
    $SXMLParams = array(
    
        // ����� ���������
        
        'host' => 'sxml', // site.ru
        'root' => '/', // /project.
        'folder' => 'sxmlight', // ��� ����� � �������������
        'ns' => 'http://sergets.ru/sxml',
        
        // ����������� ������, ���� ����������� ������������ handler.php
        
        'errors' => array(
            '404' => '../handlers/404.php',
            '403' => '../handlers/403.php'
        ),
        
        // ����������� �� ����� ������

        'handlers' => array(
            'xml' => '../handlers/xml.php',
            'xml.php' => '../handlers/xml.php.php',
            'jpg' => '../handlers/jpeg.php',
            'jpeg' => '../handlers/jpeg.php'
        ),
        
        // ��������� ��������� ���������� - ��������� ����, ����������
        
        'dirindex' => array(
            'index.xml.php',
            'index.xml'
        ),
        'dirhandler' => '../handlers/directory.php'

    );
    
    // ���������� ��������� ���������, ������� � ��������� ���������� ����� ���� ����� �������������� ����
    $SXMLParams['localroot'] = $_SERVER['DOCUMENT_ROOT'].$SXMLParams['root']; // /var/www/site_ru/project
?>
