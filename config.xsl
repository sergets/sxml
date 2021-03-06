<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sxml="http://sergets.ru/sxml">
    <xsl:variable name="sxml-config-node">
        <sxml:config>
            <ns>http://sergets.ru/sxml</ns>
            <specialHandlers>
                <err404>../handlers/404.php</err404>
                <err403>../handlers/403.php</err403>
                <upload>../handlers/upload.php</upload>
                <dir>../handlers/directory.php</dir>
            </specialHandlers>
            <handlers>
                <xml>../handlers/xml.php</xml>
                <xsl>../handlers/xsl.php</xsl>
                <xcss>../handlers/css.php</xcss>
            </handlers>
            <dirindex>
                <sxml:item>index.xml</sxml:item>
            </dirindex>
            <queries>
                <sxml:item>select</sxml:item>
                <sxml:item>insert</sxml:item>
                <sxml:item>delete</sxml:item>
                <sxml:item>edit</sxml:item>
                <sxml:item>permit-view</sxml:item>
                <sxml:item>permit-edit</sxml:item>
                <sxml:item>query</sxml:item>
            </queries>
            <paths>
                <login>/login</login>
                <uploadRoot>uploads</uploadRoot>
                <dataRoot>data</dataRoot>
                <dataFile>/data.sqlite</dataFile>
            </paths>
            <accept>
                <sxml:item>image/jpeg</sxml:item>
                <sxml:item>image/png</sxml:item>
            </accept>
            <permissions>
                <upload>#</upload>
            </permissions>
            <jquery>http://yandex.st/jquery/2.1.0/jquery.min</jquery>
        </sxml:config>
    </xsl:variable>
</xsl:stylesheet>