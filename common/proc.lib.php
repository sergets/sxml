<?
// Модуль sxml-процессора SXMLight
// Получает на вход входной xml-документ, возвращает его, с обработанными sxml-инструкциями 

require_once 'setup.php';
require_once 'common.lib.php';

// Кеши для часто встречающихся объектов
$_xpaths = array();

///////////////

// Возвращает объект DOMXPath.
function getXPath($doc) {
    // TODO - профилировать - оно вообще надо?
    // TODO - getXPath($el). Или даже evaluateXPath($el, '...');
    $uid = md5($doc->saveXML());
    if (isset($_xpaths[$uid])) {
        return $_xpaths[$uid];
    } else {
        $_xpaths[$uid] - new DOMXPath($doc);
        $_xpaths[$uid]->registerNamespace('sxml', 'http://sergets.ru/sxml');
        $xpath->registerNamespace("php", "http://php.net/xpath");
        $xpath->registerPHPFunctions("isThisVisible");
        return $_xpaths[$uid];
    }
}

// Ищет в документе блок, указанный с помощью hash, возвращает DOMNode
function findBlock($doc, $hash = array()) {
    $xpath = getXPath($doc);
    if (isset($hash['id'])) {
        // TODO: эскейпить параметры!
        $blocks = $xpath->evaluate('//[@sxml:id=\''.$hash['id'].'\']');
        if ($blocks->length > 0) {
            $block = $blocks.item(0);
        }
    } elseif (isset($hash['class']) && isset($hash['inst'])) {
        $blocks = $xpath->evaluate('//[@sxml:id=\''.$hash['id'].'\']');
        if ($blocks->length > 0) {
            $block = $blocks.item(0);
        }
    } else {
        $block = $doc->documentElement;
    }
    // TODO: выбирать по счёту // вроде уже отказались, вместо этого это будет делать processBlock
}

// Загружает и парсит (xml) файл c локального пути $from для инклуда, возвращает DOMNode c выставленными included-from и т. п.
// hash - указание блока (массив с возможными ключами: range, class+inst, id)
function fetch($from, $hash = array()) {
    // Здесь нужно дописать парсеры урлов, в частности *.xml.php.
    $doc = new DOMDocument();
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
        $block->setAttributeNS($SXMLParams['ns'], 'source', local2global($from));
        if ($block->hasAttributeNS($SXMLParams['ns', 'enumerable')) {
            $block->setAttributeNS($SXMLParams['ns'], 'range', $hash['range']); // Говорим processElement'у, что из этого блока надо будет выбрать 
        }
        return $block;
    } else {
        return $doc->createTextNode('') // TODO: что возвращать, когда нечего?
    }
}

// Проверяет, есть ли пользователь в списке $list
function isVisible($list) {
    // TODO: разобраться, где заполнятются $SXMLParams['user'] и $SXMLParams['groups'];
    if (in_array($SXMLParams['user'], $list)) {
        return true;
    }
    foreach ($SXMLParams['groups'] as $i => $group)
        if (in_array('#' . $group, $list)) {
            return true;
        }
    }
    return false;
}

// Проверяет полное условие видимости блока, с проходом вверх по дереву (собираем правила всех родителей, проверяем их пересечение)
function isInheritedlyVisible($el) {
    $tests = getXPath($el->document)->evaluate('ancestor-or-self::*/@sxml:visible-to', $el));
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
    if (!$el->hasAttributeNS($SXMLParams['ns'], 'visible-to')) {
        return true;
    } else {
        return isVisible(explode(' ', $el->getAttributeNS($SXMLParams['ns'], 'visible-to')));
    }
}

// Есть ли дети с настройками приватности
function hasHiddenChildren($el) {
    return getXPath($el->document)->evaluate('boolean(./*[@visible-to])') === true) {
}

// Возвращает список всех ограниченно видимых потомков 
function getVisibleChildElements($el) {
    return getXPath($el->document)->query('./*[php:function("isThisVisible", .)=true()]', $el);
}

// Только элементы, чтобы текстовые ноды не сбивали нумерацию
function getAllChildElements($el) {
    if ($el->hasAttributeNS($SXMLParams['ns'], 'enumerable')) {
        return getXPath($el->document)->query('./*', $el);
    } else {
        return $el->childNodes;
    }
}

// Возвращает ближайшего родителя-блока
function getNearestBlock($el) {
    return getXPath($el->document)->query('ancestor-or-self::*[@sxml:id or @sxml:class] | /*')->item(0);
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
    if ($el->hasAttributeNS($SXMLParams['ns'], 'default-range')) {
        $range = $el->getAttributeNS($SXMLParams['ns'], 'default-range');
    }
    if ($el->hasAttributeNS($SXMLParams['ns'], 'range')) {
        $range = $el->getAttributeNS($SXMLParams['ns'], 'range');
    }
    if ($el->hasAttributeNS($SXMLParams['ns'], 'total')) {
        $total = $el->getAttributeNS($SXMLParams['ns'], 'total');
    } else {
        $total = getVisibleChildNodes($el)->length;
    }
    if (!isset($range)) {
        $range = '1-';
    }
    
    // Парсим оригинальный range
    $r = parseRange($range, $total);
    $or = array(1, $total);
    if ($el->hasAttributeNS($SXMLParams['ns'], 'original-range')) {
        $or = parseRange($range, $total);
        $el->removeAttributeNS($SXMLParams['ns'], 'original-range');
    }    
    return array($or[0], $r[0], $r[1], $or[1], $total);
}

// Выставляет правильные атрибуты first и last. На вход получает выход предыдущей функции
function setRangeAttrs($el, $ranges) {
    // TODO: понять, не нужно ли здесь ещё sxml:ranges
    $el->setAttributeNS($SXMLParams['ns'], 'first', $ranges[1]);
    $el->setAttributeNS($SXMLParams['ns'], 'last', $ranges[2]);
    if (!$el->hasAttributeNS($SXMLParams['ns'], 'total')) {
        $el->setAttributeNS($SXMLParams['ns'], 'total', $ranges[4]);
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
        getNearestBlock($el)->setAttributeNS($SXMLParams['ns'], 'login-dependent', 'true');
        $children = getVisibleChildElements($el);
    } else {
        $children = getAllChildElements($el);
    }
    // Определяем, нужно ли нам выбирать узлы по счёту
    if ($el->hasAttributeNS($SXMLParams['ns'], 'enumerable')) {
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
    $doc->documentElement->setAttributeNS($SXMLParams['ns'], 'source', local2global($doc->documentElement->baseURI));
    if (isset($hash['range'])) {
        $doc->documentElement->setAttributeNS($SXMLParams['ns'], 'range', $hash['range']);
    }
    processElement($doc->documentElement);
}
?>
