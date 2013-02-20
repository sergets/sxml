<?
    ///////////
    // Параметры
    ///////////
    $SXMLParams = array(
        'host' => 'sxml', // site.ru
        'root' => '/', // /project.
        'folder' => 'sxmlight' // Имя папки с обработчиками
        'ns' => 'http://sergets.ru/sxml'
    );

    $SXMLParams['localroot'] = $_SERVER['DOCUMENT_ROOT'].$SXMLParams['root']; // /var/www/site_ru/project
?>
