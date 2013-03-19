<?
    require_once('../common/db.lib.php');
    
    function doLogin($user, $additionals) {
    
        $user = $_GET['u'];
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
    
    function getUser($user) {
        // TODO
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