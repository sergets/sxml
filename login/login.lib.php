<?
    require_once('../common/db.lib.php');
    
    // ��������������
    getDB()->query('create table if not exists "sxml:users" ("user" unique, "name", "link", "userpic", "sex")');
    getDB()->query('create table if not exists "sxml:usersgroups" ("user", "group")');
    getDB()->query('create table if not exists "sxml:groups" ("group", "name")');
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
    
        unset($_SESSION['sxml:user']);
        setcookie('userid_current', '', time() - 3600, $SXMLParams['root']);
        setcookie('userid_remember', '', time() - 3600, $SXMLParams['root']);
        
    }

    function getGroupsForUser($user) {

        /*$query = getDB()->prepare('select "sxml:groups".* from ("sxml:usersgroups" inner join "sxml:groups" on "sxml:usersgroups"."group" = "sxml:groups"."group") where ("sxml:usersgroups"."user" = :user)');
        if ($query->execute(array( 'user' => $userid ))) {
            return $query->fetchAll();
        } else {
            return array();
        }*/
        

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
    
    // ���������� �������� ������������ � ���� ����
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