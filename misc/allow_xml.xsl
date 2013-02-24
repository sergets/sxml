<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html"/>

  <xsl:template match="allow-xml">
    <html><body>
      <iframe src="allow_xml.php"/>
    </body></html>
  </xsl:template>
</xsl:stylesheet>