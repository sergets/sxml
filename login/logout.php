<?
    require_once "login.lib.php";
    require_once "../common/setup.php";
    
    session_start();
    doLogout($user);
    ?><<?='?'?>xml version="1.0"<?='?'?>>
        <ok xmlns="http://sergets.ru/sxml"/>
    <?
?>