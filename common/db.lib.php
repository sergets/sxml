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
    function processSelect($el, $vars, &$range, &$total) {
        $db = getDB();
        // Специфические параметры
        $from = $el->getAtrribute('from');
        $where = $el->getAtrribute('where');
        $order = $el->getAtrribute('order-by');
        
        // SXML-ные параметры
        $ranges = parseFreeRange(getSXMLAttr($el, 'range'));
        if (!$db->inTransaction()) {
            $trans = true;
            $db->beginTransaction();
        } else {
            $trans = false;
        }
        $db
            ->prepare('drop if exists view `sxml:select`; create temporary view `sxml:select` as select * from ('.$from.') where ('.$where.')')
            ->exec($vars);
        $restricted = $db
            ->prepare('select count(*) from `sxml:select` where (`sxml:visible-to` not null)')
            ->exec()
            ->fetchAll()[0][0];
        if ($restricted > 0 || $ranges[0] === $ranges[1] === $ranges[2] === $ranges[3] === false) {
            $res = processQuery('select * from `sxml:select`');
        } else {
            $total = $db
                ->prepare('select count(*) from `sxml:select`')
                ->exec()
                ->fetchAll()[0][0];
            if ($ranges[0] !=== false) {
                if ($ranges[1] === false) { // 2-
                    $res = processQuery('select * from `sxml:select` limit -1 offset '.$ranges[0]);
                    $range = $ranges[0].'-';
                } else { // 1-10
                    $res = processQuery('select * from `sxml:select` limit '.$ranges[1]-$ranges[0].' offset '.$ranges[0]);
                    $range = $ranges[0].'-'.$ranges[1];
                }
            } else { // 7-4
                $res = processQuery('select * from `sxml:select` limit '.$ranges[2]-$ranges[3].' offset '.$total-$ranges[2]);
                $range = $ranges[2].'-'.$ranges[3];
            }
        }
        $db->prepare('drop if exists view `sxml:select`')->exec();
        if ($trans) {
            $db->commit();
        }
        return $res;
    }
    
    // Обрабатывает инструкцию <sxml:insert/> - допиленный INSERT
    // <sxml:insert to=""><field default="123">some unescaped sql :data</field><field>where :vars are substituded from POST</field></sxml:insert>
    function processInsert($el, $vars) {
        $db = getDB();
        return null;
    }
    
    // Обрабатывает инструкцию <sxml:delete/> - допиленный DELETE
    // <sxml:delete from="" id=""/>
    function processDelete($el, $vars) {
        $db = getDB();
        return null;
    }
    
    // Обрабатывает сырую транзакцию
    function processQuery($query, $vars) {
        return getDB()->prepare($query)->exec($vars)->fetchAll();
    }
    
    // Формирует результирующий элемент
    function buildResult($el, $result, $range = false) {
        global $SXMLParams;
        
        $doc = $doc;
        if (is_array($result)) {
            $res = $doc->createElement($el->getAttribute('tag')); // TODO: tag="sxml:..."
            if ($range) {
                setSXMLAttr($res, 'original-range', $range);
            }
            if ($total) {
                setSXMLAttr($res, 'total', $total);
            }
            foreach ($el->childNodes as $i => $child) {
                if (($child->nodeType === XML_ATTRIBUTE_NODE)
                                && (!in_array(array('from', 'where', 'order-by', 'what', 'tag', 'entry', 'attrs'), $child->name))) {
                    $res->appendChild($child->cloneNode(true)); // TODO: true?
                }
            }
            $attrs = explode(' ', $el->getAttribute('attrs'));
            foreach ($result as $i => $row) {
                $rowElem = $doc->createElement($el->getAttribute('entry')); // TODO: entry="sxml:..."
                foreach ($row as $name => $value) {
                    if ($value != null) {
                        if (in_array($attrs, $name)) {
                            if (substr($name, 0, 5) === 'sxml:') {
                                setSXMLAttr($row, substr($name, 5), $value);
                            } else {
                                $row->setAttribute($name, $value);
                            }
                        } else {
                            if (substr($name, 0, 5) === 'sxml:') {
                                $q = $doc->createElementNS($SXMLParams['ns'], substr($name, 5));
                            } else {
                                $q = $doc->createElement($name);
                            }
                            $q->appendChild($doc->createTextNode($value));
                            $row->appendChild($q);
                        }
                    }
                }
                $res->appendChild($row);
            }
            return $res;
        } elseif ($result === false) { // TODO: false?
            return createError($doc, 4);
        } else {
            return $doc->createElementNS($SXMLParams['ns'], 'ok');
        }
    }
    
    // Общая часть для всех инструкций
    function processAction($el) {
        global $_SXML, $SXMLParams;
        $vars = array();
        foreach($_SXML['vars'] as $name => $value) {
            $vars[':'.$name] = $value;
        }
        if ($el->namespaceURI !== $SXMLParams['ns']) {
            return false;
        }
        $range = false;
        $total = false;
        try {
            switch ($el->tagName) {
                case 'insert':
                    $result = processInsert($el, $vars);
                    break;
                case 'delete':
                    $result = processDelete($el, $vars);
                    break;
                case 'edit':
                    $result = processEdit($el, $vars);
                    break;
                case 'query':
                    $result = processQuery($el->textContent, $vars);
                    break;
                case 'select':
                    $result = processSelect($el, $vars, $range, $total); // TODO Pass-by reference?
                    break;
                case 'query':
                    $result = processQuery($el->textContent, $vars);
                    break;
                default:
                    return false;
            }
        } catch (PDOException $e) {
            $el->parentElement->replaceChild(createError($el->ownerDocument, 3, $e->getMessage()));
        }
        $res = buildResult($el, $result, $range, $total);
        $el->parentElement->replaceChild($res, $el);
    }
    
?>