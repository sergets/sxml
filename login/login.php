<?
    require_once "login.lib.php";
    require_once "../common/setup.php";
    
    session_start();
    
    $user = $_GET['u'];
    $uarr = array(
        'user' => 'test:' . $user,
        'name' => 'Test user «'.$user.'»',
        'provider' => 'test',
        'sex' => 'm',
        'link' => 'http://sergets.ru/',
        'userpic' => 'https://avatars3.githubusercontent.com/u/1742292?s=50'
    );
    
    if (isset($_SESSION['sxml:user'])) {
        doLogin('test:'. $user, $uarr);
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="<?=$SXMLParams['ns']?>">Already logged in</sxml:error>
        <?
    } elseif (1) { // TODO Нужна осмысленная проверка логина
        doLogin('test:'. $user, $uarr);
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:ok xmlns:sxml="<?=$SXMLParams['ns']?>" sxml:user="test:<?=addslashes($user)?>"/>
        <?
    } else {
        ?><<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error xmlns:sxml="<?=$SXMLParams['ns']?>">Login sequence failed</sxml:error>
        <?
    }
?>