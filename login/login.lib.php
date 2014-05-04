<?
    require_once(dirname(__FILE__).'/../common/db.lib.php');
    
    // Инициализиация
    getDB()->query('create table if not exists "sxml:users" ("provider", "user" unique, "name", "link", "userpic", "sex")');
    getDB()->query('create table if not exists "sxml:membership" ("user", "group")');
    getDB()->query('create table if not exists "sxml:groups" ("group" unique, "name")');
    getDB()->query('insert or replace into "sxml:groups" ("group", "name") values (\'\', \'Все залогиненные\')');

    
    // //
    
    function doLogin($user, $hash) {
        global $SXMLConfig;

        saveUser($hash);
        $_SESSION['sxml:user'] = $user;
        //setcookie('userid_current', $user, 0, $SXMLConfig['root']);
        setcookie('sxml:remembered_provider', $hash['provider'], time() + 86400, $SXMLConfig['root']);
        return true;
        
    }
    
    function doLogout() {
        global $SXMLConfig;
    
        unset($_SESSION['sxml:user']);
        //setcookie('userid_current', '', time() - 3600, $SXMLConfig['root']);
        setcookie('sxml:remembered_provider', '', time() - 3600, $SXMLConfig['root']);
        
    }

    function getGroupsForUser($user) {

        $res = array(
            ''
        );
        $query = getDB()->prepare('select "group" from "sxml:membership" where ("user" = :user)');
        if ($query && $query->execute(array( 'user' => $user ))) {
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
                'provider' => $userhash['provider'],
                'user' => $userhash['user'],
                'name' => $userhash['name'],
                'sex' => $userhash['sex'],
                'link' => $userhash['link'] ? $userhash['link'] : $userhash['user'],
                'userpic' => $userhash['userpic'] ? $userhash['userpic'] : ''
            );
            $query = getDB()->prepare('insert or replace into "sxml:users" ("provider", "user", "name", "link", "userpic", "sex") values (:provider, :user, :name, :link, :userpic, :sex)');
            return $query->execute($h);
        }

    }
    
    // Возвращает свойства пользователя в виде хеша
    function getUser($userid) {
    
        $query = getDB()->prepare('select * from "sxml:users" where ("user" = :user)');
        if (!$query) return array();
        $query->execute(array( 'user' => $userid ));
        $result = $query->fetchAll();
        if (count($result) !== 1) {
            return false;
        } else {
            return $result[0];
        }
        
    }
    
    // Возвращает свойства группы в виде хеша
    function getGroup($id) {
    
        $query = getDB()->prepare('select * from "sxml:groups" where ("group" = :gr)');
        if (!$query) return array();
        $query->execute(array( 'gr' => $id ));
        $result = $query->fetchAll();
        if (count($result) !== 1) {
            return false;
        } else {
            return $result[0];
        }
        
    }
    
    // Возвращает список
    function findUsers($str) {

        $query = getDB()->prepare('select * from "sxml:users" where ("name" like "%" || :str || "%")');
        if (!$query) return array();
        $query->execute(array( 'str' => $str ));
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        return $result;

    }
    
    // Возвращает список
    function findGroups($str) {

        $query = getDB()->prepare('select * from "sxml:groups" where ("name" like "%" || :str || "%")');
        if (!$query) return array();
        $query->execute(array( 'str' => $str ));
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        return $result;

    }
    

    /////////////
    
    function redirect($url) {
        header('HTTP/1.1 302 Found');
        header('Location: '.$url);
    }
    
    function requestJSON($url, $post = null) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        if (is_array($post)) {
            curl_setopt($curl, CURLOPT_POST, $post);
        }
        $r = curl_exec($curl);
        if (!$r) {
            error(curl_error($curl));
            return false;
        } else {
            return json_decode($r, true);
        }
    }    
    
?>