<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" 
    xmlns="http://www.w3.org/1999/xhtml"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:exsl="http://exslt.org/common"
    xmlns:sxml="http://sergets.ru/sxml"
    xmlns:msxsl="urn:schemas-microsoft-com:xslt"    
    exclude-result-prefixes="exsl msxsl">
  
    <msxsl:script language="JScript" implements-prefix="exslt">
        this["node-set"] = function (x) {
            return x;
        }
    </msxsl:script>
    
    <xsl:output media-type="text/html" method="html"
          omit-xml-declaration="yes"
          doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN"
          doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"
        />
          
    <xsl:variable name="sxml-root" select="'/sxmlight'"/>
    
    <xsl:template name="sxml:page">
        <xsl:param name="scripts"/>
        <xsl:param name="styles"/>
        <xsl:param name="content"/>
        <xsl:param name="title" select="''"/>
        <html>
            <head>
                <title><xsl:value-of select="$title"/></title>
                <meta http-equiv="Content-type" content="text/html; charset=utf-8"/>
                <xsl:for-each select="exsl:node-set($styles)/*">
                    <link rel="stylesheet" type="text/css" href="{.}"/>
                </xsl:for-each>
            </head>
            <body>
                <xsl:attribute name="onload"> <!-- Костыль: при xsl:output method="html" в FF не экранируются амперсанды внутри тега script, а внутри атрибутов экранируются -->
                    return function() {
                        SXML.init({<xsl:apply-templates mode="sxml:quote" select="@sxml:item-id"/>
                            user : '<xsl:apply-templates mode="sxml:quote" select="/*/sxml:data/sxml:user"/>',
                            groups : [ <xsl:for-each select="/*/sxml:data/sxml:my-groups/sxml:group">
                                <xsl:if test="position() &gt; 1">, </xsl:if>'<xsl:apply-templates mode="sxml:quote" select="@id"/>'
                            </xsl:for-each> ],
                            token : '<xsl:apply-templates mode="sxml:quote" select="/*/@sxml:token"/>',
                            <xsl:if test="/*/@sxml:remembered-provider">rememberedProvider : '<xsl:apply-templates mode="sxml:quote" select="/*/@sxml:remembered-provider"/>',</xsl:if>
                            users : {
                                <xsl:for-each select="/*/sxml:data/sxml:found-users/sxml:user">
                                    <xsl:if test="position() &gt; 1">, </xsl:if>
                                    '<xsl:apply-templates mode="sxml:quote" select="@id"/>' : {
                                        name : '<xsl:apply-templates mode="sxml:quote" select="@name"/>', link : '<xsl:apply-templates mode="sxml:quote" select="@link"/>'
                                    }
                                </xsl:for-each>
                            },
                            source : '<xsl:apply-templates mode="sxml:quote" select="/*/@sxml:source"/>',
                            stylesheet : '<xsl:value-of select="substring-before(substring-after(/processing-instruction('xml-stylesheet'), 'href=&quot;'), '&quot;')"/>'
                        });
                    }
                </xsl:attribute>
                <div id="sxml_loginpane">
                    <xsl:call-template name="sxml:if-permitted">
                        <xsl:with-param name="rules">#</xsl:with-param>
                        <xsl:with-param name="then">
                            Вы вошли как <xsl:call-template name="sxml:user"><xsl:with-param name="user" select="/*/sxml:data/sxml:user"/></xsl:call-template>. 
                            <a href="#" class="sxml_logoutlink">Выйти</a>
                        </xsl:with-param>
                        <xsl:with-param name="else">
                            Войти как пользователь: 
                                <a href="#" class="sxml_loginlink vk" title="ВКонтакте"/>
                                <!--a href="#" class="sxml_loginlink facebook" title="Facebook"/>
                                <a href="#" class="sxml_loginlink yandex" title="Яндекс"/>
                                <a href="#" class="sxml_loginlink twitter" title="Twitter"/>
                                <a href="#" class="sxml_loginlink lj" title="Живой Журнал "/>
                                <a href="#" class="sxml_loginlink google" title="Google"/-->
                            </xsl:with-param>
                    </xsl:call-template>
                </div>
                <xsl:choose>
                    <xsl:when test="$content">
                        <xsl:apply-templates select="$content"/>
                    </xsl:when>
                    <xsl:otherwise>
                         <xsl:apply-templates select="/*"/>
                    </xsl:otherwise>
                </xsl:choose>

                <script type="text/javascript" src="//yandex.st/jquery/1.9.0/jquery.js"></script>
                <script type="text/javascript" src="{concat($sxml-root, '/client/sxml.js')}"></script>

                <xsl:for-each select="exsl:node-set($scripts)/*">
                    <script type="text/javascript" src="{.}"></script>
                </xsl:for-each>
                <script type="text/javascript">
                    document.body.onload()();
                </script>
            </body>
        </html>
    </xsl:template>
    
    <xsl:template match="/*/sxml:data"/>
    
    <xsl:template match="*" mode="sxml:user">
        <xsl:call-template name="sxml:user">
            <xsl:with-param name="user" select="./@sxml:user"/>
        </xsl:call-template>
    </xsl:template>
    
    <months>
        <m>января</m>
        <m>февраля</m>
        <m>марта</m>
        <m>апреля</m>
        <m>мая</m>
        <m>июня</m>
        <m>июля</m>
        <m>августа</m>
        <m>сентября</m>
        <m>октября</m>
    </months>
    
    <xsl:template name="sxml:date">
        <xsl:param name="date"/>
        <xsl:variable name="y" select="number(substring($date, 0, 5))"/>
        <xsl:variable name="m" select="number(substring($date, 6, 2))"/>
        <xsl:variable name="d" select="number(substring($date, 9, 2))"/>
        <xsl:variable name="h" select="number(substring($date, 12, 2))"/>
        <xsl:variable name="i" select="substring($date, 15, 2)"/>
        <xsl:variable name="now-y" select="number(substring(/*/sxml:data/sxml:now, 0, 5))"/>
        <xsl:variable name="now-m" select="number(substring(/*/sxml:data/sxml:now, 6, 2))"/>
        <xsl:variable name="now-d" select="number(substring(/*/sxml:data/sxml:now, 9, 2))"/>
        <xsl:variable name="months-raw">
            <m n="31">января</m>
            <m>февраля</m>
            <m n="31">марта</m>
            <m n="30">апреля</m>
            <m n="31">мая</m>
            <m n="30">июня</m>
            <m n="31">июля</m>
            <m n="31">августа</m>
            <m n="30">сентября</m>
            <m n="31">октября</m>
            <m n="30">ноября</m>
            <m n="31">декабря</m>
        </xsl:variable>
        <xsl:choose>
            <xsl:when test="$now-m = $m and $now-y = $y and $now-d = $d">
                сегодня,
            </xsl:when>
            <xsl:when test="
                (
                    ($now-m = $m + 1) and
                    ($now-y = $y) and
                    ($now-d = 1) and
                    ($d = exsl:node-set($months-raw)/*[$m]/@n)
                ) or (
                    ($now-y = $y + 1) and
                    ($now-m = 1) and
                    ($now-d = 1) and
                    ($m = 12) and
                    ($d = 31)
                ) or (
                    ($now-m = $m) and
                    ($now-y = $y) and
                    ($now-d = $d + 1)
                )">
                вчера,
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$d"/><xsl:text> </xsl:text><xsl:value-of select="exsl:node-set($months-raw)/*[$m]"/><xsl:if test="not($now-y = $y)">
                    <xsl:text> </xsl:text><xsl:value-of select="$y"/> г.</xsl:if>,
            </xsl:otherwise>
        </xsl:choose>
        <xsl:value-of select="$h"/>:<xsl:value-of select="$i"/>
    </xsl:template>
    
    <xsl:template match="*" mode="sxml:date">
        <xsl:if test="@sxml:time">
            <span class="sxml_date">
                <xsl:attribute name="ondblclick">return { date : '<xsl:value-of select="@sxml:time"/>' };</xsl:attribute>
                <xsl:call-template name="sxml:date">
                    <xsl:with-param name="date" select="@sxml:time"/>
                </xsl:call-template>
            </span>
        </xsl:if>
    </xsl:template>
    
    <xsl:template name="sxml:pager-generic">
        <xsl:param name="one"/>
        <xsl:param name="few"/>
        <xsl:param name="many"/>
        <xsl:param name="number" select="0"/>
        <xsl:param name="test" select="boolean($number > 0)"/>
        <xsl:param name="target" select="'1-'"/>
        <xsl:param name="class"/>
        <xsl:param name="before" select="'Ещё '"/>
        <xsl:param name="after" select="'...'"/>
        <xsl:if test="$test">
            <div class="{concat('sxml_pager-', $class)}">
                <xsl:attribute name="ondblclick">return { pager : '<xsl:value-of select="$target"/>' };</xsl:attribute>
                <xsl:value-of select="$before"/>
                <xsl:call-template name="sxml:incline">
                    <xsl:with-param name="number" select="number($number)"/>
                    <xsl:with-param name="one" select="$one"/>
                    <xsl:with-param name="few" select="$few"/>
                    <xsl:with-param name="many" select="$many"/>
                </xsl:call-template>
                <xsl:value-of select="$after"/>
            </div>
        </xsl:if>
    </xsl:template>
    
    <xsl:template name="sxml:pager-vk-up">
        <xsl:param name="one"/>
        <xsl:param name="few"/>
        <xsl:param name="many"/>
        <xsl:param name="element"/>
        <xsl:call-template name="sxml:pager-generic">
            <xsl:with-param name="number" select="(exsl:node-set($element)/@sxml:first) - 1"/>
            <xsl:with-param name="target" select="'1-'"/>
            <xsl:with-param name="class" select="'vk-up'"/>
            <xsl:with-param name="one" select="$one"/>
            <xsl:with-param name="few" select="$few"/>
            <xsl:with-param name="many" select="$many"/>
        </xsl:call-template>
        <xsl:variable name="defrange" select="exsl:node-set($element)/@sxml:default-range"/>
        <xsl:variable name="def-first">
            <xsl:choose>
                <xsl:when test="not($defrange)">1</xsl:when>
                <xsl:otherwise><xsl:value-of select="substring-before($defrange, '-')"/></xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="def-last">
            <xsl:choose>
                <xsl:when test="not($defrange)"></xsl:when>
                <xsl:otherwise><xsl:value-of select="substring-after($defrange, '-')"/></xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="def-reverse">
            <xsl:choose>
                <xsl:when test="not($defrange)"><xsl:value-of select="false()"/></xsl:when>
                <xsl:otherwise><xsl:value-of select="boolean($def-first &gt; $def-last)"/></xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="def-length">
            <xsl:choose>
                <xsl:when test="not($def-reverse)"><xsl:value-of select="$def-last - $def-first + 1"/></xsl:when>
                <xsl:otherwise><xsl:value-of select="$def-first - $def-last + 1"/></xsl:otherwise>
            </xsl:choose>
        </xsl:variable>

        <xsl:call-template name="sxml:pager-generic">
            <xsl:with-param name="test" select="exsl:node-set($element)/@sxml:total &gt; $def-length - 1 and (
                (
                    $def-reverse and (
                        not(exsl:node-set($element)/@sxml:total - exsl:node-set($element)/@sxml:first + 1 = $def-first) or
                        not(exsl:node-set($element)/@sxml:total - exsl:node-set($element)/@sxml:last + 1 = $def-last)
                    )
                ) or (
                    not ($def-reverse) and (
                        not(exsl:node-set($element)/@sxml:first = $def-first) or
                        not(exsl:node-set($element)/@sxml:last = $def-last)
                    )
                )
            )"/>
            <xsl:with-param name="target" select="$defrange"/>
            <xsl:with-param name="number" select="$def-length"/>
            <xsl:with-param name="before" select="'Оставить только '"/>
            <xsl:with-param name="class" select="'vk-up allshown'"/>
            <xsl:with-param name="one" select="concat('последний ', $one)"/>
            <xsl:with-param name="few" select="concat('последних ', $few)"/>
            <xsl:with-param name="many" select="concat('последних ', $many)"/>
        </xsl:call-template>
    </xsl:template>
    
    <xsl:template name="sxml:incline">
        <xsl:param name="number"/>
        <xsl:param name="one"/>
        <xsl:param name="few"/>
        <xsl:param name="many"/>
        <xsl:value-of select="$number"/><xsl:text> </xsl:text>
        <xsl:choose>
            <xsl:when test="$number mod 10 &gt; 1 and $number mod 10 &lt; 5 and ($number mod 100 &gt; 15 or $number mod 100 &lt; 10)">
                <xsl:value-of select="$few"/>
            </xsl:when>
            <xsl:when test="$number mod 10 = 1 and ($number mod 100 &gt; 15 or $number mod 100 &lt; 10)">
                <xsl:value-of select="$one"/>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$many"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template name="sxml:if-permitted">
        <xsl:param name="owner" select="''"/>
        <xsl:param name="rules" select="''"/>
        <xsl:param name="also" select="''"/>
        <xsl:param name="then"/>
        <xsl:param name="else"/>
        <xsl:choose>
            <xsl:when test="
                not(/*/sxml:data/sxml:user = '') and (
                    (
                        $owner = /*/sxml:data/sxml:user
                    ) or (
                        (
                            $rules = /*/sxml:data/sxml:user
                            or starts-with($rules, concat(/*/sxml:data/sxml:user, ' '))
                            or contains($rules, concat(' ', /*/sxml:data/sxml:user, ' '))
                            or (contains($rules, concat(' ', /*/sxml:data/sxml:user, ' ')) and substring-after($rules, concat(' ', /*/sxml:data/sxml:user)) = '')
                        ) or (
                            count(/*/sxml:data/sxml:my-groups/sxml:group[
                                $rules = concat('#', @id)
                                or starts-with($rules, concat('#', @id, ' '))
                                or contains($rules, concat(' #', @id, ' '))
                                or (contains($rules, concat(' #', @id)) and substring-after($rules, concat(' #', @id)) = '')
                            ]) &gt; 0
                        )
                    ) or (
                        (
                            $also = /*/sxml:data/sxml:user
                            or starts-with($also, concat(/*/sxml:data/sxml:user, ' '))
                            or contains($also, concat(' ', /*/sxml:data/sxml:user, ' '))
                            or (contains($also, concat(' ', /*/sxml:data/sxml:user)) and substring-after($also, concat(' ', /*/sxml:data/sxml:user)) = '')
                        ) or (
                            count(/*/sxml:data/sxml:my-groups/sxml:group[
                                $also = concat('#', @id)
                                or starts-with($also, concat('#', @id, ' '))
                                or contains($also, concat(' #', @id, ' '))
                                or (contains($also, concat(' #', @id)) and substring-after($also, concat(' #', @id)) = '')
                            ]) &gt; 0
                        )
                    )
                )">
                <xsl:copy-of select="$then"/>
            </xsl:when>
            <xsl:otherwise>
                <xsl:copy-of select="$else"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template name="sxml:replace">
        <xsl:param name="haystack"/>
        <xsl:param name="needle"/>
        <xsl:param name="replace"/>
        <xsl:choose>
            <xsl:when test="contains($haystack, $needle)">
                <xsl:value-of select="substring-before($haystack, $needle)"/>
                <xsl:copy-of select="$replace"/>
                <xsl:call-template name="sxml:replace">
                    <xsl:with-param name="haystack" select="substring-after($haystack, $needle)"/>
                    <xsl:with-param name="needle" select="$needle"/>
                    <xsl:with-param name="replace" select="$replace"/>
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$haystack"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template name="sxml:quote">
        <xsl:param name="v" select="''"/>
        <xsl:call-template name="sxml:replace">
            <xsl:with-param name="haystack" select="$v"/>
            <xsl:with-param name="needle" select="&quot;&apos;&quot;"/>
            <xsl:with-param name="replace" select="&quot;\&apos;&quot;"/>
        </xsl:call-template>
    </xsl:template>
    
    <xsl:template match="*" mode="sxml:quote">
        <xsl:call-template name="sxml:replace">
            <xsl:with-param name="haystack" select="."/>
            <xsl:with-param name="needle" select="&quot;&apos;&quot;"/>
            <xsl:with-param name="replace" select="&quot;\&apos;&quot;"/>
        </xsl:call-template>
    </xsl:template>
    
    <xsl:template name="sxml:user">
        <xsl:param name="user"/>
        <span class="sxml_username"><a href="{concat('http://', /*/sxml:data/sxml:found-users/sxml:user[@id=$user]/@link)}"><xsl:value-of select="/*/sxml:data/sxml:found-users/sxml:user[@id=$user]/@name"/></a></span>
    </xsl:template>
        
    <xsl:template match="*" mode="sxml">
        <xsl:variable name="js"><xsl:apply-templates select="." mode="sxml:js"/></xsl:variable>
        <xsl:variable name="extras"><xsl:apply-templates select="." mode="sxml:extras"/></xsl:variable>
        <xsl:variable name="class"><xsl:apply-templates select="." mode="sxml:class"/></xsl:variable>
        <xsl:attribute name="ondblclick">return { <xsl:if test="not($js = '')"><xsl:value-of select="concat($js, ', ')"/></xsl:if>sxml: {
            <xsl:if test="@sxml:class">class : '<xsl:apply-templates mode="sxml:quote" select="@sxml:class"/>', </xsl:if>
            <xsl:if test="@sxml:item-id">item : '<xsl:apply-templates mode="sxml:quote" select="@sxml:item-id"/>', </xsl:if>
            <xsl:if test="@sxml:id">id : '<xsl:apply-templates mode="sxml:quote" select="@sxml:id"/>', </xsl:if>
            <xsl:if test="@sxml:id or (@sxml:class and @sxml:item-id)">addressable : true, </xsl:if>
            <xsl:if test="@sxml:login-dependent">loginDependent : true,</xsl:if>
            <xsl:if test="@sxml:update">update : [ '<xsl:call-template name="sxml:replace">
                <xsl:with-param name="haystack" select="@sxml:update"/>
                <xsl:with-param name="needle" select="' '"/>
                <xsl:with-param name="replace">', '</xsl:with-param>
                </xsl:call-template>' ], </xsl:if>
            <xsl:if test="@sxml:enumerable">
                enumerable : true,
                total : '<xsl:apply-templates mode="sxml:quote" select="@sxml:total"/>',
                first : '<xsl:apply-templates mode="sxml:quote" select="@sxml:first"/>',
                last : '<xsl:apply-templates mode="sxml:quote" select="@sxml:last"/>',
                <xsl:if test="@sxml:range">range : '<xsl:apply-templates mode="sxml:quote" select="@sxml:range"/>', </xsl:if>
                <xsl:if test="@sxml:default-range">defaultRange : '<xsl:apply-templates mode="sxml:quote" select="@sxml:default-range"/>', </xsl:if>
            </xsl:if>
            source : '<xsl:apply-templates mode="sxml:quote" select="ancestor-or-self::*[@sxml:source][1]/@sxml:source"/>'
            <xsl:if test="not($extras = '')">,<xsl:value-of select="$extras"/></xsl:if>
        } }</xsl:attribute>
        <xsl:attribute name="class"><xsl:if test="not($class = '')"><xsl:value-of select="concat($class, ' ')"/></xsl:if>sxml</xsl:attribute>
    </xsl:template>
    
    <xsl:template match="*" mode="sxml:js"/>
    <xsl:template match="*" mode="sxml:class"/>
    <xsl:template match="*" mode="sxml:extras"/>

</xsl:stylesheet>