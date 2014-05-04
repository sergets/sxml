<?
    // Обработчик файлов XSL
    //
    // Подключает xsl:include в тело, резолвит все урлы,
    // ставит длительное время кеширования.
    
    require_once '../common/sxml.lib.php';

    function processIncludes($file, $baseURI = false) {
        $doc = new DOMDocument();
        $filepath = $baseURI ? resolvePath($file, $baseURI) : $file;
        $doc->load($filepath);
        
        // xsl:include
        $xslIncludes = $doc->getElementsByTagNameNS('http://www.w3.org/1999/XSL/Transform', 'include');
        while ($xslIncludes->length > 0) {
            $include = $xslIncludes->item(0);
            $replacementDoc = processIncludes($include->getAttribute('href'), $include->baseURI);
            $replacement = $doc->createDocumentFragment();
            $replacementChild = $replacementDoc->documentElement->firstChild;
            while ($replacementChild) {
                if ($replacementChild->localName != 'output') {
                    $replacement->appendChild($doc->importNode($replacementChild, true));
                }
                $replacementChild = $replacementChild->nextSibling;
            }
            $include->parentNode->replaceChild($replacement, $include);
        }
        
        // xi:include
        $xIncludes = $doc->getElementsByTagNameNS('http://www.w3.org/2001/XInclude', 'include');
        while ($xIncludes->length > 0) {
            $include = $xIncludes->item(0);
            if (file_exists(resolvePath($include->getAttribute('href'), $include->baseURI))) {
                $replacementDoc = processIncludes($include->getAttribute('href'), $include->baseURI);
                $replacement = $doc->importNode($replacementDoc->documentElement, true);
            } else if ($include->getElementsByTagNameNS('http://www.w3.org/2001/XInclude', 'fallback')->length > 0) {
                $replacement = $doc->createDocumentFragment();
                $fallbacks = $include->getElementsByTagNameNS('http://www.w3.org/2001/XInclude', 'fallback');
                $fallbackChild = $fallbacks[0]->firstChild;
                while ($fallbackChild) {
                    $replacement->appendChild($doc->importNode($fallbackChild, true));
                    $fallbackChild = $fallbackChild->nextSibling;
                }
            }
            $include->parentNode->replaceChild($replacement, $include);
        }
        
        return $doc;
    }
    
    $doc = processIncludes($_SXML['file']);
    header('Content-type: text/xsl');
    print $doc->saveXML();
?>