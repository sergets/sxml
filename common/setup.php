<?
    ///////////
    // Параметры
    ///////////
    if (!isset($SXMLParams)) {
    
        $SXMLParams = array(
        
            // Общие параметры
            
            'ns' => 'http://sergets.ru/sxml',
           
            // путь к плагину для логина
            
            'login' => '../login',
            
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
            'dirhandler' => '../handlers/directory.php',
            
            // Список возможных действий
            
            'queries' => array(
                'select',
                'insert',
                'delete',
                'edit',
                'permit-view',
                'permit-edit',
                'query'
            )

        );

        // Дописываем автоматом параметры, которые в некоторых окружениях может быть нужно переопределять явно
        $SXMLParams['docroot'] = $_SERVER['DOCUMENT_ROOT']; // /var/www/site_ru
        $SXMLParams['host'] = $_SERVER['HTTP_HOST']; // site.ru
        $whereAmI = explode('/', substr($_SERVER['PHP_SELF'], 1));
        array_pop($whereAmI); // handler.php
        array_pop($whereAmI); // common
        $SXMLParams['folder'] = array_pop($whereAmI); // sxml
        $SXMLParams['root'] = '/'.join('/', $whereAmI); // / or /project
        $SXMLParams['localroot'] = $SXMLParams['docroot'].$SXMLParams['root']; // /var/www/site_ru/project
        
        // путь к базе данных для PDO
        $SXMLParams['db'] = 'sqlite:'.$SXMLParams['localroot'].'/data/data.sqlite';
    
    }
?>
