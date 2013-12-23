// Класс для реализации событий

var Observable = function() {}

Observable.prototype = {

    on : function(events, callback, ctx) {
    
        $.each(events.split(' '), $.proxy(function(i, name) {
            ((this._observers || (this._observers = {}))[name] || (this._observers[name] = [])).push({ callback : callback, ctx : ctx || this });
        }, this));
        return this;
        
    },
    
    un : function(name, callback) {
    
        $.each(name.split(' '), $.proxy(function(i, name) {
            if (this._observers && this._observers[name]) {
                if (callback) {
                    this._observers[name] = $.grep(this._observers[name], function(o) {
                        return o.callback != callback;
                    });
                }
                if (!callback) {
                    delete this._observers[name];
                }
            }
        }, this));
        return this;
        
    },
    
    trigger : function(name, data) {
        $.each((this._observers && this._observers[name]) || [], function(i, obj) {
            obj.callback.call(obj.context, data);
        });
        return this;
    }

};

var SXML = new Observable();

$.extend(SXML, {

    data: {
    
        user : '',
        groups : [],
        users : {},
        token : '',
        source : '',
        loggedIn : false,

    },
    
    _inited : false,
    _updateIndex : {},
    _entities : {},
    _loginDependentIndex : {},
    _stylesheets : {},
    _entityCounter : 0,
    
    root : $('script').map(function(s) { var m = this.src.match(/(.*)\/client\/sxml.js$/); if (m) return m[1]; })[0],
    NS : 'http://sergets.ru/sxml',
    
    init : function(data) {
    
        if (typeof data.user == 'string') {
            SXML.data.loggedIn = !!data.user;
            SXML.data.user = data.user;
        }
        $.extend(SXML.data.users, data.users);
        if ($.isArray(data.groups)) {
            SXML.data.groups = data.groups;
        }
        SXML.data.token = data.token || SXML.data.token;
        SXML.data.source = data.source || SXML.data.source;
        SXML.data.rememberedProvider = data.rememberedProvider || SXML.data.rememberedProvider;
        SXML.data.stylesheet = data.stylesheet || SXML.data.stylesheet;
        
        // Собираем все блоки

        if (!SXML._inited) {
            SXML.registerChildren(document);
            SXML.login.init();
            SXML._inited = true;
            SXML.trigger('init');
        }
    
    },
    
    registerChildren : function(node) {
    
        var children = $(node).find('.sxml').addBack('.sxml');
        children.each(function() {
            SXML._registerEntity(this);
        });
    
    },
    
    unregisterChildren : function(node) {

        var children = $(node).data('savedChildren') || $(node).find('.sxml').addBack('.sxml');
        Array.prototype.reverse.call(children);
        children.each(function() {
            SXML._unregisterEntity(this);
        });
    
    },
    
    _getEntity : function(node) {
    
        return SXML._entities[$(node).data('sxmlEntityId')];
    
    },
    
    _registerEntity : function(node) {
    
        var dblClk = {};
        if (node.ondblclick) {
            dblClk = node.ondblclick();
        } else if (node.hasAttribute('ondblclick')) {
            dblClk = (new Function(node.getAttribute('ondblclick')))(); // Очень плохо!
        }
        var entity = $.extend(dblClk, {
            domElem : node
        });
        var thisId = ++(SXML._entityCounter);
        $(node).data('sxmlEntityId', thisId);
        SXML._entities[thisId] = entity;
        if (entity.sxml.update && entity.sxml.update.length > 0) {
            $.each(entity.sxml.update, function(i, tag) {
                (SXML._updateIndex[tag] || (SXML._updateIndex[tag] = {}))[thisId] = entity;
            });
        }
        if (entity.sxml.loginDependent) {
            SXML._loginDependentIndex[thisId] = entity;
        }
        
        // Запомнить детей, если просим
        if (entity.sxml.detachableChildren) {
            var children = $(node).find('.sxml').addBack('.sxml');
            $(node).data('savedChildren', children);
            children.data('savingParent', node);
        }
        
        var pagers = $.grep($(node).find('.sxml_pager-vk-up'), function(i) {
            return $(i).closest('.sxml')[0] == node
        });

        $(pagers).click(function(e) {
            SXML.turnPage($(this).closest('.sxml'), this.ondblclick().pager);
        });
        SXML.trigger('register', {
            node : node,
            entity : entity
        });
    
    },
    
    _unregisterEntity : function(node) {
    
        var entityId = $(node).data('sxmlEntityId'),
            entity = SXML._entities[entityId],
            savingParent = $(node).data('savingParent');
        
        if (entity && entity.sxml.update) {
            $.each(entity.sxml.update, function(i, tag) {
                delete SXML._updateIndex[tag][entityId];
            });
        }
        savingParent && $(savingParent).data('savedChildren', $(savingParent).data('savedChildren').not($(node)));

        if (entity.sxml.loginDependent) {
            delete SXML._loginDependentIndex[entityId];
        }
        delete SXML._entities[entityId];
        SXML.trigger('unregister', {
            node : node,
            entity : entity
        });
    
    },


    onParticular : function(event, conditions, callback, ctx, once) {
    
        if (typeof ctx == 'boolean') {
            once = ctx;
            ctx = window;
        }

        var context = this,
            matches = function(obj, pattern) {
                if (typeof pattern == 'object' && typeof obj == 'object') {
                    var res = true;
                    $.each(pattern, function(key, value) {
                        if(!matches(obj[key], value)) {
                            res = false;
                        }
                    });
                    return res;
                } else {
                    return (obj == pattern);
                }
            };
       
        var handler = function(options) {
            if (matches(options.entity, conditions)) {
                callback.call(ctx, options);
                if (once) {
                    context.un(event, handler);
                }
            }
        }

        context.on(event, handler);

    },

    greet : function(conditions, callback, ctx, once) {

        SXML.onParticular('register', conditions, callback, ctx, once);

    },

    goodbye : function(conditions, callback, ctx, once) {

        SXML.onParticular('unregister', conditions, callback, ctx, once);

    },

    exec : function(url, action, params, success, error, ctx) {
    
        if (typeof action !== 'string') { // Если не указан урл, то значит запрос к самому себе 
            ctx = error,
            error = success, 
            success = params,
            params = action,
            action = url,
            url = SXML.data.source;
        }

        if (error && !$.isFunction(error)) { // Если передана только одна функция, то это success.
            ctx = error;
            error = undefined;
        }

        $.ajax(url, {
            type : 'POST',
            data : $.extend(params, {
                'sxml:expect-xml' : true,
                'sxml:action' : action,
                'sxml:token' : SXML.data.token
            }),
            dataType : 'xml',
            success : function(res) { 
                SXML._onExec(action, res, success, error, ctx)
            },
            error : function(res) {
                SXML._onError(action, arguments, error, ctx)
            }
        })
    
    },
    
    _onExec : function(action, res, success, error, ctx) {

        SXML.processResponse(res, function(returnValue) {
            success && success.call(ctx || this, returnValue);
            SXML.trigger('actioncomplete', {
                action : action,
                returned : returnValue
            });
        }, function() {
            error && error.call(ctx || this);        
            SXML.trigger('actionerror', {
                action : action
            });
        });
    
    },
    
    _onError : function(action, args, error, ctx) {

        error && error.call(ctx || this);          
        SXML.Notifier.error('Не удалось выполнить действие «'+ action +'». Ошибка — ' + args[2]);
    
    },
    
    processResponse : function(xml, success, error) {
    
        SXML._updateCommonData(xml);
        
        var isOK, updates = [], returnValue = '', errorMessage = '';
        if (xml instanceof Document) {
            if (xml.documentElement.localName == 'ok' && xml.documentElement.namespaceURI == SXML.NS) {
                isOK = true;
                if (xml.documentElement.hasAttribute('returned')) {
                    returnValue = xml.documentElement.getAttribute('returned');
                }
                var updateList = xml.getElementsByTagNameNS(SXML.NS, 'update');
                $.each(updateList, function(i, updateItem) {
                    updates.push({ tag : updateItem.getAttribute('tag') });
                });
            } else if (xml.documentElement.localName == 'error' && xml.documentElement.namespaceURI == SXML.NS) {
                isOK = false;
                errorMessage = xml.documentElement.getAttribute('message');
            }
        } else {
            isOK = xml.isOK;
            returnValue = xml.returnValue || '';
            updates = xml.updates || [];
            if (!isOK) {
                errorMessage = xml.errorMessage;
            }
        }
        if (isOK) {
            $.each(updates, function(i, up) {
                SXML._updateIndex[up.tag] && SXML.update(SXML._updateIndex[up.tag]);
            });
            SXML.trigger('action');
            success && success(returnValue);
        } else {
            error && error();
            SXML.Notifier.error('Ошибка: ' + errorMessage);
        }

    },
    
    _updateCommonData : function(xml) {
        
        var data = { users : {} };
        
        if (xml instanceof Document) {
            data.user = xml.documentElement.getAttributeNS(SXML.NS, 'user');
            if (dataNode = xml.documentElement.getElementsByTagNameNS(SXML.NS, 'data')[0]) {
                var usersList = dataNode.getElementsByTagNameNS(SXML.NS, 'found-users')[0].getElementsByTagNameNS(SXML.NS, 'user');
                $.each(usersList, function(i, usersItem) {
                    data.users[usersItem.getAttribute('id')] = {
                        name : usersItem.getAttribute('name'),
                        link : usersItem.getAttribute('link')
                    };
                });
                var groupsList = dataNode.getElementsByTagNameNS(SXML.NS, 'my-groups')[0].getElementsByTagName(SXML.NS, ' group');
                if (groupsList.length > 0) {
                    data.groups = [];
                    $.each(groupsList, function(i, group) {
                        data.groups.push(group);
                    });
                }
            }
        } else { // Закачивали json через фреймы
            data.user = xml.user;
            data.users = xml.users;
            data.groups = xml.groups;
        }
        SXML.init(data);
        return true;
        
    },
    
    update : function(index, test) {
    
        // TODO: оптимизировать — удалять не только по родителям, но и по детям
        $.each(index, function(entityId, entity) {
            if (!test || test(entity)) {
                var hasSimilarParents = false;
                $(entity.domElem).parents().each(function() {
                    if (index[$(this).data('sxmlEntityId')] && (!test || test(index[$(this).data('sxmlEntityId')]))) {
                        hasSimilarParents = true;
                    }
                });
                if (!hasSimilarParents) {
                    SXML.updateBlock(entity);
                }
            }
        });
    
    },
    
    _getEntityNonStandardRanges : function(entity) {
    
        var ranges;
        $(entity.domElem).find('.sxml').andSelf().each(function() {
            var id = $(this).data('sxmlEntityId'),
                ent = id && SXML._entities[id];
            if (ent && ent.sxml.enumerable == true && ent.sxml.range !== ent.sxml.defaultRange) {
                ranges = ranges || {};
                ranges[SXML._getEntityURL(ent)] = ent.sxml.range;
            }
        });
        return ranges;
    
    },
    
    _getEntityURL : function(entity, range) {
    
        var url = entity.sxml.source,
            sxmlLocator = '';
            
        if (entity.sxml.id) {
            sxmlLocator = entity.sxml.id;
        } else if (entity.sxml['class'] && entity.sxml.item) {
            sxmlLocator = entity.sxml['class'] + ':' + entity.sxml.item;
        }
        if (range) {
            sxmlLocator += '/' + range;
        }
        if (sxmlLocator) {
            url += (url.indexOf('?') == -1 ? '?' : '&') + sxmlLocator;
        }
        return url;

    },
    
    updateBlock : function(entity, newRange) {
    
        var ranges = SXML._getEntityNonStandardRanges(entity);
        if (newRange) {
            if (ranges) {
                ranges[''] = newRange;
            } else {
                ranges = newRange;
            }
        }
        SXML._loadBlock(entity, ranges, function(doc) {
            $(doc)
                .find('.sxml')
                .each(function() {
                    newEntity = (new Function(this.getAttribute('ondblclick')))(); // FIXME: Очень плохо! 
                    if (newEntity && SXML._compareEntities(entity.sxml, newEntity.sxml)) {
                        SXML._replaceNode(entity.domElem, this);
                    }
                });
        });
    
    },
    
    // pageLocator может быть строкой, либо массивом локаторов для ряда дочерних элементов, содержащим в элементе '' локатор нужной страницы
    _loadBlock : function(entity, pageLocator, onTransform) {
    
        var pageDescription,
            thisLocator;
                     
        if ($.isFunction(pageLocator)) { // Без pageLocator'а
            onTransform = pageLocator;
            pageLocator = false;
        } else if (pageLocator && typeof pageLocator !== 'string') {
            if (thisLocator = pageLocator['']) {
                delete pageLocator[''];
                pageDescription = $.map(pageLocator, function(v, i) { return (v? encodeURIComponent(i) + '/' + encodeURIComponent(v) : null); }).join(':');
                pageLocator = thisLocator;
            } else {
                pageDescription = $.map(pageLocator, function(v, i) { return (v? encodeURIComponent(i) + '/' + encodeURIComponent(v) : null); }).join(':');
                pageLocator = false;
            }
        }
        
        // TODO говнокод!!!
        var url = SXML._getEntityURL(entity, pageLocator)
        if (pageDescription) {
            if (url.indexOf('?') == -1) {
                url += '?sxml:ranges=' + encodeURI(pageDescription) + '&sxml:expect-xml=false';
            } else {
                url = url.replace('?', '?sxml:ranges=' + encodeURI(pageDescription) + '&sxml:expect-xml=false&');
            }
        } else {
            if (url.indexOf('?') == -1) {
                url += '?sxml:expect-xml=false';
            } else {
                url = url.replace('?', '?sxml:expect-xml=false&');
            }        
        }
        
        $.ajax(url, {
            method : 'get',
            dataType : 'xml',
            success : function(response) {
                if (response.documentElement.tagName == 'html') {
                    onTransform(response);
                } else {
                    // TODO Нихрена не трансформируется на клиенте!
                    SXML.applyTransformation(response, SXML.data.stylesheet, onTransform);
                }
            },
            error : function() {
                SXML.Notifier.error('Что-то пошло не так, произошла ошибка загрузки. Попробуйте обновить страницу');
            }
        });    
    
    },
    
    _compareEntities : function(oldEntity, newEntity) {
    
        return (
            (newEntity.source == oldEntity.source) && (
                (!oldEntity.id && !newEntity.id && !oldEntity['class'] && !newEntity['class']) ||
                (oldEntity.id && newEntity.id == oldEntity.id) ||
                (oldEntity['class'] && oldEntity.item && 
                    newEntity['class'] == oldEntity['class'] &&
                    newEntity.item == oldEntity.item)
            )
        );
    
    },
    
    _replaceNode : function(oldNode, newNode) {
    
        // TODO: Здесь будем анализировать на предмет — а поменялось ли вообще что-то?

        var doc = oldNode.ownerDocument,
            importedNode = doc.importNode(newNode, true);
        
        SXML.unregisterChildren(oldNode);
        $(oldNode).replaceWith(newNode);
        SXML.registerChildren(newNode);
        
    
    },
    
    applyTransformation : function(document, stylesheetName, success) {
        
        if (!SXML._stylesheets[stylesheetName]) {
            $.ajax(stylesheetName, {
                method : 'get',
                dataType : 'xml',
                success : function(xml) {
                    SXML._stylesheets[stylesheetName] = xml;
                    var out = SXML._doApplyTransformation(document, xml);
                    if (out) {
                        success(out);
                    } else {
                        
                    }
                }
            })
        } else {
            success(SXML._doApplyTransformation(document, SXML._stylesheets[stylesheetName]));
        }
    
    },
    
    _doApplyTransformation : function(xml, xsl) {

        if (window.ActiveXObject) { // IE
            var out = new ActiveXObject("Microsoft.XMLDOM");
            xml.transformNodeToObject(xsl, out);
            return out;
        } else if (window.XSLTProcessor) { // Mozilla, Firefox, Opera, Safari etc.
            var xsltProcessor = new XSLTProcessor();
            xsltProcessor.importStylesheet(xsl);
            var out = xsltProcessor.transformToDocument(xml);
            if (!out) {
                return false;
            }            
        }

    },
    
    turnPage : function(node, range) {

        SXML.updateBlock(SXML._entities[$(node).data('sxmlEntityId')], range);
    
    }
    
});

// Часть, отвечающая за логин

SXML.login = {

    form : null,
    providers : {
        'vk' : 'ВКонтакте'
    },
    
    init : function() {
    
        SXML.login.pane = $('#sxml_loginpane');
        SXML.login.initLoginLinks();
        SXML.on('login', function() {
            SXML.login.drawPane();
            SXML.update(SXML._loginDependentIndex);
        });
        
        if (SXML.data.rememberedProvider && !SXML.data.loggedIn) {
            SXML.login.trySilentLogin(SXML.data.rememberedProvider);
        }
    
    },
    
    initLoginLinks : function() {
    
        if (SXML.data.loggedIn) {
            $('.sxml_logoutlink').click(SXML.login.logout);
        } else {
            $.each(SXML.login.providers, function(provider, name) {
                $('.sxml_loginlink.' + provider).click(function() {
                    SXML.login.login(provider);
                });
            });
        }
    },
    
    drawLoginLinks : function() {
    
        return $.map(SXML.login.providers, function(name, id) {
            return '<a class="sxml_loginlink ' + id + '" title="' + name + '" href="#"/>';
        }).join('');
    
    },
    
    drawPane : function() {
    
        if (SXML.data.loggedIn) {
            SXML.login.pane.html('Вы вошли как ' + SXML.login.drawUser(SXML.data.user) + '. <a class="sxml_logoutlink" href="#">Выйти</a>');
        } else {
            SXML.login.pane.html('Войти как пользователь: ' + SXML.login.drawLoginLinks());
        }
        SXML.login.initLoginLinks();
    
    },
    
    drawUser : function(userid) {
    
        return '<span class="sxml_username"><a href="' + SXML.data.users[userid].link + '">' + SXML.data.users[userid].name + '</a></span>' 
    
    },
    
    popup : function(url, success, error) {
    
        SXML.login._waitForWindow(
            window.open(url, '', 'width=600,height=400,locationbar=yes,status=no'),
            success, error, 20
        );

    
    },
    
    frame : function(url, success, error, maxCount) {

        SXML.login._waitForWindow(
            $('<iframe/>')
                .css('display', 'none')
                .appendTo('body')
                .attr('src', url)[0]
                .contentWindow,
            success, error
        );

    },
    
    _waitForWindow : function(win, success, error) {
    
        var winId = Math.random(),
            responder = function(data) {
                if (data.winId == winId) {
                    SXML.un('window', responder);
                    win.close();
                    SXML.processResponse(data, success, error);
                    /*if (data.isOK) {
                        success();
                    } else {
                        error();
                    }*/
                }
            }
        win.name = winId;
        SXML.on('window', responder);
    
    },
    
    login : function(provider) {
    
        SXML.login.popup(SXML.root + '/login/?sxml:provider=' + provider, function() {
            SXML.trigger('login');
        });

    },
    
    logout : function() {

        SXML.login.frame(SXML.root + '/login/?sxml:logout=true', function() {
            SXML.trigger('login');
        });

    },
    
    trySilentLogin : function(provider) {

        SXML.login.frame(SXML.root + '/login/?sxml:provider=' + provider, function() {
            SXML.trigger('login');
            delete SXML.data.remeberedProvider;
        }, null, 20);

    }
    
};

// Сообщения

SXML.Notifier = {

    _messages : {},
    
    show : function(text, options) {
        
        var mark = (options && options.mark) || Math.random(),
            timeout = (options && options.timeout) || 5000,
            message = $('<div/>')
                        .addClass('sxml_message')
                        .addClass((options && options['class']) || '')
                        .html(text)
                        .click(function() {
                            SXML.Notifier.close(mark);
                        });
        if (SXML.Notifier._messages[mark]) {
            clearTimeout(SXML.Notifier._messages[mark].timeout);
            SXML.Notifier._messages[mark].message.replaceWith(message);
            SXML.Notifier._messages[mark].message = message;
        } else {
            message.appendTo('#sxml_notifier');
            SXML.Notifier._messages[mark] = {
                message : message
            }
        }
        if (timeout > 0) {
            SXML.Notifier._messages[mark].timeout = setTimeout(function() {
                message.addClass('sxml_disappearing');
                delete SXML.Notifier._messages[mark];
                setTimeout(function() {
                    message.remove()
                }, 500);
            }, timeout)
        }
    
    },
    
    close : function(mark) {
    
        if (SXML.Notifier._messages[mark]) {
            clearTimeout(SXML.Notifier._messages[mark].timeout);
            var message = SXML.Notifier._messages[mark].message;
            message.addClass('sxml_disappearing');
            delete SXML.Notifier._messages[mark];
            setTimeout(function() {
                message.remove()
            }, 500);
        }
    
    },
    
    ok : function(text) {
        
        SXML.Notifier.show(text, { 'class' : 'sxml_ok', timeout : 2000 });
    
    },
    
    error : function(text) {
        
        SXML.Notifier.show(text, { 'class' : 'sxml_error' });
    
    }

};