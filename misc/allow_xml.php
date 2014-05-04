<?
    session_start();

    require_once '../common/common.lib.php';
    
    setcookie('sxml:allow-xml', 'true', time() + time()+60*60*24*365, $SXMLConfig['root']);
?>