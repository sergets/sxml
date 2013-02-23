<?
    require_once "login.lib.php";
    
    session_start();
    
    if (testLogin($_GET['u'])) { // TODO Нужна осмысленная проверка логина
        $user = $_GET['u'];
        if (!getUser($user)) {
            saveUser($user, getAdditionals($user));
        }
        $_SESSION['sxml:user'] = $user;
        // TODO что отвечать
    } else {
        // TODO выдать ошибку
    }
?>