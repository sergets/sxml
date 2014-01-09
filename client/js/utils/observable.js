define([], function() {

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
                obj.callback.call(obj.ctx, data);
            });
            return this;
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

        }

    };
    
    return Observable;

});