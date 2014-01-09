define(['jquery'], function($) {

    return {

        _messages : {},
        
        show : function(text, options) {
            
            var _this = this,
                mark = (options && options.mark) || Math.random(),
                timeout = (options && options.timeout) || 5000,
                message = $('<div/>')
                            .addClass('sxml_message')
                            .addClass((options && options['class']) || '')
                            .click(function() {
                                _this.close(mark);
                            });

            if (typeof text === 'string') {
                message.html(text);
            } else {
                message.append(text);
            }
            if (this._messages[mark]) {
                clearTimeout(this._messages[mark].timeout);
                this._messages[mark].message.replaceWith(message);
                this._messages[mark].message = message;
            } else {
                message.appendTo('#sxml_notifier');
                this._messages[mark] = {
                    message : message
                }
            }
            if (timeout > 0) {
                this._messages[mark].timeout = setTimeout(function() {
                    message.addClass('sxml_disappearing');
                    delete _this._messages[mark];
                    setTimeout(function() {
                        message.remove()
                    }, 500);
                }, timeout)
            }
        
        },
        
        close : function(mark) {
        
            if (this._messages[mark]) {
                clearTimeout(this._messages[mark].timeout);
                var message = this._messages[mark].message;
                message.addClass('sxml_disappearing');
                delete this._messages[mark];
                setTimeout(function() {
                    message.remove()
                }, 500);
            }
        
        },
        
        ok : function(text) {
            
            this.show(text, { 'class' : 'sxml_ok', timeout : 2000 });
        
        },
        
        error : function(text) {
            
            this.show(text, { 'class' : 'sxml_error' });
        
        }

    };
    
});