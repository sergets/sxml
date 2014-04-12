<?
    ///////////
    // Параметры
    ///////////
    if (!isset($SXMLParams)) {
    
        $SXMLParams = array(
        
            // Общие параметры
            
            'ns' => 'http://sergets.ru/sxml',
           
            // обработчики ошибок, пути указываются относительно handler.php
            
            'specialHandlers' => array(
                '404' => '../handlers/404.php',
                '403' => '../handlers/403.php',
                'upload' => '../handlers/upload.php',
                'dir' => '../handlers/directory.php'
            ),
            
            // Обработчики по типам файлов

            'handlers' => array(
                'xml' => '../handlers/xml.php',
                'xsl' => '../handlers/xsl.php',
                'xml.php' => '../handlers/xml.php.php',
                'xcss' => '../handlers/css.php',
                'jpg' => '../handlers/jpeg.php',
                'jpeg' => '../handlers/jpeg.php'
            ),
                        
            // параметры обработки директорий - индексный файл, обработчик
            
            'dirindex' => array(
                'index.xml.php',
                'index.xml'
            ),
            
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
        do {
            array_pop($whereAmI); // handler.php
            $curfldr = $SXMLParams['docroot'] . '/' . join('/', $whereAmI);
        } while (!file_exists($curfldr . '/client'));
        $SXMLParams['folder'] = array_pop($whereAmI); // sxml
        $SXMLParams['root'] = '/'.join('/', $whereAmI).((count($whereAmI) > 0) ? '/' : ''); // / or /project/
        $SXMLParams['localroot'] = $SXMLParams['docroot'].$SXMLParams['root']; // /var/www/site_ru/project/
        $SXMLParams['sxml'] = $SXMLParams['localroot'].$SXMLParams['folder'];
        
        // путь к плагину для логина
        $SXMLParams['login'] = $SXMLParams['sxml'].'/login';
        
        // путь к базе данных для PDO
        $SXMLParams['data'] = $SXMLParams['localroot'].'data';
        $SXMLParams['db'] = $SXMLParams['data'].'/data.sqlite';
        
        // разрешённые типы данных для загрузки файлов
        $SXMLParams['accept'] = array('image/jpeg', 'image/png');
        $SXMLParams['uploaders'] = '#';
        $SXMLParams['uploaddir'] = $SXMLParams['localroot'].'uploads';
    
    }
?>
