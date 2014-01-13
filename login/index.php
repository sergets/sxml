<?
    require_once('login.lib.php');
    require_once('login.inc.php');
    require_once('../common/setup.php');
    
    ///// 
    
    // Подробности настроек протокола
    
    if (!isset($_SESSION['oauth:random_key'])) {
        $_SESSION['oauth:random_key'] = substr(md5(rand()), 0, 10);
    }
    $OAuthSetup = array(
        'self' => 'http://'.$SXMLParams['host'].$SXMLParams['root'].$SXMLParams['folder'].'/login/?sxml:oauthkey='.$_SESSION['oauth:random_key'],
        'vk_id' => '1931312', // '2441664', // '3542713',
        'vk_secret' => 'YhKsOqqRNwv96hV9qybE' // '0fDYmRanA9f3DQpVoNGB' // 'pSdxVtb7COUga0sZEAQP'
    );
    
    
    // Имя пользователя в том виде, в каком оно будет в дальнейшем использоваться у нас
    function getLocalUsername($provider, $user) {
        return $provider.':'.$user;
    }
    
    // Адрес, на который ведёт кнопочка "залогиниться"
    function getProviderLoginPoint($provider) {
        global $OAuthSetup;
        
        if ($provider == 'vk') {
            return 'http://oauth.vk.com/authorize?client_id='.$OAuthSetup['vk_id'].'&redirect_uri='.urlencode($OAuthSetup['self'].'&sxml:provider=vk').'&response_type=code';
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
        global $OAuthSetup;
        
        if ($provider == 'vk') {
            return 'https://oauth.vk.com/access_token?client_id='.$OAuthSetup['vk_id'].'&client_secret='.$OAuthSetup['vk_secret'].'&redirect_uri='.urlencode($OAuthSetup['self'].'&sxml:provider=vk').'&code='.urlencode($code);
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
            return 'https://api.vk.com/method/users.get?uids='.urlencode($token['user']).'&fields=uid,first_name,last_name,photo_50,sex,screen_name&access_token='.urlencode($token['token']);
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
                $_SESSION['sxml:vk:accessToken'] = $results['access_token'];
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
            if (isset($results['uid']) && isset($results['first_name']) && isset($results['last_name']) && isset($results['photo_50'])) {
                switch ($results['sex']) {
                    case '1': $sex = 'f'; break;
                    case '2': $sex = 'm'; break;
                    default: $sex = 'x'; break;
                }
                return array(
                    'provider' => 'vk',
                    'user' => 'vk:'.$results['uid'],
                    'name' => $results['first_name'] .' '. $results['last_name'],
                    'sex' => $sex,
                    'userpic' => $results['photo_50'],
                    'link' => 'vk.com/'.$results['screen_name']
                );
            }
        }
        return false;
    }

    /////
    
    // Результирующие действия
    
    function success($provider = null, $username = null, $additionals = null) {
        global $SXMLParams;
        
        if (!$provider) {
            doLogout();
        } else {
            doLogin($username, $additionals);
        }
        $groups = getGroupsForUser($username);
        foreach($groups as $i => $gr) {
            $groups[$i] = addslashes($gr);
        }
        header('HTTP/1.1 200 OK');
        header('Content-type: text/html');
        ?><html><body><script type="text/javascript">
            (window.opener || window.top)._sxml.trigger('window', {
                winId : window.name,
                isOK : true,
                user : '<?=addslashes($username)?>',
                users : [
                    {
                        id : '<?=addslashes($username)?>',
                        name : '<?=addslashes($additionals['name'])?>',
                        link : '<?=addslashes($additionals['link'])?>'
                    }
                ],
                groups : [
                    '<?=join('\', \'', $groups)?>'
                ]
            });
        </script></body></html><?
    }
    
    function error($message) {
        global $SXMLParams;
        
        header('HTTP/1.1 200 OK');
        header('Content-type: text/html');
        ?><html><script type="text/javascript">
            (window.opener || window.top)._sxml.trigger('window', {
                winId : window.name,
                isOK : false,
                errorMessage : '<?=addslashes($message)?>'
            });
        </script></html><?
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
            return parseUserinfo($provider, $JSON['response'][0]);
        } else {
            return false;
        }
    }
    
    /////
    
    // Основной код
    
    if (isset($_REQUEST['sxml:logout'])) {
        success();
        exit(0);
    }
    
    $provider = $_REQUEST['sxml:provider'];
    if (!$provider) {
        error('Unknown provider');
    } else {
        if (!isset($_REQUEST['sxml:oauthkey'])) {
            // Мы здесь первый раз
            if (!proceedToProvider($provider)) {
                error('Unable to proceed: unknown provider');
            }
        } elseif ($_REQUEST['sxml:oauthkey'] !== $_SESSION['oauth:random_key']) { 
            error('Unable to proceed: key mismatch');
        } else {
            $code = parseProviderLoginCode($provider);
            if (!$code) {
                error(parseProviderLoginError($provider));
            } else {
                $token = testCode($provider, $code);
                if (!$token) {
                    error('Unable to test token');
                } else {
                    $userinfo = getUserinfo($provider, $token);
                    if (!$userinfo) {
                        error('Unable to fetch userinfo');
                    } else {
                        success($provider, $userinfo['user'], $userinfo);
                    }
                }
            }
        }
    }
    
?>