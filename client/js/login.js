define(['jquery'], function($) {

    return {

        form : null,
        providers : {
            'vk' : 'ВКонтакте'
        },
        
        init : function(sxml) {

            this.pane = $('#sxml_loginpane');
            this._sxml = sxml;
            this.initLoginLinks();
            this._sxml.on('login', function() {
                this.drawPane();
                this._sxml.update(this._sxml._loginDependentIndex);
            }, this);
            
            if (this._sxml.data.rememberedProvider && !this._sxml.data.loggedIn) {
                this.trySilentLogin(this._sxml.data.rememberedProvider);
            }
        
        },
        
        initLoginLinks : function() {

            var _this = this;
            if (this._sxml.data.loggedIn) {
                $('.sxml_logoutlink').click(function() {
                    _this.logout();
                });
            } else {
                $.each(this.providers, function(provider, name) {
                    $('.sxml_loginlink.' + provider).click(function() {
                        _this.login(provider);
                    });
                });
            }
        },
        
        drawLoginLinks : function() {
        
            return $.map(this.providers, function(name, id) {
                return '<a class="sxml_loginlink ' + id + '" title="' + name + '" href="#"/>';
            }).join('');
        
        },
        
        drawPane : function() {
        
            if (this._sxml.data.loggedIn) {
                this.pane.html('Вы вошли как ' + this.drawUser(this._sxml.data.user) + '. <a class="sxml_logoutlink" href="#">Выйти</a>');
            } else {
                this.pane.html('Войти как пользователь: ' + this.drawLoginLinks());
            }
            this.initLoginLinks();
        
        },
        
        drawUser : function(userid) {
        
            return '<span class="sxml_username"><a href="' + this._sxml.data.users[userid].link + '">' + this._sxml.data.users[userid].name + '</a></span>' 
        
        },
        
        popup : function(url, success, error) {
        
            this._waitForWindow(
                window.open(url, '', 'width=600,height=400,locationbar=yes,status=no'),
                success, error
            );

        
        },
        
        frame : function(url, success, error, maxCount) {

            this._waitForWindow(
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
                sxml = this._sxml,
                responder = function(data) {
                    if (data.winId == winId) {
                        sxml.un('window', responder);
                        win.close();
                        sxml.processResponse(data, success, error);
                        /*if (data.isOK) {
                            success();
                        } else {
                            error();
                        }*/
                    }
                }
            win.name = winId;
            sxml.on('window', responder);
        
        },
        
        login : function(provider) {
        
            var sxml = this._sxml;
            this.popup(sxml.root + '/login/?sxml:provider=' + provider, function() {
                sxml.trigger('login');
            });

        },
        
        logout : function() {

            var sxml = this._sxml;
            this.frame(sxml.root + '/login/?sxml:logout=true', function() {
                sxml.trigger('login');
            });

        },
        
        trySilentLogin : function(provider) {

            var sxml = this._sxml;
            this.frame(sxml.root + '/login/?sxml:provider=' + provider, function() {
                sxml.trigger('login');
                delete sxml.data.remeberedProvider;
            }, null, 20);

        }
        
    };
    
});