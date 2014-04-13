define([
    'jquery',
    'sxml/sxml',
    'sxml/utils/observable'
], function(
    $,
    sxml,
    Observable
) {
    var defaultOptions = {
        maxFiles : 15
    };

    var Input = function(elem, options) {
        Observable.call(this);
        this._outerDomElem = elem;
        this._options = $.extend({}, defaultOptions, options);
        this._hash = this._makeHash();
        this._val = options.val ? options.val.split(' ') : [];
        this
            ._build()
            ._drawContent();
    };
    
    Input.prototype = $.extend({}, Observable.prototype, {
        _build : function() {
            this._wrapper = $('<div/>')
                .addClass('sxml_fileinput-wrapper')
            this._content = $('<div/>')
                .addClass('sxml_fileinput-content');
            this._outerDomElem
                .append(this._content)
                .append(this._wrapper)

            return this;
        },

        _initInput : function() {
            this._input && this._input.remove();
            if (!this._options.readOnly && this._val.length < this._options.maxFiles) {
                this._input = $('<input/>')
                    .attr('type', 'file')
                    .addClass('sxml_fileinput-input')
                    .bind('change', $.proxy(this._upload, this));
                this._wrapper
                    .removeClass('sxml_fileinput-wrapper-hidden')
                    .append(this._input);
            } else {
                this._wrapper
                    .addClass('sxml_fileinput-wrapper-hidden')
            }
        },
        
        _buildForm : function(input) {
            var iframeName = 'file-' + this._hash,
                iframe = $('<iframe/>')
                    .addClass('sxml_fileinput-iframe')
                    .attr('name', iframeName)
                    .attr('src', 'about:blank'),
                form = $('<form/>')
                    .attr('action', sxml.root + '/actions/upload/')
                    .attr('method', 'post')
                    .attr('target', iframeName)
                    .attr('enctype', 'multipart/form-data')
                    .append($('<input/>')
                        .attr('type', 'hidden')
                        .attr('name', 'hash')
                        .attr('value', this._hash)
                    )
                    .append($('<input/>')
                        .attr('type', 'hidden')
                        .attr('name', 'sxml:token')
                        .attr('value', sxml.data.token)
                    )
                    .append(input.attr('name', 'file'));
                this._wrapper
                    .append(iframe);
            return form;
        },
        
        _makeInput : function() {
            this._input = $('<input/>')
                .attr('type', 'file')
                .attr('name', 'file')
                .addClass('sxml_fileinput-input');
        },
        
        _upload : function() {
            this._input.detach();
            var form = this._buildForm(this._input);
            form.submit();
            this._val.push(this._hash); // TODO multiple files
            this._hash = this._makeHash();
            this._drawContent();
            // TODO new hash
        },
        
        _drawContent : function() {
            var input = this;
            this._content.empty();
            this._val.forEach(function(hash) {
                var src = sxml.root + '/../uploads/' + hash + '?s=50x50',
                    onClick = this._options.readOnly && this._options.onClick
                this._content.append(
                    $('<div/>')
                        .addClass('sxml_fileinput-file')
                        .append(this._options.readOnly ? '' : $('<div/>')
                            .addClass('sxml_fileinput-file-remove')
                            .click(function() {
                                input._val.splice(input._val.indexOf(hash), 1);
                                input._drawContent();
                            })
                        )
                        .append($('<img/>')
                            .css('width', '50px')
                            .css('height', '50px')
                            .attr('src', src)
                            .bind('error', function() {
                                var _this = this;
                                $(this).parent().addClass('sxml_fileinput-file-loading');
                                setTimeout(function() {
                                    $(_this).parent().removeClass('sxml_fileinput-file-loading');
                                    _this.src = src;
                                }, 500)
                            })
                            .click(onClick? $.proxy(function() {
                                onClick.call(this, hash, this.val())
                            }, this) : $.noop)
                        )
                )
            }, this);
            this._initInput();
            return this;
        },

        _makeHash : function() {
            return (Math.round(Math.random()*1e16)).toString(16) + (Math.round(Math.random()*1e16)).toString(16);
        },

        _incHash : function(hash, n) {
            var l = hash.length,
                init = hash.substring(0, l - 8),
                fin = hash.substring(l - 8);
            return init + (Number('0x' + fin) + n).toString(16);
        },

        val : function(data) {
            if (data) {
                this._val = data.split(' ');
                this._drawContent();
                return this;
            } else {
                return this._val.join(' ');
            }
        }
    });
    
    return Input;
});
    
    