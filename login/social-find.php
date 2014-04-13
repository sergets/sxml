<?
    require_once 'login.lib.php';
    session_start();
    
    $result = array();
    $find = $_GET['find'];
    
    if (isset($_SESSION['sxml:vk:accessToken'])) {
        $res = requestJSON('https://api.vk.com/method/users.get?uids='.urlencode($find).'&fields=photo_50,sex&access_token='.$_SESSION['sxml:vk:accessToken']);
        $response = isset($res['response']) ? $res['response'] : array();
        foreach ($response as $i => $r) {
            $result[] = array(
                'type' => 'vk',
                'id' => 'vk:' . $r['uid'],
                'name' => $r['first_name'] .' '. $r['last_name'],
                'userpic' => $r['photo_50']
            );
            saveUser(array(
                'provider' => 'vk',
                'user' => 'vk:' . $r['uid'],
                'name' => $r['first_name'] .' '. $r['last_name'],
                'sex' => $r['sex'],
                'link' => false,
                'userpic' => $r['photo_50']
            ));
        }
    }
    
    print json_encode($result);
    
?>