define([
    'jquery',
    'sxml/sxml',
    'sxml/utils/observable'
], function(
    $,
    sxml,
    Observable
) {
    if (sxml.globalGallery) {
        return sxml.globalGallery;
    }

    var globalGallery = sxml.globalGallery = new Observable();

    $.extend(globalGallery, {
    
        _domElem : $('<div/>').addClass('sxml_globalgallery hidden'),
        _descrField : $('<div/>').addClass('sxml_globalgallery-descr'),
        _loader : $('<div/>').addClass('sxml_globalgallery-loader hidden'),
        
        _picture : $('<img/>')
            .addClass('sxml_globalgallery-img')
            .click(function() { 
                globalGallery.next();
            }),

        _nextButton : $('<div/>')
            .addClass('sxml_globalgallery-button sxml_globalgallery-button-next')
            .click(function() { 
                globalGallery.next();
            }),

        _prevButton : $('<div/>')
            .addClass('sxml_globalgallery-button sxml_globalgallery-button-prev')
            .click(function() { 
                globalGallery.prev();
            }),
        
        _closeButton : $('<div/>')
            .addClass('sxml_globalgallery-button sxml_globalgallery-button-close')
            .click(function() { 
                globalGallery.hide();
            }),
        
        _sequence : [],
        _current : null,
        _loadingImage : null,
        _loadingThumb : null,
        
        _init : function() {
            this._domElem
                .append($('<div/>')
                    .addClass('sxml_globalgallery-wrapper')
                    .append(this._closeButton)
                    .append(this._prevButton)
                    .append(this._nextButton)
                    .append(this._picture)
                )
                .append(this._descrField)
                .append(this._loader)
                .appendTo($(document.body));
        },
        
        _abort : function() {
            this._loadingImage && (this._loadingImage.onload = $.noop);
            this._loadingThumb && (this._loadingThumb.onload = $.noop);
        },
        
        _replacePicture : function(src) {
            this._picture.attr('src', src);
            // TODO transitions
        },
        
        setSequence : function(seq) {
            this._sequence = seq;
        },
        
        next : function() {
            var current = this._sequence.indexOf(this._current),
                next = current < this._sequence.length - 1? current + 1 : 0;
            this.open(this._sequence[next]);
        },
        
        prev : function() {
            var current = this._sequence.indexOf(this._current),
                prev = current > 0? current - 1 : this._sequence.length - 1;
            this.open(this._sequence[prev]);
        },
        
        hide : function() {
            this.trigger('hide');
            this._abort();
            this._domElem.addClass('hidden');
        },

        open : function(hash, sequence) {
            this.trigger('open', hash);
            if (sequence) {
                this.setSequence(sequence);
            }
            this._abort();
            if (this._domElem.hasClass('hidden')) {
                this._domElem.removeClass('hidden');
                this.trigger('show');
            }
            this._loader.removeClass('hidden');
                
            var imgLoaded = false,
                thumb = this._loadingThumb = new Image(),
                image = this._loadingImage = new Image();
                
            thumb.src = sxml.root + '/../uploads/' + hash + '?s=x100';
            image.src = sxml.root + '/../uploads/' + hash;
            thumb.onload = $.proxy(function() {
                this._current = hash;
                if (!imgLoaded) {
                    this._replacePicture(thumb.src);
                }
            }, this);
            image.onload = $.proxy(function() {
                imageLoaded = true;
                this._replacePicture(image.src);
                this._loader.addClass('hidden');
                this.trigger('load', hash);
            }, this);
        }
    });
    
    globalGallery._init();
    return globalGallery;
});