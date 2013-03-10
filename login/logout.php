<?
    require_once "login.lib.php";
    require_once "../common/setup.php";
    
    session_start();
    
    unset($_SESSION['sxml:user']);
    setcookie('userid_current', '', time() - 3600, $SXMLParams['root']);
    setcookie('userid_remember', '', time() - 3600, $SXMLParams['root']);
   
    ?><<?='?'?>xml version="1.0"<?='?'?>>
        <ok xmlns="http://sergets.ru/sxml"/>
    <?
?>