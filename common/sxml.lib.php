<?
    // Модуль sxml-процессора SXMLight
    // Получает на вход входной xml-документ, возвращает его, с обработанными sxml-инструкциями 

    require_once 'setup.php';
    require_once 'common.lib.php';

    // Кеши для часто встречающихся объектов
    $_xpaths = array();

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
        return $el->setAttributeNS($SXMLParams['ns'], $attr, $val);
    }

    // Возвращает объект DOMXPath.
    function getXPath($doc) {
        global $_xpaths;
        // TODO - профилировать - оно вообще надо?
        // TODO - getXPath($el). Или даже evaluateXPath($el, '...');
        $uid = md5($doc->saveXML());
        if (isset($_xpaths[$uid])) {
            return $_xpaths[$uid];
        } else {
            $_xpaths[$uid] = new DOMXPath($doc);
            $_xpaths[$uid]->registerNamespace('sxml', 'http://sergets.ru/sxml');
            $_xpaths[$uid]->registerNamespace('php', 'http://php.net/xpath');
            //$_xpaths[$uid]->registerPhpFunctions('isThisVisible');
            return $_xpaths[$uid];
        }
    }
    
    // Шорткат для xpath. Третий параметр: если true, то делать query, иначе evaluate;
    function evaluateXPath($el, $xpath, $forceNodeList = false) {
        if (!isset($el->ownerDocument)) {
            if ($forceNodeList) {
                return getXPath($el)->evaluate($xpath);
            } else {
                return getXPath($el)->query($xpath);
            }
        } else {
            if ($forceNodeList) {
                return getXPath($el->ownerDocument)->evaluate($xpath, $el);
            } else {
                return getXPath($el->ownerDocument)->query($xpath, $el);
            }
        }
    }

    // Ищет в документе блок, указанный с помощью hash, возвращает DOMNode
    function findBlock($doc, $hash = array()) {
        if (isset($hash['id'])) {
            // TODO: эскейпить параметры!
            $blocks = evaluateXPath($doc, '//[@sxml:id=\''.$hash['id'].'\']');
            if ($blocks->length > 0) {
                $block = $blocks.item(0);
            }
        } elseif (isset($hash['class']) && isset($hash['inst'])) {
            $blocks = evaluateXPath($doc, '//[@sxml:id=\''.$hash['id'].'\']');
            if ($blocks->length > 0) {
                $block = $blocks.item(0);
            }
        } else {
            $block = $doc->documentElement;
        }
        return $block;
        // TODO: выбирать по счёту // вроде уже отказались, вместо этого это будет делать processBlock
    }

    // Загружает и парсит (xml) файл c локального пути $from для инклуда, возвращает DOMNode c выставленными included-from и т. п.
    // hash - указание блока (массив с возможными ключами: range, class+inst, id)
    function fetch($from, $hash = array()) {
        global $SXMLParams;
        // Здесь нужно дописать парсеры урлов, в частности *.xml.php.
        $doc = new DOMDocument();
        if (!$from) {
            return $doc->createTextNode('');
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
            setSXMLAttr($block, 'source', local2global($from));
            if (hasSXMLAttr($block, 'enumerable')) {
                setSXMLAttr($block, 'range', $hash['range']); // Говорим processElement'у, что из этого блока надо будет выбрать 
            }
            return $block;
        } else {
            return $doc->createTextNode(''); // TODO: что возвращать, когда нечего?
        }
    }

    // Проверяет, есть ли пользователь в списке $list
    function isVisible($list) {
        global $_SXML;
        if (!isset($_SXML['user'])) {
            return false;
        }
        if (isset($_SXML['groups'])) {
            $grp = $_SXML['groups'];
        } else {
            $grp = array();
        }
        // TODO: разобраться, где заполнятются $_SXML['user'] и $_SXML['groups'];
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
            return isVisible($list);
        } else {
            return true;
        }
    }

    // Проверяет условие видимости конкретного элемента (вверх по дереву не ходит)
    function isThisVisible($el) {
        if (!hasSXMLAttr($el, 'visible-to')) {
            return true;
        } else {
            return isVisible(explode(' ', getSXMLAttr($el, 'visible-to')));
        }
    }

    // Есть ли дети с настройками приватности
    function hasHiddenChildren($el) {
        return evaluateXPath($el, 'boolean(./*[@visible-to])');
    }

    // Возвращает список всех ограниченно видимых потомков 
    function getVisibleChildElements($el) {
        // Неприятный костыль, но нужно для того, чтобы у getVisible... и getAll... был одинаковый интерфейс с ->item();
        $f = $el->ownerDocument->createDocumentFragment();
        foreach($el->childNodes as $i => $child) {
            if ($child->nodeType == XML_ELEMENT_NODE && isThisVisible($child)) {
                $f->appendChild($child);
            }
        }
        return $f->childNodes;
        // return evaluateXPath($el, './*[php:function("isThisVisible", .)=true()]', true); // не работает на php ниже 5.3
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
            if (is_numeric($range)) { // /34
                return array($range, $range);
            }
            $a = explode('-', $range);
            $first = $a[0];
            $last = $a[0];
            if ((!isset($first) || !is_numeric($first) && $first !== '')
                || (!isset($last) || !is_numeric($last) && $last !== '')
                || ($range == '-')) { // Ошибка
                return array(1, $total); 
            }
            if ($first == '') { // /-5
                return array($total - $last + 1, $total - $last + 1); 
            }
            if ($last == '') { // /10-
                return array($first, $total);
            }
            if ($last < $first) {
                return array($total - $first + 1, $total - $last + 1);
            }
            return array($first, $last);
        }
    }

    // Определяет, какой в результате диапазон нам нужен. Возвращает 5 чисел - orig-first, first, last, orig-last и total
    function getRangesForElement($el) {
        // TODO переписать на нормальный язык, с && и ||
        if (hasSXMLAttr($el, 'default-range')) {
            $range = getSXMLAttr($el, 'default-range');
        }
        if (hasSXMLAttr($el, 'range')) {
            $range = getSXMLAttr($el, 'range');
        }
        if (hasSXMLAttr($el, 'total')) {
            $total = getSXMLAttr($el, 'total');
        } else {
            $total = getVisibleChildNodes($el)->length;
        }
        if (!isset($range)) {
            $range = '1-';
        }
        
        // Парсим оригинальный range
        $r = parseRange($range, $total);
        $or = array(1, $total);
        if (hasSXMLAttr($el, 'original-range')) {
            $or = parseRange($range, $total);
            removeSXMLAttr($el, 'original-range');
        }    
        return array($or[0], $r[0], $r[1], $or[1], $total);
    }

    // Выставляет правильные атрибуты first и last. На вход получает выход предыдущей функции
    function setRangeAttrs($el, $ranges) {
        // TODO: понять, не нужно ли здесь ещё sxml:ranges
        setSXMLAttr($el, 'first', $ranges[1]);
        setSXMLAttr($el, 'last', $ranges[2]);
        if (!hasSXMLAttr($el, 'total')) {
            setSXMLAttr($el, 'total', $ranges[4]);
        }
    }

    // Обрабатывает инклуд (получает элемент, вычисляет требуемые параметры, фетчит и возвращает блок
    function processInclude($el) {
        $hash = array();
        if ($el->hasAttribute('id')) {
            $hash['id'] = $el->getAttribute('id');
        } elseif ($el->hasAttribute('class') && $el->hasAttribute('inst')) {
            $hash['class'] = $el->getAttribute('class');
        }
        if ($el->hasAttribute('range')) {
            $hash['range'] = $el->getAttribute('range');
        }
        $block = fetch(resolvePath($el->getAttribute('from'), $el->baseURI), $hash);
        $el->parentElement->replaceChild($block, $el);
        processElement($block);
    }

    ////////////////

    // Основная функция. Принимает на вход DOMElement
    function processElement($el) {
        if (!isThisVisible($el)) { // TODO - Кажется, это дублирующая проверка! 
            $el->parentElement->removeChild($el);
        } elseif ($el->tagName == 'include' && $el->namespaceURI == $SXMLParams['ns']) {
            $block = processInclude($el);
            $el->parentElement->replaceChild($block, $el);
            processElement($block);
        }
        if (hasHiddenChildren($el)) {
            setSXMLAttr(getNearestBlock($el), 'login-dependent', 'true');
            $children = getVisibleChildElements($el);
        } else {
            $children = getAllChildElements($el);
        }
        // Определяем, нужно ли нам выбирать узлы по счёту
        if (hasSXMLAttr($el, 'enumerable')) {
            $ranges = getRangesForElement($el);
            setRangeAttrs($el, $ranges);
            // Обрабатываем потомков
            for ($i = $ranges[0]-1; $i < $ranges[1]-1; $i++) {
                $el->removeChild($children->item($i));
            }
            for ($i = $ranges[1]-1; $i < $ranges[2]; $i++) {
                processElement($children->item($i));
            }
            for ($i = $ranges[2]; $i < $ranges[3]; $i++) {
                $el->removeChild($children->item($i));
            }
        } else {
            for ($i = 0; $i < $children->length; $i++) {
                processElement($children->item($i));
            }
        }
    }

    // Основная функция. Получает на вход DOMDocument
    function processDocument($doc, $hash = array()) {
        $docblock = findBlock($doc, $hash);
        if ($docblock->isSameNode($doc->documentElement)) {
            $doc->replaceChild($docblock, $doc->documentElement);
        }
        setSXMLAttr($doc->documentElement, 'source', local2global($doc->documentElement->baseURI));
        if (isset($hash['range'])) {
            setSXMLAttr($doc->documentElement, 'range', $hash['range']);
        }
        processElement($doc->documentElement);
    }
?>
