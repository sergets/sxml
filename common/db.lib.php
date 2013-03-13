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
    function processSelect($el, $vars) {
        $db = getDB();
        return null;
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
        // TODO слишком много ownerDocument
        if (is_array($result)) {
            $res = $el->ownerDocument->createElement($el->getAttribute('tag')); // TODO: tag="sxml:..."
            if ($range) {
                $res->setAttributeNS($SXMLParams['ns'], 'original-range', $range);
            }
            foreach ($el->childNodes as $i => $child) {
                if (($child->nodeType === XML_ATTRIBUTE_NODE)
                                && (!in_array(array('from', 'where', 'order-by', 'what', 'tag', 'entry', 'attrs'), $child->name))) {
                    $res->appendChild($child->cloneNode(true)); // TODO: true?
                }
            }
            $attrs = explode(' ', $el->getAttribute('attrs'));
            foreach ($result as $i => $row) {
                $rowElem = $el->ownerDocument->createElement($el->getAttribute('entry')); // TODO: entry="sxml:..."
                foreach ($row as $name => $value) {
                    if ($value != null) {
                        if (in_array($attrs, $name)) {
                            if (substr($name, 0, 5) === 'sxml:') {
                                $row->setAttributeNS($SXMLParams['ns'], substr($name, 5), $value);
                            } else {
                                $row->setAttribute($name, $value);
                            }
                        } else {
                            if (substr($name, 0, 5) === 'sxml:') {
                                $q = $el->ownerDocument->createElementNS($SXMLParams['ns'], substr($name, 5));
                            } else {
                                $q = $el->ownerDocument->createElement($name);
                            }
                            $q->appendChild($el->ownerDocument->createTextNode($value));
                            $row->appendChild($q);
                        }
                    }
                }
                $res->appendChild($row);
            }
            return $res;
        } elseif ($result === false) { // TODO: false?
            return $el->ownerDocument->createElementNS($SXMLParams['ns'], 'error');
        } else {
            return $el->ownerDocument->createElementNS($SXMLParams['ns'], 'ok');
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
                $result = processSelect($el, $vars, $range); // TODO Pass-by reference?
                break;
            case 'query':
                $result = processQuery($el->textContent, $vars);
                break;
            default:
                return false;
        }
        $res = buildResult($el, $result, $range);
        $el->parentElement->replaceChild($res, $el);
    }
    
?>