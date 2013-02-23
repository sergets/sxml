<?
    require_once 'login.lib.php';

    session_start();
    
    if (!isset($_SXML)) {
        $_SXML = array();
    }
    
    $_SXML['user'] = '';
    $_SXML['groups'] = array();
    
    if (isset($_SESSION['sxml:user'])) {
        $_SXML['user'] = $_SESSION['sxml:user'];
        $_SXML['groups'] = getGroupsForUser[$_SXML['user']];
    }
?>
