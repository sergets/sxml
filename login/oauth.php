<?
    require_once('login.lib.php');
    require_once('login.inc.php');
    require_once('../common/setup.php');
    
    function redirect($url) {
        header('HTTP/1.1 302 Found');
        header('Location: '.$url);
    }
    
    function requestJSON($url, $post) {
        // TODO
        return array(); // JSON.parse(...);
    }
    
    ///// 
    
    // Подробности настроек протокола
    
    $_SESSION['oauth:random_key'] = substr(md5(rand()), 0, 10);
    $self = 'ugorodaika.ru/sxmlight/sxmlight/oauth.php?sxml:oauthkey='.$_SESSION['oauth:random_key'];
    
    // Имя пользователя в том виде, в каком оно будет в дальнейшем использоваться у нас
    function getLocalUsername($provider, $user) {
        return $provider.':'.$user;
    }
    
    // Адрес, на который ведёт кнопочка "залогиниться"
    function getProviderLoginPoint($provider) {
        if ($provider == 'vk') {
            return 'http://oauth.vk.com/authorize?client_id=2441664&redirect_uri='.urlencode($self.'&sxml:provider=vk').'&response_type=code';
        }
        return false;
    }
    
    // Вернуть код из строки
    function parseProviderLoginCode($provider) {
        if ($_GET['code']) {
            return $_GET['code'];
        } else {
            return false;
        }
    }
    
    // Вернуть ошибку из строки
    function parseProviderLoginError($provider) {
        if ($_GET['error_description']) {
            return $_GET['error_description'];
        } else {
            return 'Provider returned a login error';
        }
    }
    
    // Запрос, который нужно сделать для проверки кода
    function getProviderValidationPoint($provider, $code) {
        if ($provider == 'vk') {
            return 'https://oauth.vk.com/access_token?client_id=2441664&client_secret=0fDYmRanA9f3DQpVoNGB&code='.urlencode($code);
        }
        return false;
    }
    
    // POST-параметры, которые нужно передать    
    function getProviderValidationParams($provider, $code) {
        if ($provider == 'vk') {
            return false;
        }
        return false;
    }
    
    // Адрес, на который нужно идти за информацией о пользователе.
    function getProviderUserinfoPoint($provider, $token) {
        if ($provider == 'vk') {
            return 'https://api.vk.com/method/users.get?uids='.urlencode($token['user']).'&fields=uid,first_name,last_name,photo50,sex,screen_name&access_token='.urlencode($token['user']);
        }
        return false;
    }
    
    // POST-параметры (в общем, не бывает)
    function getProviderUserinfoParams($provider, $token) {
        return false;
    }
    
    // Возвращает токен (для ВК - массив из двух членов - token и user)
    function parseToken($provider, $results) {
        if ($provider == 'vk') {
            if (isset($results['access_token']) && isset($results['user_id'])) {
                return array(
                    'token' => $results['access_token'],
                    'user' => $results['user_id']
                );
            }
        }
        return false;
    }
    
    // Возвращает информацию о пользователе (массив: user, name, sex, userpic, link)
    function parseUserinfo($provider, $results) {
        if ($provider == 'vk') {
            if (isset($results['uid']) && isset($results['first_name']) && isset($results['last_name']) && isset($results['photo50'])) {
                return array(
                    'user' => $results['uid'],
                    'name' => $results['first_name'] .' '. $results['last_name']
                    'sex' => $results['sex'],
                    'additionals' => $results['userpic'],
                    'link' => 'vk.com/'.$results['screen_name']
                );
            }
        }
        return false;
    }

    /////
    
    // Результирующие действия
    
    function success($provider, $username, $additionals) {
        global SXMLParams;
        
        doLogin($username, $additionals);
        header('HTTP/1.1 200 OK');
        header('Content-type: application/xml');
        ?>
            <<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:ok action="login" xmlns:sxml="<?=$SXMLParams['ns']?>"><sxml:update login-dependent="yes"/></sxml:ok>
        <?
    }
    
    function error($message) {
        header('HTTP/1.1 200 OK');
        header('Content-type: application/xml');
        ?>
            <<?='?'?>xml version="1.0"<?='?'?>>
            <sxml:error action="login" xmlns:sxml="<?=$SXMLParams['ns']?>"><?=htmlspecialchars($message)?></sxml:ok>
        <?
    }
    
    /////
    
    // Отдельные шаги протокола OAuth
    
    // Шаг 1. Перенаправляет пользователя на нужную ссылку провайдера - аналог первой кнопки
    function proceedToProvider($provider) {
        if (getProviderLoginPoint($provider)) {
            redirect(getProviderLoginPoint($provider));
            return true;
        } else {
            return false;
        }
    }
    
    // Шаг 2. Проверяет ответ от провайдера, возвращает token (для ВК - token и user).
    function testCode($provider, $code) {
        $JSON = requestJSON(getProviderValidationPoint($provider, $code), getProviderValidationParams($provider, $code));
        if ($JSON) {
            return parseToken($provider, $JSON);
        } else {
            return false;
        }
    }
    
    // Шаг 3. Запрашивает информацию о пользователе
    function getUserinfo($provider, $token) {
        $JSON = requestJSON(getProviderUserinfoPoint($provider, $token), getProviderUserinfoParams($provider, $token));
        if ($JSON) {
            return parseUserinfo($provider, $JSON);
        } else {
            return false;
        }
    }
    
    /////
    
    // Основной код
    
    $provider = $_REQUEST['sxml:provider'];
    if ($provider) {
        error('Unknown provider');
    } else {
        if (!isset($_REQUEST['sxml:oauthkey']) || ($_REQUEST['sxml:oauthkey'] !== $_SESSION['oauth:random_key'])) {
            // Мы здесь первый раз
            if (!proceedToProvider($provider)) {
                error('Unable to proceed: unknown provider');
            }
        } else {
            $code = parseProviderLoginCode($provider);
            if (!$code) {
                error(parseProviderLoginError($provider));
            } else {
                $token = testCode($provider, $code);
                if (!$token) {
                    error('Unable to test token');
                } else {
                    $userInfo = getUserinfo($provider, $token);
                    if (!$userInfo) {
                        error('Unable to fetch userinfo');
                    } else {
                        success($provider, $userinfo['user'], $userinfo);
                    }
                }
            }
        }
    }
    
?>