<?
    require_once "login.lib.php";
    require_once "../common/setup.php";
    
    session_start();
    
    $user = $_GET['u'];
    
    if (isset($_SESSION['sxml:user'])) {
        doLogin($user, array('user' => $user, 'name' => 'Test user "'.$user.'"') );
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="<?=$SXMLParams['ns']?>">Already logged in</sxml:error>
        <?
    } elseif (1) { // TODO Нужна осмысленная проверка логина
        doLogin($user, array('user' => $user, 'name' => 'Test user "'.$user.'"') );
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:ok xmlns:sxml="<?=$SXMLParams['ns']?>" sxml:user="<?=addslashes($user)?>>"/>
        <?
    } else {
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="<?=$SXMLParams['ns']?>">Login sequence failed</sxml:error>
        <?
    }
?>