define(['jquery', 'sxml/sxml'], function($, sxml) {

    var Input = function(elem, val, options) {
        this._vals = val? val.split(' ') : [];
        this._names = $.extend({}, $.map(sxml.data.users, function(v, i) { return v.name; }));
        this._domElem = $(elem);
        this._options = $.extend({}, {
            permittedTypes : ['user', 'group', 'vk'],
            mutable : true
        }, options);
        this._build();
        this._options.mutable && this._bindListeners();
        this._update();
    };
    
    Input.prototype = {
        _build : function() {
            this._users = $('<span/>')
                .addClass('sxml_rightsinput-users');
            this._domElem
                .addClass('sxml_rightsinput')
                .append(this._users)

            if (this._options.mutable) {
                this._dropdown = $('<div/>')
                    .addClass('sxml_rightsinput-dropdown')
                    .addClass('sxml_rightsinput-hidden');
                this._input = $('<input/>')
                    .addClass('sxml_rightsinput-input')
                    .attr('type', 'text');
                this._domElem
                    .append(this._dropdown)
                    .append(this._input);
            }
        },
        
        _bindListeners : function() {
            this._domElem.click($.proxy(function(e) {
                this._input.focus();
                this._doSuggest();
                e.stopPropagation();
            }, this));
        
            $(document).click($.proxy(function(e) { // TODO (_destroy)
                this._hideDropdown();
            }, this));

            this._input
                .bind('keydown', $.proxy(function(e) {
                    if (e.keyCode == 13) { // enter
                        this._dropdown.find('.sxml_rightsinput-suggestitem.sxml_rightsinput-selected').click();
                        e.preventDefault();
                        e.stopPropagation();
                    } else if (e.keyCode == 8) { // backspace
                        var input = this._input[0];
                        if (input.selectionStart === 0 && input.selectionEnd === 0) {
                            this._vals.pop();
                            this._update(); 
                            e.preventDefault();
                            e.stopPropagation();    
                        }
                    }
                }, this))
                .bind('keyup', $.proxy(function(e) {
                    if (e.keyCode == 38) { // arrow up
                        var current = this._dropdown.find('.sxml_rightsinput-suggestitem.sxml_rightsinput-selected').removeClass('sxml_rightsinput-selected'),
                            prev;
                        if (current[0]) {
                            var prev = current.prev('.sxml_rightsinput-suggestitem');
                            if (!prev[0]) prev = current.parent().find('.sxml_rightsinput-suggestitem:last');
                        }
                        prev.addClass('sxml_rightsinput-selected');
                    } else if (e.keyCode == 40) { // arrow down
                        if (!this._dropdown.hasClass('sxml_rightsinput-hidden') && this._dropdown.find('.sxml_rightsinput-suggestitem').length > 0) {
                            var current = this._dropdown.find('.sxml_rightsinput-suggestitem.sxml_rightsinput-selected').removeClass('sxml_rightsinput-selected'),
                                next;
                            if (current[0]) {
                                next = current.next('.sxml_rightsinput-suggestitem');
                                if (!next[0]) next = current.parent().find('.sxml_rightsinput-suggestitem:first');
                            } else {
                                next = this._dropdown.find('.sxml_rightsinput-suggestitem:first');
                            }
                            next.addClass('sxml_rightsinput-selected');
                        }
                    } else {
                        this._doSuggest();                    
                    }
                }, this));
        },
        
        _buildUser : function(id) {
            var name = this._names[id],
                nameSpan = $('<span/>')
                    .html(name || '...'),
                pane = $('<div/>')
                    .addClass('sxml_rightsinput-user')
                    .append(nameSpan)
            if (this._options.mutable) {
                pane.append($('<div/>')
                    .addClass('sxml_rightsinput-close')
                    .click($.proxy(function() {
                        this._remove(id);
                    }, this))
                );
            }
            if (id.charAt(0) == '#') {
                pane.addClass('sxml_rightsinput-user-group');
            } else if (id.charAt(0) == '=') {
                pane.addClass('sxml_rightsinput-user-inherited');
                name = 'Унаследовано от ' + id.substring(1);
                nameSpan.html(name);
            }
            if (!name) {
                $.ajax(sxml.root + (id.charAt(0) == '#'? '/login/group.php?id=' + id.substring(1) : '/login/name.php?id=' + id), {
                    dataType : 'json',
                    success : function(name) {
                        this._names[id] = name;
                        nameSpan && nameSpan.html(name);
                    },
                    context : this
                });
            };
            return pane;
        },
        
        _buildSuggestItem : function(elem) {
            if (this._options.permittedTypes.indexOf(elem.type) === -1 || this._vals.indexOf(elem.id) !== -1) {
                return null;
            }
            this._names[elem.id] = elem.name;
            return $('<div/>')
                .addClass('sxml_rightsinput-suggestitem')
                .addClass('sxml_rightsinput-suggestitem-' + elem.type)
                .append($('<img/>')
                    .addClass('sxml_rightsinput-suggestupic')
                    .attr('src', elem.userpic))
                .append($('<span/>')
                    .html(elem.name))
                .click($.proxy(function() {
                    this._vals.push(elem.id);
                    this._update();
                }, this));
            
        },        
        
        _doSuggest : function() {
            var prefix = this._input.val();
            if (prefix.length > 0) {
                $.ajax(sxml.root + '/login/suggest.php?find=' + prefix, {
                    dataType : 'json',
                    success : function(a) {
                        this._fillSuggest(a, prefix)
                    },
                    context : this
                });
            } else {
                this._hideDropdown();
            }
        },
        
        _fillSuggest : function(array, prefix) {
            this._dropdown.html('');
            var items = $.map(array, $.proxy(this._buildSuggestItem, this));
            if (items.length !== 0) {
                this._dropdown.append(items);
            } else {
                this._dropdown.append($('<div/>')
                    .addClass('sxml_rightsinput-service')
                    .html('На сайте нет. ')
                    .append($('<a/>')
                        .html('Искать логин vk...') // TODO саджест для наследования
                        .attr('href', '#')
                        .click($.proxy(function(e) {
                            $.ajax(sxml.root + '/login/social-find.php?find=' + prefix, {
                                dataType : 'json',
                                success : this._fillSuggest,
                                context : this
                            });
                            return false;
                        }, this))
                    )
                );
            }
            this._showDropdown();
        },
        
        _hideDropdown : function() {
            this._options.mutable && this._dropdown.addClass('sxml_rightsinput-hidden')
        },
        
        _showDropdown : function() {
            this._options.mutable && this._dropdown.removeClass('sxml_rightsinput-hidden')
        },
        
        _add : function(id) {
            this._vals.push(id);
            this._update();
        },
        
        _remove : function(id) {
            var c;
            while ((c = this._vals.indexOf(id)) !== -1) {
                this._vals.splice(c, 1);
            }
            this._update();
        },
        
        _update : function() {
            this._input && this._input.val('');
            this._hideDropdown();
            this._users.html('');
            $.map(this._vals, $.proxy(function(v) {
                this._users.append(this._buildUser(v));
            }, this));
        },
        
        val : function(val) {
            if (typeof val === 'undefined') {
                return this._vals.join(' ');
            } else {
                this._vals = val.split(' ');
                this._update();
            }
        }
    };

    return Input;

});