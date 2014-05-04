<?
    require_once "login.lib.php";
    
    if (isset($SXMLConfig['allowTestLogin']) && $SXMLConfig['allowTestLogin']) {
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
                <sxml:error xmlns:sxml="<?=$SXMLConfig['ns']?>">Already logged in</sxml:error>
            <?
        } else {
            doLogin('test:'. $user, $uarr);
            ?><<?='?'?>xml version="1.0"<?='?'?>>
                <sxml:ok xmlns:sxml="<?=$SXMLConfig['ns']?>" sxml:user="test:<?=addslashes($user)?>"/>
            <?
        }
    } else {
        ?>Please set <b>allowTestLogin</b> flag in <b>config.xml</b> to proceed<?
    }
?>