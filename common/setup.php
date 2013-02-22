<?
    ///////////
    // Параметры
    ///////////
    $SXMLParams = array(
    
        // Общие параметры
        
        'host' => 'sxml', // site.ru
        'root' => '/', // /project.
        'folder' => 'sxmlight', // Имя папки с обработчиками
        'ns' => 'http://sergets.ru/sxml',
        
        // обработчики ошибок, пути указываются относительно handler.php
        
        'errors' => array(
            '404' => '../handlers/404.php',
            '403' => '../handlers/403.php'
        ),
        
        // Обработчики по типам файлов

        'handlers' => array(
            'xml' => '../handlers/xml.php',
            'xml.php' => '../handlers/xml.php.php',
            'jpg' => '../handlers/jpeg.php',
            'jpeg' => '../handlers/jpeg.php'
        ),
        
        // параметры обработки директорий - индексный файл, обработчик
        
        'dirindex' => array(
            'index.xml.php',
            'index.xml'
        ),
        'dirhandler' => '../handlers/directory.php'

    );
    
    // Дописываем автоматом параметры, которые в некоторых окружениях может быть нужно переопределять явно
    $SXMLParams['docroot'] = $_SERVER['DOCUMENT_ROOT']; // /var/www/site_ru
    $SXMLParams['localroot'] = $SXMLParams['docroot'].$SXMLParams['root']; // /var/www/site_ru/project
?>
