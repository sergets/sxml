<?
    require_once "login.lib.php";
    
    header('Content-type: text/json');
    $users = findUsers($_GET['find']);
    foreach ($users as $i => $u) {
        $users[$i]['type'] = 'user';
        $users[$i]['id'] = $users[$i]['user'];
    }
    $groups = findGroups($_GET['find']);
    foreach ($groups as $i => $g) {
        $groups[$i]['type'] = 'group';    
        $groups[$i]['id'] = '#' . $groups[$i]['group'];
    }
    print json_encode(array_merge($users, $groups));
?>