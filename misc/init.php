<?php
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'sergets' && $_SERVER['PHP_AUTH_PW'] !== '7162372') {
    header('WWW-Authenticate: Basic realm="SXML init"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authorization required';
    exit;
} else {
    echo "<p>Done</p>";
}
?>

<?
    mkdir('../../data', 0755);
    mkdir('../../uploads', 0755);
?>