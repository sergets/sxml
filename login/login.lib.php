<?
    require_once('../common/db.lib.php');
    
    function doLogin($user, $additionals) {

        if (!getUser($user)) {
            saveUser($user, $additionals);
        }
        $_SESSION['sxml:user'] = $user;
        setcookie('userid_current', $user, 0, $SXMLParams['root']);
        setcookie('userid_remember', $user, time() + 86400, $SXMLParams['root']);
        
    }
    
    function doLogout() {
    
        unset($_SESSION['sxml:user']);
        setcookie('userid_current', '', time() - 3600, $SXMLParams['root']);
        setcookie('userid_remember', '', time() - 3600, $SXMLParams['root']);
        
    }

    function getGroupsForUser($user) {
        
        return array();
    }
    
    function addUserToGroup($user, $group) {
        // TODO
    }

    function removeUserFromGroup($user, $group) {
        // TODO
    }
    
    function saveUser($user, $additionals) {
        // TODO
    }
    
    // Возвращает свойства пользователя в виде хеша
    function getUser($user) {
        
        $db = getDB();
        $db->exec('create table if not exists "sxml:users" ("user", "name", "link", "userpic", "sex")');
        $result = $db->prepare('select * from "sxml:users" where ("user" = :user)')->exec(array( 'user' => $user ))->fetchAll();
        if (count($result) !== 1) {
            return false;
        } else {
            return $result;
        }
        
    }
    
    // Основная функция, проверяющая правильность логина
    function testLogin($user, $misc = false) {
        return true;
        // TODO
    }
    
    // Возвращает дополнительные параметры пользователя (имя и пр.) в виде хеша
    function getAdditionals($user) {
        return array();
        // TODO
    }

?>