<?
    require_once "login.lib.php";
    require_once "../common/setup.php";
    
    session_start();
    
    if (isset($_SESSION['sxml:user'])) {
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="<?=$SXMLParams['ns']?>">Already logged in</sxml:error>
        <?
    } elseif (testLogin($_GET['u'])) { // TODO Нужна осмысленная проверка логина
        doLogin($user);
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:ok xmlns:sxml="<?=$SXMLParams['ns']?>" sxml:user="<?=addslashes($user)?>>"/>
        <?
    } else {
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="<?=$SXMLParams['ns']?>">Login sequence failed</sxml:error>
        <?
    }
?>