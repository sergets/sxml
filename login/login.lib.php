<?
    require_once('../common/db.lib.php');

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