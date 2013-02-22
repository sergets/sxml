<?
    // ������� ����������. ������ ��� ��������� ���������� �� ���� �������������� ������.
    // �������� ��� ������������ ����� � ������, ������� ���������� ���������������� �����������,
    // ���� ���� ����������, ����� - �������� ������.
    
    require_once 'setup.php';
    require_once 'common.lib.php';
    
    session_start();
    
    if (!isset($_SXML)) {
        $_SXML = array();
    }
    $_SXML_GET = array();
    $_SXML_POST = array();
    $_SXML_REQUEST = array();

    $stderr = fopen('php://stderr', 'w');
    fwrite($stderr, 'Errors would be here');

    $userid = 'sergets'; // $_SESSION['userid'];
    error_reporting( E_ERROR + E_WARNING ); 
    
    // ��������� ������ ������� �� $_SXML['file'], $_SXML['query'] � $_SXML_GET;
    if (false !== ($p = strpos($_SERVER['REQUEST_URI'], '?'))) {
        $filelocator = substr($_SERVER['REQUEST_URI'], 0, $p);
        $querystring = substr($_SERVER['REQUEST_URI'], $p + 1);
        $gets = explode('&', $querystring);
        if (strpos($gets[count($gets)-1], '=') === false) {
            $_SXML['query'] = $gets[count($gets)-1];
            array_pop($gets);
        } else {
            $_SXML['query'] = '';
        }
        foreach($gets as $i => $get) {
            if (false !== ($p = strpos($get, '='))) { // �� explode, ������ ��� ����� ���� ������ ������ ����� ���������, � ������ ������ ������.
                $_SXML_GET[substr($get, 0, $p)] = substr($get, $p + 1);
            } else {
                $_SXML_GET[substr($get)] = '';
            }
        }
    } else {
        $filelocator = $_SERVER['REQUEST_URI'];
    }
    
    // ���� ����
    $ready = false;
    $_SXML['file'] = $file = resolvePath($filelocator); // $file - ��������� ��� �����
    if (!file_exists($file)) {
    
        $ready = true;
        header('HTTP/1.0 404 Not Found');
        include ($SXMLParams['errors']['404']); // 
    
    } elseif (is_dir($file)) {
        if (!file_exists($file.'/'.$SXMLParams['dirindex'])) {
            $_SXML['file'] = $file = $file.'/'.$SXMLParams['dirindex'];
        } else {
            
            $ready = true;
            header('HTTP/1.0 200 OK');
            include ($SXMLParams['dirhandler']);
            
        }
    }
    // �������������� �������� - ������ ��� ��� ����� ���� index.xml
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
        
            // ���� ��������� �� ��� � .htaccess'� ����� 403, �� �������� �� �������. ������, ��� � ������ 403.
            $ready = true;
            header('HTTP/1.0 403 Forbidden');
            include ($SXMLParams['errors']['403']);      
        
        }
    
    }
    
?>