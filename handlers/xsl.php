<?
    // Обработчик файлов XSL
    //
    // Подключает xsl:include в тело, резолвит все урлы,
    // ставит длительное время кеширования.
    
    require_once '../common/sxml.lib.php';
    
    function processXSLIncludes($file, $baseURI = false) {
        $doc = new DOMDocument();
        $filepath = $baseURI ? resolvePath($file, $baseURI) : $file;
        $doc->load($filepath);
        $includes = $doc->getElementsByTagNameNS('http://www.w3.org/1999/XSL/Transform', 'include');
        while ($includes->length > 0) {
            //echo "[at $file: $includes->length includes, pos = $i]\n";        
            $include = $includes->item(0);
            //echo "[processing {$include->getAttribute('href')} included at {$file}]\n";
            $replacementDoc = processXSLIncludes($include->getAttribute('href'), $include->baseURI);
            $replacement = $doc->createDocumentFragment();
            $replacementChild = $replacementDoc->documentElement->firstChild;
            while ($replacementChild) {
                if ($replacementChild->localName != 'output') {
                    $replacement->appendChild($doc->importNode($replacementChild, true));
                }
                $replacementChild = $replacementChild->nextSibling;
            }
            $include->parentNode->replaceChild($replacement, $include);
            //echo "[at $file: $includes->length includes, pos = $i]\n";
        }
        return $doc;
    }
    
    $doc = processXSLIncludes($_SXML['file']);
    header('Content-type: text/xsl');
    print $doc->saveXML();
?>