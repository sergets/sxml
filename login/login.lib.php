<?
    require_once('../common/db.lib.php');
    
    // Инициализиация
    getDB()->query('create table if not exists "sxml:users" ("user" unique, "name", "link", "userpic", "sex")');
    getDB()->query('create table if not exists "sxml:membership" ("user", "group")');
    getDB()->query('create table if not exists "sxml:groups" ("group" unique, "name")');
    // //
    
    function doLogin($user, $hash) {
        global $SXMLParams;

        saveUser($hash);
        $_SESSION['sxml:user'] = $user;
        setcookie('userid_current', $user, 0, $SXMLParams['root']);
        setcookie('userid_remember', $user, time() + 86400, $SXMLParams['root']);
        return true;
        
    }
    
    function doLogout() {
        global $SXMLParams;
    
        unset($_SESSION['sxml:user']);
        setcookie('userid_current', '', time() - 3600, $SXMLParams['root']);
        setcookie('userid_remember', '', time() - 3600, $SXMLParams['root']);
        
    }

    function getGroupsForUser($user) {

        $res = array(
            ''
        );
        $query = getDB()->prepare('select "group" from "sxml:membership" where ("user" = :user)');
        if ($query->execute(array( 'user' => $user ))) {
            foreach ($query->fetchAll() as $i => $row) {
                $res[] = $row['group'];
            }
        }
        return $res;

    }

    
    function addUserToGroup($user, $group) {
        // TODO
    }

    function removeUserFromGroup($user, $group) {
        // TODO
    }
    
    function saveUser($userhash) {
    
        if (!is_array($userhash) || !isset($userhash['user'])) {
            return false;
        } else {
            $h = array(
                'user' => $userhash['user'],
                'name' => $userhash['name'],
                'sex' => $userhash['sex'],
                'link' => $userhash['link'] ? $userhash['link'] : $userhash['user'],
                'userpic' => $userhash['userpic'] ? $userhash['userpic'] : ''
            );
            $query = getDB()->prepare('insert or replace into "sxml:users" ("user", "name", "link", "userpic", "sex") values (:user, :name, :link, :userpic, :sex)');
            return $query->execute($h);
        }

    }
    
    // Возвращает свойства пользователя в виде хеша
    function getUser($userid) {
    
        $query = getDB()->prepare('select * from "sxml:users" where ("user" = :user)');
        $query->execute(array( 'user' => $userid ));
        $result = $query->fetchAll();
        if (count($result) !== 1) {
            return false;
        } else {
            return $result[0];
        }
        
    }

?>