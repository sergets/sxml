<?
    require_once 'setup.php';
    require_once 'db.lib.php';
    
    // Модуль для работы с базой данных в SXML
    
    // Ссылка на хендлер базы данных
    $SXML_DBHandler = null;
    
    // Открывает базу данных, если нужно
    function getDB() {
        global $SXML_DBHandler, $SXMLParams;
        if (!$SXML_DBHandler) {
            $SXML_DBHandler = new PDO($SXMLParams['db']);
        }
        return $SXML_DBHandler;
    }
    
    function closeDB() {
        global $SXML_DBHandler;
        $SXML_DBHandler = null;
    }
    
    // Обрабатывает инструкцию <sxml:select/> - допиленный под наши нужды (имена пользователей, даты, права, страницы) SELECT.
    // <sxml:select from="" where="unescaped sql" [плюс всякие документные штуки типа enumerable, range и т. п.]/>
    function processSelect($el, &$range = null, &$total = null) {
    
        $db = getDB();
        $ranges = parseFreeRange(getSXMLAttr($el, 'range'));
        if (!$_SXML['inTransaction']) {
            $trans = true;
            $_SXML['inTransaction'] = true;
            $db->beginTransaction();
        } else {
            $trans = false;
        }
        $db->exec('drop if exists view "sxml:select"');
        $createViewQuery = 'create temporary view "sxml:select" as select * from ('.$el->getAttribute('from').')';
        if ($el->hasAttribute('where')) {
            $createViewQuery .= ' where ('.$el->getAttribute('where').')';
        }
        if ($el->hasAttribute('order-by')) {
            $createViewQuery .= ' order by ('.$el->getAttribute('order-by').')';
        }
        $createdView = processRawQuery($createViewQuery, $el->getAttribute($uses));
        if ($createdView !== true) {
            return $createdView;
        }
        $restricted = $db->query('select * from "sxml:select" where ("sxml:visible-to" not null)')->rowCount();
        if ($restricted > 0 || ($ranges[0] === false && $ranges[1] === false && $ranges[2] === false && $ranges[3] === false)) {
            $res = processRawQuery('select * from "sxml:select"');
        } else {
            $total = $db->query('select count(*) from "sxml:select"')->fetchAll();
            $total = current($total[0]);
            if ($ranges[0] !== false) {
                if ($ranges[1] === false) { // 2-
                    $res = processRawQuery('select * from "sxml:select" limit -1 offset '.$ranges[0]);
                    $range = $ranges[0].'-';
                } else { // 1-10
                    $res = processRawQuery('select * from "sxml:select" limit '.($ranges[1] - $ranges[0] + 1).' offset '.$ranges[0]);
                    $range = $ranges[0].'-'.$ranges[1];
                }
            } else { // 7-4
                $res = processRawQuery('select * from "sxml:select" limit '.($ranges[2] - $ranges[3] + 1).' offset '.($total - $ranges[2]));
                $range = $ranges[2].'-'.$ranges[3];
            }
        }
        $db->exec('drop if exists view "sxml:select"');
        if ($trans) {
            $_SXML['inTransaction'] = false;
            $db->commit();
        }
        return $res;
    }
    
    // Обрабатывает инструкцию <sxml:insert/> - допиленный INSERT
    // <sxml:insert to=""><field default="123">some unescaped sql :data</field><field>where :vars are substituded from POST</field></sxml:insert>
    function processInsert($el) {
        global $SXMLParams, $_SXML;
        
        $table = $el->getAttribute('into');
        $cols = evaluateXPath($el, './*', true);
        $data = array();
        for ($i = 0; $i < $cols->length; $i++) {
            if ($cols->item($i)->tagName == 'col') {
                $name = $cols->item($i)->getAttribute('name');
            } elseif ($cols->item($i)->namespaceURI == $SXMLParams['ns']) {
                $name = 'sxml/' . $cols->item($i)->tagName;
            } else {
                $name = $cols->item($i)->tagName;
            }
            $data[$name] = $cols->item($i)->textContent;
        }
        $r = getDB()->exec('create table if not exists "'.$table.'" ("sxml:item-id" integer primary key autoincrement, "'.join('", "', array_keys($data)).'", "sxml:editable-to", "sxml:visible-to", "sxml:time", "sxml:user")');
        return processRawQuery('insert into "'.$table.'" ("'.join('", "', array_keys($data)).'", "sxml:time", "sxml:user") values(('.join('), (', $data).'), \''.date(DATE_ATOM).'\', :user)', $el->getAttribute('uses').' user');
    }
    
    // Обрабатывает инструкцию <sxml:delete/> - допиленный DELETE
    // <sxml:delete from="" id=""/>
    function processDelete($el) {
        $db = getDB();
        return null;
    }
    
    // Обрабатывает сырую транзакцию, возвращает массив результатов, либо true, либо строку с ошибкой
    function processRawQuery($q, $uses = null) {
        global $_SXML;

        $query = getDB()->prepare($q);
        $vars = explode(' ', $uses);
        if (!$query) {
            return 'DB error: malformed query';
        }
        foreach($_SXML['vars'] as $name => $value) {
            if (in_array($name, $vars)) {
                $query->bindValue(':'.$name, $value);
            }
        }
        if ($query->execute()) {
            $fetch = $query->fetchAll(PDO::FETCH_ASSOC);
            if (count($fetch) > 0) {
                return $fetch;
            } else {
                return true;
            }
        } else {
            $ei = $query->errorInfo();
            return 'DB error: ' . $ei[2];
        }
    }
    
    // Формирует результирующий элемент
    function buildResult($el, $result, $range = false) {
        $doc = $el->ownerDocument;
        if (is_array($result)) {
            if ($el->hasAttribute('store')) {
                $_SXML['vars'][$el->getAttribute('store')] = current($result[0]);
            } else {
                if (!$el->hasAttribute('tag')) {
                    $tag = 'list';
                } else {    
                    $tag = $el->getAttribute('tag');
                }
                if (substr($tag, 0, 5) === 'sxml:') {
                    $res = createSXMLElem($doc, substr($tag, 5)); 
                } else {
                    $res = $doc->createElement($tag); 
                }
                if ($range) {
                    setSXMLAttr($res, 'original-range', $range);
                }
                if ($total) {
                    setSXMLAttr($res, 'total', $total);
                }
                for ($i = 0; $i < $el->attributes->length; $i++) {
                    $child = $el->attributes->item($i);
                    if (!in_array($child->localName, array('from', 'where', 'into', 'order-by', 'what', 'tag', 'entry', 'attrs', 'uses', 'store'))) {
                        $res->setAttributeNS($child->namespaceURI, $child->nodeName, $child->nodeValue); // TODO: true?
                    }
                }
                $attrs = explode(' ', $el->getAttribute('attrs'));
                foreach ($result as $i => $row) {
                    $entry = $el->getAttribute('entry');
                    if (substr($entry, 0, 5) === 'sxml:') {
                        $rowElem = createSXMLElem($doc, $SXMLParams['ns'], substr($entry, 5)); 
                    } else {
                        $rowElem = $doc->createElement($entry); 
                    }
                    foreach ($row as $name => $value) {
                        if ($value != null) {
                            if (substr($name, 0, 5) === 'sxml:') {
                                setSXMLAttr($rowElem, substr($name, 5), $value);
                            } elseif (in_array($name, $attrs)) {
                                $rowElem->setAttribute($name, $value);
                            } else {
                                $q = $doc->createElement($name);
                                $q->appendChild($doc->createTextNode($value));
                                $rowElem->appendChild($q);
                            }
                        }
                    }
                    $res->appendChild($rowElem);
                }
                return $res;
            }
        } elseif ($result === true) {
            return createSXMLElem($doc, 'ok');
        } else {
            return createError($doc, 4, $result);
        }
    }
    
    // Общая часть для всех инструкций
    function processQuery($el) {
        global $_SXML, $SXMLParams;
        if ($el->namespaceURI !== $SXMLParams['ns']) {
            return false;
        }
        $range = false;
        $total = false;
        try {
            switch ($el->localName) {
                case 'insert':
                    $result = processInsert($el);
                    break;
                case 'delete':
                    $result = processDelete($el);
                    break;
                case 'edit':
                    $result = processEdit($el);
                    break;
                case 'select':
                    $result = processSelect($el, $range, $total);
                    break;
                case 'query':
                    $result = processRawQuery($el->textContent, $el->getAttribute('uses'));
                    break;
                default:
                    return false;
            }
        } catch (PDOException $e) {
            $el->parentElement->replaceChild(createError($el->ownerDocument, 3, $e->getMessage()));
        }
        $res = buildResult($el, $result, $range, $total);
        $el->parentNode->replaceChild($res, $el);
        return $res;
    }
    
?>