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
            try {
                $SXML_DBHandler = new PDO('sqlite:'.$SXMLParams['db']);
            } catch(PDOException $e) {
                echo "sql exception!";
                if (!file_exists($SXMLParams['data'])) {
                    echo $SXMLParams['data'];
                }
                if (!file_exists($SXMLParams['db'])) {
                    fopen($SXMLParams['db'], 'w');
                    fclose($SXMLParams['db']);
                    $SXML_DBHandler = new PDO('sqlite:'.$SXMLParams['db']);
                }
            }
        }
        return $SXML_DBHandler;
    }
    
    function closeDB() {
        global $SXML_DBHandler;
        $SXML_DBHandler = null;
    }
    
    function logQuery($query, $vars, $result) {
        global $SXMLParams;
        
        $fn = $SXMLParams['localroot'].'data/queries.log';
        if (file_exists($fn)) {
            $s = date('H:i:s') . "---------------------------------------------\n";
            $s .= '[' . $query . ']';
            if ($vars) { 
                $s .= ' with ' . print_r($vars, true);
            }
            $s .= "\n\n";
            $s .= print_r($result, true) . "\n";
            $fp = fopen($SXMLParams['localroot'].'data/queries.log', 'a');
            fwrite($fp, $s);
            fclose($fp);
        }
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
    // Узнаёт доступы, заданные по образцу
    //
    function requestPermissionsFromDB($table, $val, $type = 'visible-to') {
        global $_SXML_VARS;
        if (isset($_SXML_VARS['permrowid'])) {
            $hadpermrowid = true;
            $oldpermrowid = $_SXML_VARS['permrowid'];
        }
        $_SXML_VARS['permrowid'] = $val;
        $str = simpleSelect('sxml:' . $type, '"'.$table.'"', '"rowid" = :permrowid', 'permrowid');
        if ($hadpermrowid) {
            $_SXML_VARS['permrowid'] = $oldpermrowid;
        } else {
            unset($_SXML_VARS['permrowid']);
        }
        return explode(' ', $str);
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
                $name = 'sxml:' . $cols->item($i)->localName;
            } else {
                $name = $cols->item($i)->tagName;
            }
            $data[$name] = $cols->item($i)->textContent;
        }
        return $data;
    }
    
    // Проверяет автора заданной строчки таблицы 
    function isChangeAllowed($el) {
        global $_SXML_VARS;
        $table = $el->hasAttribute('from') ? $el->getAttribute('from') : $el->getAttribute('in');
        if ($el->hasAttribute('open-to')) {
            $allowed =  $el->getAttribute('open-to');
        } elseif ($el->hasAttribute('open-as')) {
            $allowed =  $_SXML_VARS[$el->getAttribute('open-as')];
        } elseif ($el->hasAttribute('open-as-from')) {
            $allowed = simpleSelect('sxml:open-to', $el->getAttribute('open-as-from'), $el->hasAttribute('open-as-where') ? $el->getAttribute('open-as-where') : null, $el->hasAttribute('open-as-uses') ? $el->getAttribute('open-as-uses') : null);
        } else {
            $allowed = '';
        }
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
    
    // Добавляет новую колонку, если её ещё нет
    function ensureColumns($table, $data) {
        $pragma = processRawQuery('pragma table_info("'.$table.'")');
        if (is_array($pragma)) {
            $actualColumns = array();
            foreach($pragma as $i => $row) {
                $actualColumns[] = $row['name'];
            }
            foreach($data as $key => $v) {
                if (!in_array($key, $actualColumns)) {
                    processRawQuery('alter table "'.$table.'" add column "'.$key.'"');
                }
            }
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
            $paramStringAfterWhere .= ' order by ('.$el->getAttribute('order-by').')' . ($el->hasAttribute('order') && $el->getAttribute('order') == 'desc'? ' desc' : ' asc');
        }
        
        $restrictedQuery = 'select '.$what.$paramStringBeforeWhere.' and ("sxml:visible-to" not null) '.$paramStringAfterWhere;
        $restricted = $db->query($restrictedQuery);
        if (!$restricted) {
            logQuery($restrictedQuery, array(), getDB()->errorInfo());
            $restricted = 0;
        } else {
            logQuery($restrictedQuery, array(), $restricted->fetchAll(PDO::FETCH_ASSOC));
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
        $dataKeys = array_unique(array_merge(array_keys($data), array("sxml:editable-to", "sxml:visible-to", "sxml:open-to", "sxml:time", "sxml:user", "sxml:deleted")));
        $createQuery = 'create table if not exists "'.$table.'" ("sxml:item-id" integer primary key autoincrement, "'.join('", "', $dataKeys) .'")';
        $r = getDB()->exec($createQuery);
        if ($r) {
            logQuery($createQuery, array(), true);
        } else {
            logQuery($createQuery, array(), getDB()->errorInfo());
        }
        
        $queryText = 'insert into "'.$table.'" ("'.join('", "', array_keys($data)).'", "sxml:time", "sxml:user") values(('.join('), (', $data).'), \''.date(DATE_ATOM).'\', :user)';
        $queryVars = $el->getAttribute('uses').' user';
        ensureColumns($table, $data);
        $rq = processRawQuery($queryText, $queryVars);
        if (is_array($rq)) {
            $rq = simpleSelect('sxml:item-id', $table, 'rowid=last_insert_rowid();');
        }
        return $rq;
    }
    
    // Обрабатывает инструкцию <sxml:delete/> - допиленный DELETE
    // <sxml:delete from="" id="" open-to="" uses=""/>
    // open-to - кому можно, кроме автора. Проставляет 'deleted' в поле deleted - чтобы при обновлении можно было заметить, что что-то удалилось
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
    // <sxml:edit in="" id="" open-to="" uses="">data</sxml:edit>
    // open-to - кому можно, кроме автора. Проставляет 'deleted' в поле deleted - чтобы при обновлении можно было заметить, что что-то удалилось
    function processEdit($el) {
    
        if (!$el->hasAttribute('in') || !$el->hasAttribute('id')) {
            return 'Неправильный edit';
        }        
        if (!isChangeAllowed($el)) {
            return 'Нельзя редактировать чужое :)';
        } else {
            $table = $el->getAttribute('in');
            $data = getContainedData($el);
            $updateString = '';
            foreach ($data as $field => $content) {
                $data[$field] = '"'.$field.'" = ('.$content.')';
            }
            $queryText = 'update '.$table.' set '.join(', ', $data).', "sxml:time" = \''.date(DATE_ATOM).'\' where ("sxml:item-id" = '.$el->getAttribute('id').')';
            $queryVars = $el->getAttribute('uses');
            ensureColumns($table, $data);
            $rq = processRawQuery($queryText, $queryVars);
            return $rq;
        }

    }
    
    // Обрабатывает сырую транзакцию, возвращает массив результатов, либо строку с ошибкой, либо число — айдишник последней записи
    function processRawQuery($q, $uses = null) {
        global $_SXML_VARS;

        $query = getDB()->prepare($q);
        $vars = explode(' ', $uses);
        preg_match_all('/:(\w+)/', $q, $arr);
        $requiredVars = $arr[1];
        $varList = array();
        if (!$query) {
            $err = getDB()->errorInfo();
            logQuery($q, $uses, $err);
            return 'DB error: '.$err[2];
        }
        foreach($_SXML_VARS as $name => $value) {
            if (in_array($name, $vars) && in_array($name, $requiredVars)) {
                $query->bindValue(':'.$name, $value);
                $varList[$name] = $value;
            }
        }
        if ($query->execute()) {
            $fetch = $query->fetchAll(PDO::FETCH_ASSOC);
            logQuery($q, $varList, $fetch);
            if (count($fetch) > 0) {
                return $fetch;
            } else {
                return array();
            }
        } else {
            $ei = $query->errorInfo();
            logQuery($q, $varList, $ei);
            return 'DB error while processing ' . $q . ': ' . $ei[2];
        }
    }
    
    // Формирует результирующий элемент
    function buildResult($el, $result, $range = false, $total = false) {
        global $_SXML_VARS;

        $doc = $el->ownerDocument;
        if (is_array($result) || /*(is_numeric($result) || $result === true) && */ $el->localName == 'select' || $el->hasAttribute('nook')) { 
            // Если это запрос не на действие, то в любом случае показываем нужный тег 
            if ($el->hasAttribute('store')) {
                if (is_array($result)) {
                    if ($el->hasAttribute('delim')) {
                        $res = array();
                        foreach($result as $i => $r) {
                            $res[] = current($r);
                        }
                        $_SXML_VARS[$el->getAttribute('store')] = join($el->getAttribute('delim'), $res);
                    } else {
                        $_SXML_VARS[$el->getAttribute('store')] = isset($result[0])? current($result[0]) : '';
                    }
                } else {
                    $_SXML_VARS[$el->getAttribute('store')] = $result."";
                }
                return null;
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
                    if (!in_array($child->localName, array('from', 'where', 'what', 'into', 'order-by', 'what', 'tag', 'entry', 'attrs', 'ignore', 'uses', 'store', 'open-to', 'open-'))) {
                        $res->setAttributeNS($child->namespaceURI, $child->nodeName, $child->nodeValue);
                    }
                }
                $attrs = explode(' ', $el->getAttribute('attrs'));
                $ignore = explode(' ', $el->getAttribute('ignore'));
                $oldVars = $_SXML_VARS;
                if (is_array($result)) {  // Иначе в ответе ничего 
                    foreach ($result as $i => $row) {
                        $_SXML_VARS = $oldVars;
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
                            $_SXML_VARS[$name] = $value;
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
                                $rowElem->appendChild($readyElem);
                                processElement($readyElem);
                            }
                        }
                        $res->appendChild($rowElem);
                    }
                }
                $_SXML_VARS = $oldVars;
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
            return createError($doc, 4, 'Ошибка при запросе к базе данных: «'.$result.'»');
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
        if ($res) {
            $el->parentNode->replaceChild($res, $el);
        } else {
            $el->parentNode->removeChild($el);
        }
        return $res;
    }
    
?>