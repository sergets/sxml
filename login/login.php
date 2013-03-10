<?
    require_once "login.lib.php";
    require_once "../common/setup.php";
    
    session_start();
    
    if (isset($_SESSION['sxml:user'])) {
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="http://sergets.ru/sxml">Already logged in</sxml:error>
        <?
    } elseif (testLogin($_GET['u'])) { // TODO Нужна осмысленная проверка логина
        $user = $_GET['u'];
        if (!getUser($user)) {
            saveUser($user, getAdditionals($user));
        }
        $_SESSION['sxml:user'] = $user;
        setcookie('userid_current', $user, 0, $SXMLParams['root']);
        setcookie('userid_remember', $user, time() + 86400, $SXMLParams['root']);
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:ok xmlns:sxml="http://sergets.ru/sxml" sxml:user="<?=addslashes($user)?>>"/>
        <?
    } else {
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="http://sergets.ru/sxml">Login sequence failed</sxml:error>
        <?
    }
?>