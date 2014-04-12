<?
    header('Content-type: text/xml');
?>
<<?="?"?>xml version="1.0" encoding="utf-8"<?="?"?>><?
    require_once('../../common/setup.php');
    require_once('../../common/sxml.lib.php');
    require_once('../../common/db.lib.php');
    
    require_once($SXMLParams['login'].'/login.inc.php');
    
    // Инициализиация
    getDB()->query('create table if not exists "sxml:uploads" ("hash" unique, "user", "time", "type", "original", "usage")');
    
    $uploaddir = $SXMLParams['localroot'].'/uploads';
    $uploadfile = $uploaddir . basename($_FILES['file']['name']);

    if (stringPermits($SXMLParams['uploaders'], 'open-to')) {
        if (isset($_POST['sxml:token']) && $_POST['sxml:token'] === $_SXML['token']) {
            $hash = $_POST['hash'];
            $file = $SXMLParams['uploaddir'].'/'.$hash;
            if (file_exists($file) || !$hash) {
                ?><error xmlns="<?=$SXMLParams['ns']?>" code="10" message="Файл с таким хешом уже существует"/><?
            } else {
                if (!$_FILES['file']['error'] && move_uploaded_file($_FILES['file']['tmp_name'], $file)) {
                    $query = getDB()->prepare('insert or replace into "sxml:uploads" ("hash", "user", "time", "type", "original", "usage") values (:hash, :user, :time, :type, :original, null)');
                    $query->execute(array(
                        'hash' => $hash,
                        'user' => $_SXML['user'],
                        'time' => date(DATE_ATOM),
                        'type' => $_FILES['file']['type'],
                        'original' => $_FILES['file']['name']
                    ));
                    ?><ok xmlns="<?=$SXMLParams['ns']?>"/><?
                } else {
                    ?><error xmlns="<?=$SXMLParams['ns']?>" code="10" message="Ошибка при загрузке файла"/><?
                }
            }
        } else {
            ?><error xmlns="<?=$SXMLParams['ns']?>" code="5" message="Неправильный токен"/><?
        }
    } else {
        ?><error xmlns="<?=$SXMLParams['ns']?>" code="6" message="Вы не имеете прав на загрузку файлов"/><?
    }
?>