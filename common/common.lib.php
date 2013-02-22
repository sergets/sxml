<?
    ///////////
    // �������
    ///////////
    
    require_once 'setup.php';

    // ����������, �������� �� $path ������������� ������� � � ����� ������ ����������� ��� � ���������� ����. ���� ���� �� ������������� � ����������,
    // �� ���������� false
    function resolvePath($path, $baseURI = '') {
        global $SXMLParams;
        // ��������� �������� $path: 
        // - [http:]//sergets.ru/dir/dir[/../..]/file.txt
        // - /dir/dir[/../..]/path (�� DOCUMENT_ROOT)
        // - dir[/..]/path
        // ���� baseURI - ������ ������, �� ��� $_SERVER['PHP_SELF'];
        // baseURI ���������� � UNIX-���� (������ �����).
        
        if ($baseURI == '') {
            $baseURI = $SXMLParams['docroot'];
        }
        $baseURI = str_replace('\\', '/', $baseURI);
        if (!is_dir($baseURI)) {
            $baseURI = substr($baseURI, 0, strrpos($baseURI, '/'));
        }
        
        if (($s = strpos($path, '//'.$SXMLParams['host'])) !== false) {
            $path = substr($path, $s + strlen('//'.$SXMLParams['host'])); // [http:]//sergets.ru/dir/ -> /dir/
            if ($path == '') $path = '/';
        } else if (strpos($path, '//') !== false) { 
            return false; // ������ ����
        }
        if (strpos($path, '/') === 0) {
            $path = str_replace('\\', '/', $SXMLParams['docroot']).$path;
        } else {
            $path = $baseURI.'/'.$path;
        }
        if (strpos($path, '..') === 0 || strpos($path, '/..') === 0) {
            return false;
        }
        $path = str_replace('/./', '/', $path);
        while (strpos($path, '/..') !== false) {
            $path = preg_replace('/\/([^\/]*[^\/\.][^\/]*)\/\.\./', '', $path);
        }

        if (strpos($path, str_replace('\\', '/', $SXMLParams['localroot'])) !== 0) {
            return false;
        } else {
            return $path;
        }
    }

    function local2global($path) {
        // TODO
        return 'http://?/'.$path;
    }
?>