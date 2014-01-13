<?
    require_once "login.lib.php";
    
    header('Content-type: text/json');
    $user = getUser($_GET['id']);
    print $user? json_encode($user['name']) : json_encode('['.$_GET['id'].']');
?>