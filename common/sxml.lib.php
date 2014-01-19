<?
    // Модуль sxml-процессора SXMLight
    // Получает на вход входной xml-документ, возвращает его, с обработанными sxml-инструкциями 

    require_once 'setup.php';
    require_once 'common.lib.php';
    require_once 'db.lib.php';

    // Кеши для часто встречающихся объектов
    $_xpaths = array();
    $_xpathDocs = array();

    ///////////////
    
    // Шотркаты для неймспейсных атрибутов
    function hasSXMLAttr($el, $attr) {
        global $SXMLParams;
        return $el->hasAttributeNS($SXMLParams['ns'], $attr);
    }
    
    function getSXMLAttr($el, $attr) {
        global $SXMLParams;
        return $el->getAttributeNS($SXMLParams['ns'], $attr);
    }
    
    function removeSXMLAttr($el, $attr) {
        global $SXMLParams;
        return $el->removeAttributeNS($SXMLParams['ns'], $attr);
    }
    
    function setSXMLAttr($el, $attr, $val) {
        global $SXMLParams;
        return $el->setAttributeNS($SXMLParams['ns'], 'sxml:' . $attr, $val);
    }
    
    function createSXMLElem($doc, $name) {
        global $SXMLParams;
        return $doc->createElementNS($SXMLParams['ns'], $name, $val);
    }

    // Возвращает объект DOMXPath.
    function getXPath($doc) {
        global $_xpaths, $_xpathDocs, $_SXML_GET;
        // TODO - профилировать - оно вообще надо?
        $uid = md5($doc->saveXML() . $doc->documentURI . join($_SXML_GET));
        if (isset($_xpaths[$uid]) && $_xpathDocs[$uid] === $doc) {
            return $_xpaths[$uid];
        } else {
            $_xpaths[$uid] = new DOMXPath($doc);
            $_xpaths[$uid]->registerNamespace('sxml', 'http://sergets.ru/sxml');
            $_xpathDocs[$uid] = $doc;
            return $_xpaths[$uid];
        }
    }
    
    // Шорткат для xpath. Третий параметр: если true, то делать query, иначе evaluate;
    function evaluateXPath($el, $xpath, $forceNodeList = false) {
        if (!isset($el->ownerDocument)) {
            if (!$forceNodeList) {
                return getXPath($el)->evaluate($xpath);
            } else {
                return getXPath($el)->query($xpath);
            }
        } else {
            if (!$forceNodeList) {
                return getXPath($el->ownerDocument)->evaluate($xpath, $el);
            } else {
                return getXPath($el->ownerDocument)->query($xpath, $el);
            }
        }
    }

    // Ищет в документе блок, указанный с помощью hash, возвращает DOMNode.
    // Если блока нет, выставляет глобальную переменную _SXML['inSearch'] и возвращает весь документ
    function findBlock($doc, $hash = array()) {
        global $_SXML;
        if (isset($hash['id'])) {
            $blocks = evaluateXPath($doc, '//*[@sxml:id=\''.addslashes($hash['id']).'\']');
            if ($blocks->length > 0) {
                $block = $blocks->item(0);
            } else {
                $_SXML['inSearch'] = $hash;
                $block = $doc->documentElement;
            }
        } elseif (isset($hash['class']) && isset($hash['inst'])) {
            $blocks = evaluateXPath($doc, '//*[@sxml:class=\''.addslashes($hash['class']).'\' and @sxml:item-id=\''.addslashes($hash['inst']).'\']');
            if ($blocks->length > 0) {
                $block = $blocks->item(0);
            } else {
                $_SXML['inSearch'] = $hash;
                $block = $doc->documentElement;
            }
        } else {
            $block = $doc->documentElement;
        }
        return $block;
    }
    
    // Проверяет, подходит ли блок под описание
    function matchesHash($block, $hash) {
    
        return(
            (isset($hash['id']) && hasSXMLAttr($block, 'id') && getSXMLAttr($block, 'id') == $hash['id']) ||
            (isset($hash['class']) && hasSXMLAttr($block, 'class') && hasSXMLAttr($block, 'item-id')
                && getSXMLAttr($block, 'class') == $hash['class'] && getSXMLAttr($block, 'item-id') == $hash['inst'] )
        );
    
    }
    
    // Проверяет, не этот ли блок мы ищем
    function isSearched($block) {
        global $_SXML;
        
        return matchesHash($block, $_SXML['inSearch']);
    }

    // Загружает и парсит (xml) файл c локального пути $from для инклуда, возвращает DOMNode c выставленными included-from и т. п.
    // hash - указание блока (массив с возможными ключами: range, class+inst, id)
    function fetch($from, $hash = array()) {
        global $SXMLParams;
        // Здесь нужно дописать парсеры урлов, в частности *.xml.php.
        $doc = new DOMDocument();
        if (!$from) {
            return null;
        }
        $doc->load($from);
        $block = findBlock($doc, $hash);
        
        // Про enumerables. Может статься, что у нас достаточно умный инклудимый скрипт,
        // который и не возвращает нам лишних страниц. Тогда он должен на этот блок установить
        // величины sxml:original-range и sxml:total, чтобы мы знали, что нам выдали.
        // Далее, в этой функции мы вешаем на блок атрибут sxml:range, на основании которого
        // и будет идти дальнейшая выборка.
        //
        // Важный момент: такие "умные" скрипты должны сами проверять доступы и видимости.
        // Это не критично для безопасности (все равно невидимые отсекутся при дальнейшей обработке),
        // но важно для нумерации.
        //
        // На данный момент представляется верным такой алгоритм: инклудимый скрипт
        // проверяет то, что у него запросили (всё), и если там нет настроек видимости,
        // то отдаёт нам только ту часть, какую надо, иначе отдаёт всё, и доступы 
        // разруливаем уже мы сами. Типа: 
        //   if exists(select sxml:rules from {from} where {where}) then
        //       select * from {from} where {where}
        //   else
        //       select * from {from} where {where} limit {first},{last-first}   
        //
        if (isInheritedlyVisible($block)) {
            if (hasSXMLAttr($block, 'enumerable')) {
                setSXMLAttr($block, 'range', $hash['range']); // Говорим processElement'у, что из этого блока надо будет выбрать 
            }
            return $block;
        } else {
            return null;
        }
    }

    // Проверяет, есть ли пользователь в списке $list
    function isPermitted($list, $type = 'visible-to') {
        // Ищем в списке правила вида "=table/rowid", которые надо пробивать по базе данных и искать в ней поля open-to
        foreach ($list as $i => $elem) {
            if (substr($elem, 0, 1) === '=') {
                unset($list[$i]);
                $splt = explode('/', substr($elem, 1));
                $dbPermList = requestPermissionsFromDB($splt[0], $splt[1], $type);
                foreach ($dbPermList as $j => $nelem) {
                    $list[] = $nelem;
                }
            }
        }
    
        global $_SXML;
        if (!isset($_SXML['user'])) {
            return false;
        }
        if (isset($_SXML['groups'])) {
            $grp = $_SXML['groups'];
        } else {
            $grp = array();
        }
        if (in_array($_SXML['user'], $list)) {
            return true;
        }
        foreach ($grp as $i => $group) {
            if (in_array('#' . $group, $list)) {
                return true;
            }
        }
        return false;
    }

    // Проверяет полное условие видимости блока, с проходом вверх по дереву (собираем правила всех родителей, проверяем их пересечение)
    function isInheritedlyVisible($el) {
        $tests = evaluateXPath($el, 'ancestor-or-self::*/@sxml:visible-to');
        if ($tests->length > 0) {
            for ($i == 0; $i < $tests->length; $i++) {
                $newlist = explode(' ', $tests->item($i)->value);
                if (!isset($list)) {
                    $list = $newlist;
                } else {
                    foreach($list as $n => $u) {
                        if (!in_array($u, $newlist)) {
                            unset($list[$n]);
                        }
                    }
                }
            }
            return isPermitted($list, 'visible-to');
        } else {
            return true;
        }
    }

    // Проверяет условие видимости конкретного элемента (вверх по дереву не ходит)
    function isThisVisible($el) {
        if (!hasSXMLAttr($el, 'visible-to')) {
            return true;
        } else {
            return stringPermits(getSXMLAttr($el, 'visible-to'), 'visible-to');
        }
    }
    
    function stringPermits($str, $type = 'visible-to') {
        //echo('string permits: "'. $str. '" for '.$type.'?'."\n");
        if (rtrim($str) === '') {
            //echo('yes, as there\'s nobody'."\n");
            return true;
        } else {
            $p = isPermitted(explode(' ', $str), $type);
            //echo(($p?'yes':'no').', as there was somebody'."\n");
            return $p;
        }

    }

    // Возвращает список детей, у которых в принципе есть настройки приватности
    function getEvanescentChildren($el) {
        return evaluateXPath($el, './*[@sxml:visible-to]', true);
    }
    
    // Удаляет дочерние узлы, которые невидимы лично нам
    function removeInvisibleChildren($list) {
        if ($list instanceof DOMNode) {
            $list = $list->childNodes;
        }
        for ($i = 0; $i < $list->length; $i++) {
            $item = $list->item($i);
            if ($item->nodeType == XML_ELEMENT_NODE && !isThisVisible($item)) {
                $item->parentNode->removeChild($item);
            }
        }
    }
    
    // Только элементы, чтобы текстовые ноды не сбивали нумерацию
    function getAllChildElements($el) {
        if (hasSXMLAttr($el, 'enumerable')) {
            return evaluateXPath($el, './*', true);
        } else {
            return $el->childNodes;
        }
    }

    // Возвращает ближайшего родителя-блока
    function getNearestBlock($el) {
        return evaluateXPath($el, 'ancestor-or-self::*[@sxml:id or @sxml:class] | /*', true)->item(0);
    }

    // Парсит range, возвращает массив first, last (в естественной нумерации, с единицы). При ошибке возвращает 0-0 или 1- (если знает total)
    function parseRange($range, $total) {
        if (!is_numeric($total)) {
            return array(0, 0);
        } else {
            $fr = parseFreeRange($range);
            if ($fr[0] === false && $fr[2] !== false) {
                return array($total - $fr[2] + 1, $total - $fr[3] + 1);
            } elseif ($fr[1] !== false) {
                return array($fr[0], $fr[1]);
            } elseif ($fr[0] !== false) {
                return array($fr[0], $total);
            } else {
                return array(1, $total); // Ошибка, показывай всё
            }
        }
    }
    
    // Парсит range при неизвестном total. Возвращает массив [first, last, first from end, last from end]:
    // 1-2 => [1, 2, false, false]; 5-4 => [false, false, 5, 4];
    function parseFreeRange($range) {
        if (is_numeric($range) && $range > 0) { // /34
            return array($range, $range, false, false);
        }
        $a = explode('-', $range);
        $first = $a[0];
        $last = $a[1];
        if ((!isset($first) || !is_numeric($first) && $first !== '')
            || (!isset($last) || !is_numeric($last) && $last !== '')
            || ($range == '-')) { // Ошибка
            return array(false, false, false, false); 
        }
        if ($first == '') { // /-5
            return array(false, false, $last, $last); 
        }
        if ($last == '') { // /10-
            return array($first, false, false, false);
        }
        if ($last < $first) {
            return array(false, false, $first, $last);
        }
        return array($first, $last, false, false);
    }

    // Определяет, какой в результате диапазон нам нужен. Возвращает 5 чисел - orig-first, first, last, orig-last и total.
    // Если range не возвратит ни одного элемента, ставит ещё последний элемент в true
    function getRangesForElement($el) {
        global $_SXML, $_SXML_GET;
        // TODO переписать на нормальный язык, с && и ||
        if (hasSXMLAttr($el, 'default-range')) {
            $range = getSXMLAttr($el, 'default-range');
        }
        if (hasSXMLAttr($el, 'range') && (getSXMLAttr($el, 'range') != '')) {
            $range = getSXMLAttr($el, 'range');
        }
        if (isset($_SXML['ranges'])) {
            foreach($_SXML['ranges'] as $rangeSpec) {
                if (isSamePath($rangeSpec['path'], $rangeSpec['get'], local2global($el->baseURI), $_SXML_GET) && matchesHash($el, $rangeSpec['hash'])) {
                    $range = $rangeSpec['range'];
                    if (!hasSXMLAttr($el, 'range')) {
                        setSXMLAttr($el, 'range', $range);
                    }
                }
            }
        }
        if (hasSXMLAttr($el, 'total')) {
            $total = getSXMLAttr($el, 'total');
        } else {
            $total = getAllChildElements($el)->length;
        }
        if (!$range) {
            $range = '1-';
        }
        // Парсим оригинальный range
        $r = parseRange($range, $total);
        $or = array(1, $total);
        if (hasSXMLAttr($el, 'original-range')) {
            $or = parseRange(getSXMLAttr($el, 'original-range'), $total);
            removeSXMLAttr($el, 'original-range');
        }
        if ($r[0] < $or[0]) {
            $r[0] = $or[0];
        }
        if ($r[1] > $or[1]) {
            $r[1] = $or[1];
        }
        
        return array($or[0], $r[0], $r[1], $or[1], $total);
    }

    // Выставляет правильные атрибуты first и last. На вход получает выход предыдущей функции
    function setRangeAttrs($el, $ranges) {
        setSXMLAttr($el, 'first', $ranges[1]);
        setSXMLAttr($el, 'last', $ranges[2]);
        if (!hasSXMLAttr($el, 'total')) {
            setSXMLAttr($el, 'total', $ranges[4]);
        }
    }

    // Обрабатывает инклуд (получает элемент, вычисляет требуемые параметры, фетчит и возвращает блок
    function processInclude($el) {
        global $_SXML_GET;
    
        $pathParts = splitGetString($el->getAttribute('from'));
        $hash = parseHash($pathParts['sxml']);
        if ($el->hasAttribute('id')) {
            $hash['id'] = $el->getAttribute('id');
        } elseif ($el->hasAttribute('class') && $el->hasAttribute('inst')) {
            $hash['class'] = $el->getAttribute('class');
        }
        if ($el->hasAttribute('range')) {
            $hash['range'] = $el->getAttribute('range');
        }

        // Подставляем GET-параметры из запроса вместо настоящих
        $rememberGet = $_SXML_GET;
        $_SXML_GET = $pathParts['get'];
        $block = fetch(resolvePath($pathParts['path'], $el->baseURI), $hash);
        addSourceAttr($block);
        $localBlock = $el->ownerDocument->importNode($block, true);
        $el->parentNode->replaceChild($localBlock, $el);
        processElement($localBlock);
        $_SXML_GET = $rememberGet;
        return $localBlock;
        
    }
    
    /////////////
    
    // Заполняет переменную, задекларированную в элементе
    function fillVar($var) {
        global $_SXML, $_SXML_VARS, $_SXML_POST, $_SXML_GET;
        
        if ($var->hasAttribute('name') && $var->hasAttribute('from') && $var->hasAttribute('value')) {
            $name = $var->getAttribute('name');
            $from = $var->getAttribute('from'); 
            $value = $var->getAttribute('value');
            $val = false;
            if (!$var->hasAttribute('preserve-old') || !isset($_SXML_VARS['name'])) {
                if ($from == 'get') {
                    $val = $_SXML_GET[$value];
                } elseif ($from == 'post') {
                    $val = $_SXML_POST[$value];
                } elseif ($from == 'sxml') {
                    $val = $_SXML[$value];
                }
                // TODO выражения - арифметика, конкатенация, if...
                if ($var->hasAttribute('accept') && !preg_match('/'.$var->hasAttribute('accept').'/', $val)) {
                    $err = createError($var->ownerDocument, 8);
                    $var->parentNode->replaceChild($err, $var);
                    return $err;
                } else {
                    $_SXML_VARS[$name] = $val;
                    $var->parentNode->removeChild($var);
                    return null;
                }
            }
        } else if ($var->hasAttribute('name')) { // Переменная прямо в тексте
            $children = getAllChildElements($var);
            for ($i = 0; $i < $children->length; $i++) {
                if (processElement($children->item($i)) === false) {
                    break;
                }
            }
            $_SXML_VARS[$var->getAttribute('name')] = $var->textContent;
            $var->parentNode->removeChild($var);
        }
    }
    
    // Ищет все переменные перед указанным элементом и последовательно их заполняет. Нужно для случая, когда мы запрашиваем часть документа,
    // а также для разворачивания if'ов и foreach'ей.
    // Переменные — это sxml:var или с указанным store, не находящиеся в action.
    function collectVars($block) {
        global $SXMLParams;
  
        $vars = evaluateXPath($block, 'preceding::sxml:var|(preceding::sxml:'.join('|preceding::sxml:', $SXMLParams['queries']).')[@store and not(ancestor::sxml:action)]');
        for ($i = 0; $i < $vars->length; $i++) {
            $var = $vars->item($i);
            if ($var->localName == 'var') {
                fillVar($var);
            } else {
                processQuery($var);
            }
        }        
    }
    
    // Выполняет в документе действие: ищёт <sxml:action> с соответствующим именем, прогоняет все элементы. Если элемент первого уровня вышел с ошибкой, то вся завершается с ошибкой,
    // Иначе с результатом всех ok первого уровня. Здесь хинт: можно игнорировать ошибки того или иного запроса, просто спрятав его под неисчезающий тег (т. е. не под sxml:if)
    function executeAction($currentAction) {
        global $_SXML, $SXMLParams, $_SXML_VARS;
        
        $doc = $currentAction->ownerDocument;
        // Более правильно — с префиксом: sxml:open-to, а не open-to, но для совместимости работают оба варианта. // TODO сделать только правильный
        $openTo = hasSXMLAttr($currentAction, 'open-to')? getSXMLAttr($currentAction, 'open-to') : ($currentAction->hasAttribute('open-to')? $currentAction->getAttribute('open-to') : false);
        $openAs = hasSXMLAttr($currentAction, 'open-as')? getSXMLAttr($currentAction, 'open-as') : ($currentAction->hasAttribute('open-as')? $currentAction->getAttribute('open-as') : false);
        
        if (!isInheritedlyVisible($currentAction)) {
            $actionResult = createError($doc, 6); // Или лучше даже 7, чтобы никто не догадался о невидимом действии 
        } elseif ($openTo !== false && !stringPermits($openTo, 'open-to')) {
            $actionResult = createError($doc, 6); // Явно запрещено прямо в файле
        } elseif ($openAs !== false && !stringPermits($_SXML_VARS[$openAs], 'open-to')) {
            $actionResult = createError($doc, 6); // Запрещено согласно значению переменной
/*        } elseif ($currentAction->hasAttribute('open-as-from') && !stringPermits(simpleSelect('sxml:open-to', $currentAction->getAttribute('open-as-from'), $currentAction->hasAttribute('open-as-where') ? $currentAction->getAttribute('open-as-where') : null, $currentAction->hasAttribute('open-as-uses') ? $currentAction->getAttribute('open-as-uses') : null), 'open-to')) {
            $actionResult = createError($doc, 6); // Запрещено по результатам запроса из базы*/ // Вырезано в пользу open-as + select
        } else {
            getDB()->beginTransaction();
            $_SXML['inTransaction'] = true;
            $failed = false;

            $children = getAllChildElements($currentAction);
            for ($i = 0; $i < $children->length; $i++) {
                if (!$failed) {
                    $item = $children->item($i);
                    $stepResult = processElement($item);
                    if ($stepResult !== null && $stepResult->nodeType == XML_ELEMENT_NODE) {    // Если мы видим какой-то тег в результате
                        if ($stepResult->localName == 'error' && $stepResult->namespaceURI == $SXMLParams['ns']) {
                            $actionResult = $stepResult;
                            getDB()->rollBack();
                            $_SXML['inTransaction'] = false;
                            $failed = true;
                        }
                    }
                }
            }
            
            if (!$failed) {
                $_SXML['inTransaction'] = false;
                getDB()->commit();
                
                // Результат - элемент <sxml:ok> c тегами update собранным со всех дочерних <ok'ев> и тегами <sxml:update tag="" item=""/>
                $actionResult = createSXMLElem($doc, 'ok');
                $updateAttrs = evaluateXPath($currentAction, './/sxml:ok/@update', true);
                $updateTags = evaluateXPath($currentAction, './/sxml:update', true);
                $updateString = '';
                foreach($updateAttrs as $i => $updateAttr) {
                    $updateTag = createSXMLElem($doc, 'update');
                    $updateTag->setAttribute('tag', $updateAttr->nodeValue);
                    $actionResult->appendChild($updateTag);
                }
                foreach($updateTags as $i => $updateTag) {
                    $actionResult->appendChild($updateTag);
                }
            }
        }
        if ($currentAction->hasAttribute('returns') && isset($_SXML_VARS[$currentAction->getAttribute('returns')])) {
            $actionResult->setAttribute('returned', $_SXML_VARS[$currentAction->getAttribute('returns')]);
        }
        $actionResult->setAttribute('action', $currentAction->getAttribute('name'));

        $replaced = $_SXML['laconic']? $doc->documentElement : $currentAction;
        $replaced->parentNode->replaceChild($actionResult, $replaced);
        return ($_SXML['laconic']? false : $currentAction);
    }
    
    // К атрибутам прав доступа, которые вычисляются на сервере (sxml:open-to="=table/id", sxml:open-as="var") дописывает понятный для интерфейса флажок "sxml:open-to-me". Бесполезные для интерфейса "sxml:open-as" и "sxml:visible-as" убирает.
    // sxml:visible-to-me не имеет смысла, и так понятно, показан он мне или нет. Значения sxml:open-to и sxml:visible-to нужно сохранить неизменными (включая =table/id) для полей ввода в интерфейсе.
    function interfacizeRights($el) {
        global $_SXML_VARS;
        if (hasSXMLAttr($el, 'open-to')) {
            //echo "attribute open-to in fact: ".getSXMLAttr($el, 'open-to')."\n";
            setSXMLAttr($el, 'open-to-me', !!stringPermits(getSXMLAttr($el, 'open-to'), 'open-to'));
        }
        if (hasSXMLAttr($el, 'open-as')) {
            //echo "attribute open-as in fact: ".getSXMLAttr($el, 'open-as')." = ".$_SXML_VARS[getSXMLAttr($el, 'open-as')]."\n";        
            setSXMLAttr($el, 'open-to-me', !!stringPermits($_SXML_VARS[getSXMLAttr($el, 'open-as')], 'open-to'));
            removeSXMLAttr($el, 'open-as');
        }
        if (hasSXMLAttr($el, 'visible-as')) {
            removeSXMLAttr($el, 'visible-as');
        }
    }
    
    ////////////////

    // Основная функция. Принимает на вход DOMElement. Возвращает либо себя, либо то, на что себя заменили (возможно, массив).
    // Если удалили, возвращает null. Если заменили весь документ и пора заканчивать всю обработку, возвращает false.
    function processElement($el) {
        global $SXMLParams, $_SXML, $_SXML_VARS;
    
        if ($el->nodeType == XML_ELEMENT_NODE) {
            if ($el->namespaceURI == $SXMLParams['ns']) {
                switch ($el->localName) {
                    case 'if':
                        // TODO
                    break;
                    case 'foreach':
                        if (!$el->hasAttribute('array') || !$el->hasAttribute('as')) {
                            return createError(9, 'foreach needs at least "array" and "as" attributes');
                        }
                        $inputArray = explode($el->hasAttribute('delim') ? $el->getAttribute('delim') : ' ', $_SXML_VARS[$el->getAttribute('array')]);
                        $asName = $el->getAttribute('as');  
                        if (isset($_SXML_VARS[$asName])) {
                            $wasOldAs = true;
                            $oldAs = $_SXML_VARS[$asName];
                        }
                        $children = getAllChildElements($el);
                        $numChildren = $children->length;
                        $fragment = $el->ownerDocument->createDocumentFragment();
                        
                        foreach ($inputArray as $i => $currentValue) {
                            $_SXML_VARS[$asName] = $currentValue;
                            for ($i = 0; $i < $numChildren; $i++) {
                                $currentChild = $children->item($i)->cloneNode(true);
                                $fragment->appendChild($currentChild);
                                processElement($currentChild);
                            }
                        }
                        
                        if ($wasOldAs) {
                            $_SXML_VARS[$asName] = $wasOldAs;
                        }
                        
                        $el->parentNode->replaceChild($fragment, $el);
                        return $fragment;
                    break;
                    case 'action':
                        if ($el->getAttribute('name') === $_SXML['action']) {
                            return executeAction($el);
                        } else {
                            // Убираем все внутренности
                            interfacizeRights($el);
                            while ($el->childNodes->length > 0) {
                                $el->removeChild($el->firstChild);
                            }
                            return $el;
                        }
                    break;
                    case 'include':
                        $block = processInclude($el);
                        return $block;
                    break;
                    case 'var':
                        $var = fillVar($el);
                        return $var;    
                    break;
                    case 'value-of':
                        $textNode = $el->ownerDocument->createTextNode($_SXML_VARS[$el->getAttribute('var')]);
                        $el->parentNode->replaceChild($textNode, $el);
                        return $textNode;
                    break;
                    case 'attribute':
                        $children = getAllChildElements($el);
                        for ($i = 0; $i < $children->length; $i++) {
                            if (processElement($children->item($i)) === false) {
                                break;
                            }
                        }
                        if (strpos($el->getAttribute('name'), 'sxml:') === 0) {
                            $el->parentNode->setAttributeNS($SXMLParams['ns'], substr($el->getAttribute('name'), 5), $el->textContent);
                        } else {
                            $el->parentNode->setAttribute($el->getAttribute('name'), $el->textContent);
                        }
                        $el->parentNode->removeChild($el);
                        return null;
                    default:
                        if (in_array($el->localName, $SXMLParams['queries'])) {
                            $block = processQuery($el);
                            processElement($block);
                            return $block;
                        }
                }
            }
            
            if (isSearched($el)) {
                unset($_SXML['inSearch']);
                $el->ownerDocument->replaceChild($el, $el->ownerDocument->documentElement);
                processElement($el);
                return false;
            }
            
            $hidden = getEvanescentChildren($el);
            if ($hidden->length > 0) {
                setSXMLAttr(getNearestBlock($el), 'login-dependent', 'true');
                removeInvisibleChildren($hidden);
            }
            $children = getAllChildElements($el);
            
            // Запоминаем пользователя
            if (hasSXMLAttr($el, 'user')) {
                $_SXML['found_users'][getSXMLAttr($el, 'user')] = true;
            }
            
            // Определяем, нужно ли нам выбирать узлы по счёту
            if (hasSXMLAttr($el, 'enumerable')) {
                $ranges = getRangesForElement($el);
                setRangeAttrs($el, $ranges);
                if ($ranges[1] > $ranges[3] || $ranges[2] < $ranges[0]) {
                    for ($i = $ranges[0]-1; $i < $ranges[3]; $i++) {
                        $el->removeChild($children->item($i));
                    }
                } else {
                    // Обрабатываем потомков
                    for ($i = $ranges[0]-1; $i < $ranges[1]-1; $i++) {
                        $el->removeChild($children->item($i-$ranges[0]+1));
                    }
                    for ($i = $ranges[1]-1; $i < $ranges[2]; $i++) {
                        processElement($children->item($i-$ranges[0]+1));
                    }
                    for ($i = $ranges[2]; $i < $ranges[3]; $i++) {
                        $el->removeChild($children->item($i-$ranges[0]+1));
                    }
                }
            } else {
                for ($i = 0; $i < $children->length; $i++) {
                    if (processElement($children->item($i)) === false) {
                        break;
                    }
                }
            }
        }
        
        return $el;
    }

    // Создаёт элемент sxml:error
    function createError($doc, $errCode, $text = false) {
        global $SXMLParams;
        $messages = array(
            1 => 'Элементов не найдено',
            2 => 'Файл не найден',
            3 => 'Неожиданная ошибка базы данных',
            4 => 'Неправильный запрос к базе данных',
            5 => 'Неправильный токен',
            6 => 'Недостаточно прав',
            7 => 'Неверное действие',
            8 => 'Неподходящее значение параметра',
            9 => 'Синтаксическая ошибка'
        );
        $error = $doc->createElementNS($SXMLParams['ns'], 'error');
        if (!$text) {
            if (isset($messages[$errCode])) {
                $text = $messages[$errCode];
            } else {
                $text = 'Ошибка SXML, код: '. $errCode;
            }
        }
        $error->setAttribute('code', $errCode);
        $error->setAttribute('message', $text);
        return $error;
    }
    
    // Создаёт список записей о пользователях, упомянутых на странице
    function makeUsersNode($doc) {
        global $_SXML;

        $mainNode = createSXMLElem($doc, 'found-users');
        foreach ($_SXML['found_users'] as $user => $t) {
            $hash = getUser($user);
            if ($hash !== false) {
                $elem = createSXMLElem($doc, 'user');
                $elem->setAttribute('id', $hash['user']);
                $elem->setAttribute('name', $hash['name']);
                $elem->setAttribute('link', $hash['link']);
                $mainNode->appendChild($elem);
            }
        }
        return $mainNode;
    }
    
    // Создаёт список записей о пользователях, упомянутых на странице
    function makeGroupsNode($doc) {
        global $_SXML;
        
        $mainNode = createSXMLElem($doc, 'my-groups');
        if ($_SXML['user'] !== '') {
            $groups = getGroupsForUser($_SXML['user']);
            if ($groups) {
                foreach ($groups as $i => $group) {
                    $elem = createSXMLElem($doc, 'group');
                    $elem->setAttribute('id', $group);
                    $mainNode->appendChild($elem);
                }
            }
        }
        return $mainNode;
    }
    
    // Добавляет к ноде атрибут source с правильной GET-строкой (исключая служебные параметры)
    function addSourceAttr($el) {
        global $_SXML_GET;
        $myGet = array();
        foreach ($_SXML_GET as $name => $value) {
            if (substr($name, 0, 5) !== 'sxml:') {
                $myGet[$name] = $value;
            }
        }
        setSXMLAttr($el, 'source', local2global($el->baseURI).(count($myGet) > 0 ? '?'.http_build_query($myGet) : ''));
    }
    
    // Основная функция. Получает на вход DOMDocument
    function processDocument($doc, $hash = array()) {
        global $_SXML, $_SXML_GET;
        $docblock = findBlock($doc, $hash);
        if ($docblock == null) {
            $doc->replaceChild(createError($doc, 1), $doc->documentElement);
            return;
        }
        if (!$docblock->isSameNode($doc->documentElement)) {
            collectVars($docblock);
            $doc->replaceChild($docblock, $doc->documentElement);
        }

        $_SXML['found_users'] = array(
            $_SXML['user'] => true
        );
        
        if (isset($hash['range'])) {
            setSXMLAttr($doc->documentElement, 'range', $hash['range']);
        }
        processElement($doc->documentElement);

        addSourceAttr($doc->documentElement);
        //setSXMLAttr($doc->documentElement, 'user', $_SXML['user']);
        setSXMLAttr($doc->documentElement, 'token', $_SXML['token']);
        if (isset($_SXML['remembered'])) {
            setSXMLAttr($doc->documentElement, 'remembered-provider', $_SXML['remembered']);
        }

        
        // Добавляем список пользователей в начало
        $SXMLDataNode = createSXMLElem($doc, 'data');
        if ($_SXML['user']) {
            $userNode = createSXMLElem($doc, 'user');
            $userNode->appendChild($doc->createTextNode($_SXML['user']));
            $SXMLDataNode->appendChild($userNode);
        }
        $SXMLDataNode->appendChild(makeUsersNode($doc));
        $SXMLDataNode->appendChild(makeGroupsNode($doc));
        $timeNode = createSXMLElem($doc, 'now');
        $timeNode->appendChild($doc->createTextNode(date(DATE_ATOM)));
        $SXMLDataNode->appendChild($timeNode);
        $doc->documentElement->insertBefore($SXMLDataNode, $doc->documentElement->firstChild);
    }
?>
