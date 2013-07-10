<?
    // Главный обработчик. Именно ему передаётся управление от всех обрабатываемых файлов.
    // Выделяет имя запрошенного файла и запрос, передаёт управление соответствующему обработчику,
    // если файл существует, иначе - странице ошибки.

    error_reporting( E_ERROR + E_WARNING );

    require_once 'setup.php';
    require_once 'common.lib.php';

    require_once($SXMLParams['login'].'/login.inc.php');

    //session_start();

    if (!isset($_SXML)) {
        $_SXML = array();
    }
    $_SXML_GET = array();
    $_SXML_POST = array();
    $_SXML_REQUEST = array();
    $_SXML_VARS = array(
        'user' => $_SXML['user']
    );

    // Разбираем строку запроса на $_SXML['file'], $_SXML['query'] и $_SXML_GET;
    $res = splitGetString($_SERVER['REQUEST_URI']);
    $filelocator = $res['path'];
    $_SXML_GET = $res['get'];
    $_SXML['hash'] = $res['hash'];

    // Разбираем POST-запрос
    foreach (explode('&', file_get_contents("php://input")) as $tok) {
        $_SXML_POST[urldecode(strrstr($tok, '='))] = urldecode(substr(strstr($tok, '='), 1));
    }

    // Ищем файл
    $ready = false;
    $_SXML['file'] = $file = resolvePath($filelocator); // $file - локальное имя файла

    if (!file_exists($file)) {
        // TODO: базовые параметры через слеш, для красоты.

        $ready = true;
        header('HTTP/1.0 404 Not Found');
        include ($SXMLParams['errors']['404']); //

    } elseif (is_dir($file)) {
        $foundindex = false;
        foreach ($SXMLParams['dirindex'] as $i => $indexfile) {
            $indexPath = strrpos($file, '/') === strlen($file) - 1 ? $file.$indexfile : $file.'/'.$indexfile;
            if (file_exists($indexPath)) {
                $_SXML['file'] = $file = $indexPath;
                $foundindex = true;
                break;
            }
        }
        if (!$foundindex) {

            $ready = true;
            header('HTTP/1.0 200 OK');
            include ($SXMLParams['dirhandler']);

        }
    }
    // Дополнительная проверка - теперь это ещё может быть index.xml
    if (!$ready) {
        $fn = substr($file, strrpos($file, '/') + 1);
        $exts = explode('.', $fn);
        $extensions = array(); // ['xml.php', 'php']
        while (count($exts) > 0) {
            array_shift($exts);
            $extensions[] = join($exts, '.');
        }
        foreach($extensions as $i => $extension) {
            if (isset($SXMLParams['handlers'][$extension])) {

                $ready = true;
                header('HTTP/1.0 200 OK');
                include ($SXMLParams['handlers'][$extension]);

                break;
            }
        }
        if (!$ready) {

            // Файл направили на нас в .htaccess'е через 403, но хендлера не нашлось. Значит, это и правда 403.
            $ready = true;
            header('HTTP/1.0 403 Forbidden');
            include ($SXMLParams['errors']['403']);

        }

    }

?>