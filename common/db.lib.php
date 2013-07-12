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
    
    // Вытаскивает из базы первое поле заданного имени по заданным from, where и uses. Всегда возвращает строку
    function simpleSelect($name, $from, $where = null, $uses = null) {
        $query = 'select "'.$name.'" from '.$from;
        if ($where !== null) {
            $query .= ' where '.$where;
        }
        $results = processRawQuery($query, $uses);
        if (is_array($results) && (count($results) > 0) && isset($results[0][$name])) {
            return $results[0][$name];
        } else {
            return '';
        }
    }
    
    ///////
    // Составыне части для функций-обработчиков
    
    // Возвращает в виде массива данные, переданные в insert или edit
    function getContainedData($el) {
        global $SXMLParams;
    
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
        return $data;
    }
    
    // Проверяет автора заданной строчки таблицы 
    function isChangeAllowed($el) {
        $table = $el->hasAttribute('from') ? $el->getAttribute('from') : $el->getAttribute('in');
        $allowed =  $el->hasAttribute('also-open-to') ? $el->getAttribute('also-open-to') : '';
        $item = $el->getAttribute('id');
        $uses = $el->hasAttribute('uses') ? $el->getAttribute('uses') : '';
        
        $owner = simpleSelect('sxml:user', $table, '"sxml:item-id" = '.$item, $uses); // Это безопасно, $item не от пользователя пришёл, а от программиста
        
        if ($owner)
            if ($allowed) $allowed .= ' ' . $owner;
            else $allowed = $owner;
        
        if (stringPermits($allowed)) {
            return true;
        } else {
            return false;
        }
    }
    
    ////////
    // Функции-обработчики стандартных действий. Принимают элемент (<sxml:.../>), возвращают массив результатов, true или строку с ошибкой
    
    // Обрабатывает инструкцию <sxml:select/> - допиленный под наши нужды (имена пользователей, даты, права, страницы) SELECT.
    // <sxml:select from="" where="unescaped sql" [плюс всякие документные штуки типа enumerable, range и т. п.]/>
    function processSelect($el, &$range = null, &$total = null) {
        global $_SXML;
    
        $db = getDB();
        $ranges = parseFreeRange(getSXMLAttr($el, 'range'));
        if (!$_SXML['inTransaction']) {
            $trans = true;
            $_SXML['inTransaction'] = true;
            $db->beginTransaction();
        } else {
            $trans = false;
        }
        
        if ($el->hasAttribute('what')) {
            $what = $el->getAttribute('what');
        } else {
            $what = '*';
        }
        $paramStringBeforeWhere = ' from ('.$el->getAttribute('from').') where ( 1 = 1 ';
        $paramStringAfterWhere = ' and "sxml:deleted" is null )'; // <> \'deleted\')';
        if ($el->hasAttribute('where')) {
            $paramStringBeforeWhere .= ' and ('.$el->getAttribute('where').')';
        }
        if ($el->hasAttribute('order-by')) {
            $paramStringAfterWhere .= ' order by ('.$el->getAttribute('order-by').')';
        }
        
        $restricted = $db->query('select '.$what.$paramStringBeforeWhere.' and ("sxml:visible-to" not null) '.$paramStringAfterWhere);
        if (!$restricted) {
            $restricted = 0;
        } else {
            $restricted = $restricted->rowCount();
        }
        if ($restricted > 0 || ($ranges[0] === false && $ranges[1] === false && $ranges[2] === false && $ranges[3] === false)) {
            $res = processRawQuery('select '.$what.$paramStringBeforeWhere.$paramStringAfterWhere, $el->getAttribute('uses'));
        } else {
            $total = processRawQuery('select count('.$what.') '.$paramStringBeforeWhere.$paramStringAfterWhere, $el->getAttribute('uses'));
            $total = current($total[0]);
            if ($ranges[0] !== false) {
                if ($ranges[1] === false) { // 2-
                    $res = processRawQuery('select '.$what.$paramStringBeforeWhere.$paramStringAfterWhere.' limit -1 offset '.($ranges[0] - 1), $el->getAttribute('uses'));
                    $range = $ranges[0].'-';
                } else { // 1-10
                    $res = processRawQuery('select '.$what.$paramStringBeforeWhere.$paramStringAfterWhere.' limit '.($ranges[1] - $ranges[0] + 1).' offset '.($ranges[0] - 1), $el->getAttribute('uses'));
                    $range = $ranges[0].'-'.$ranges[1];
                }
            } else { // 7-4
                $res = processRawQuery('select '.$what.$paramStringBeforeWhere.$paramStringAfterWhere.' limit '.($ranges[2] - $ranges[3] + 1).' offset '.($total - $ranges[2]), $el->getAttribute('uses'));
                $range = $ranges[2].'-'.$ranges[3];
            }
        }
        
        if ($trans) {
            $_SXML['inTransaction'] = false;
            $db->commit();
        }
        return $res;
    }
    
    // Обрабатывает инструкцию <sxml:insert/> - допиленный INSERT
    // <sxml:insert into=""><field default="123">some unescaped sql :data</field><field>where :vars are substituded from POST</field></sxml:insert>
    function processInsert($el) {
        global $SXMLParams, $_SXML;
        
        $table = $el->getAttribute('into');
        $data = getContainedData($el);
        $r = getDB()->exec('create table if not exists "'.$table.'" ("sxml:item-id" integer primary key autoincrement, "'.join('", "', array_keys($data)).'", "sxml:editable-to", "sxml:visible-to", "sxml:open-to", "sxml:time", "sxml:user", "sxml:deleted")');
        $rq = processRawQuery('insert into "'.$table.'" ("'.join('", "', array_keys($data)).'", "sxml:time", "sxml:user") values(('.join('), (', $data).'), \''.date(DATE_ATOM).'\', :user)', $el->getAttribute('uses').' user');
        if ($rq) {
            $rq = simpleSelect('sxml:item-id', $table, 'rowid=last_insert_rowid();');
        }
        return $rq;
    }
    
    // Обрабатывает инструкцию <sxml:delete/> - допиленный DELETE
    // <sxml:delete from="" id="" also-open-to="" uses=""/>
    // also-open-to - кому можно, кроме автора. Проставляет 'deleted' в поле deleted - чтобы при обновлении можно было заметить, что что-то удалилось
    function processDelete($el) {
    
        if (!$el->hasAttribute('from') || !$el->hasAttribute('id')) {
            return 'Неправильный delete';
        }        
        if (!isChangeAllowed($el)) {
            return 'Нельзя удалять чужое :)';
        } else {
            return processRawQuery('update '.$el->getAttribute('from').' set "sxml:deleted" = \'deleted\', "sxml:time" = \''.date(DATE_ATOM).'\' where ("sxml:item-id" = '.$el->getAttribute('id').')', $el->getAttribute('uses'));
        }

    }
    
    // Обрабатывает инструкцию <sxml:edit/> - допиленный UPDATE
    // <sxml:edit in="" id="" also-open-to="" uses="">data</sxml:edit>
    // also-open-to - кому можно, кроме автора. Проставляет 'deleted' в поле deleted - чтобы при обновлении можно было заметить, что что-то удалилось
    function processEdit($el) {
    
        if (!$el->hasAttribute('in') || !$el->hasAttribute('id')) {
            return 'Неправильный edit';
        }        
        if (!isChangeAllowed($el)) {
            return 'Нельзя редактировать чужое :)';
        } else {
            $data = getContainedData($el);
            $updateString = '';
            foreach ($data as $field => $content) {
                $data[$field] = '"'.$field.'" = ('.$content.')';
            }
            return processRawQuery('update '.$el->getAttribute('in').' set '.join(', ', $data).', "sxml:time" = \''.date(DATE_ATOM).'\' where ("sxml:item-id" = '.$el->getAttribute('id').')', $el->getAttribute('uses'));
        }

    }
    
    // Обрабатывает сырую транзакцию, возвращает массив результатов, либо true, либо строку с ошибкой, либо число — айдишник последней записи
    function processRawQuery($q, $uses = null) {
        global $_SXML_VARS;

        $query = getDB()->prepare($q);
        $vars = explode(' ', $uses);
        if (!$query) {
            $err = getDB()->errorInfo();
            return 'DB error: '.$err[2];
        }
        foreach($_SXML_VARS as $name => $value) {
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
    function buildResult($el, $result, $range = false, $total = false) {
        global $_SXML_VARS;
   
        $doc = $el->ownerDocument;
        if (is_array($result) || (is_numeric($result) || $result === true) && ($el->localName == 'select' || $el->hasAttribute('nook'))) { 
            // Если это запрос не на действие, то в любом случае показываем нужный тег 
            if ($el->hasAttribute('store')) {
                $_SXML_VARS[$el->getAttribute('store')] = current($result[0]);
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
                    if (!in_array($child->localName, array('from', 'where', 'what', 'into', 'order-by', 'what', 'tag', 'entry', 'attrs', 'ignore', 'uses', 'store', 'also-open-to'))) {
                        $res->setAttributeNS($child->namespaceURI, $child->nodeName, $child->nodeValue);
                    }
                }
                $attrs = explode(' ', $el->getAttribute('attrs'));
                $ignore = explode(' ', $el->getAttribute('ignore'));
                if (is_array($result)) {  // Иначе в ответе ничего 
                    foreach ($result as $i => $row) {
                        $entry = $el->getAttribute('entry');
                        $entryClass = $el->getAttribute('entry-class');
                        if (substr($entry, 0, 5) === 'sxml:') {
                            $rowElem = createSXMLElem($doc, $SXMLParams['ns'], substr($entry, 5)); 
                        } else if ($entry == '') {
                            $rowElem = $doc->createElement('entry');
                        } else {
                            $rowElem = $doc->createElement($entry); 
                        }
                        if ($entryClass) {
                            setSXMLAttr($rowElem, 'class', $entryClass);
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
                        // Подстановка вложенных элементов 
                        foreach($el->childNodes as $i => $child) {
                            if ($child->nodeType == XML_ELEMENT_NODE) {
                                $readyElem = $child->cloneNode(true);
                                foreach($readyElem->attributes as $a => $attr) {
                                    $attr->value = substituteVars($attr->value, $row, $_SXML_VARS);
                                }
                                foreach($readyElem->childNodes as $a => $grandchild) {
                                    if ($grandchild->nodeType == XML_TEXT_NODE) {
                                        $readyElem->replaceChild($readyElem->ownerDocument->createTextNode(substituteVars($grandchild->wholeText, $row, $_SXML_VARS)), $grandchild);
                                    }
                                }
                                $rowElem->appendChild($readyElem);
                            }
                        }
                        $res->appendChild($rowElem);
                    }
                }
                return $res;
            }
        } elseif ($result === true) {
            return createSXMLElem($doc, 'ok');
        } elseif (is_numeric($result)) {
            $ok = createSXMLElem($doc, 'ok');
            if ($el->hasAttribute('store')) {
                $_SXML_VARS[$el->getAttribute('store')] = $result;
            }
            $ok->setAttribute('last-insert-id', $result);
            return $ok;
        } else {
            return createError($doc, 4);
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