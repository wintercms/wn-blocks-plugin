/*
 * Blocks FormWidget plugin
 *
 * @TODO:
 * - Remove functionality not used by the Blocks FormWidget
 * - Potentially switch to Editor.js?
 *
 * Data attributes:
 * - data-control="fieldblocks" - enables the plugin on an element
 * - data-option="value" - an option with a value
 *
 * JavaScript API:
 * $('a#someElement').fieldBlocks({...})
 */

+function ($) { "use strict";

    var Base = $.wn.foundation.base,
        BaseProto = Base.prototype

    // FIELD REPEATER CLASS DEFINITION
    // ============================

    var Blocks = function(element, options) {
        this.options   = options
        this.$el       = $(element)
        if (this.options.sortable) {
            this.$sortable = $(options.sortableContainer, this.$el)
        }

        $.wn.foundation.controlUtils.markDisposable(element)
        Base.call(this)
        this.init()
    }

    Blocks.prototype = Object.create(BaseProto)
    Blocks.prototype.constructor = Blocks

    Blocks.DEFAULTS = {
        sortableHandle: '.repeater-item-handle',
        sortableContainer: 'ul.field-repeater-items',
        titleFrom: null,
        minItems: null,
        maxItems: null,
        sortable: false,
        mode: 'list',
        style: 'default',
    }

    Blocks.prototype.init = function() {
        if (this.options.sortable) {
            this.bindSorting()
        }

        this.$el.on('ajaxDone', '> .field-repeater-items > .field-repeater-item > .repeater-item-remove > [data-repeater-remove]', this.proxy(this.onRemoveItemSuccess))
        this.$el.on('ajaxDone', '> .field-repeater-items > .field-repeater-add-item > [data-repeater-add]', this.proxy(this.onAddItemSuccess))
        this.$el.on('click', '> ul > li > .repeater-item-collapse .repeater-item-collapse-one', this.proxy(this.toggleCollapse))
        this.$el.on('click', '> .field-repeater-items > .field-repeater-add-item > [data-repeater-add-group]', this.proxy(this.clickAddGroupButton))
        this.$el.on('mouseover', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemMouseOver))
        this.$el.on('mouseout', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemMouseOut))
        this.$el.on('focus', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemFocus))
        this.$el.on('blur', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemBlur))

        this.$el.one('dispose-control', this.proxy(this.dispose))

        this.togglePrompt()
        this.applyStyle()
    }

    Blocks.prototype.dispose = function() {
        if (this.options.sortable) {
            this.$sortable.sortable('destroy')
        }

        this.$el.off('ajaxDone', '> .field-repeater-items > .field-repeater-item > .repeater-item-remove > [data-repeater-remove]', this.proxy(this.onRemoveItemSuccess))
        this.$el.off('ajaxDone', '> .field-repeater-items > .field-repeater-add-item > [data-repeater-add]', this.proxy(this.onAddItemSuccess))
        this.$el.off('click', '> ul > li > .repeater-item-collapse .repeater-item-collapse-one', this.proxy(this.toggleCollapse))
        this.$el.off('click', '> .field-repeater-items > .field-repeater-add-item > [data-repeater-add-group]', this.proxy(this.clickAddGroupButton))
        this.$el.off('mouseover', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemMouseOver))
        this.$el.off('mouseout', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemMouseOut))
        this.$el.off('focus', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemFocus))
        this.$el.off('blur', '> .field-repeater-items > .field-repeater-item', this.proxy(this.onItemBlur))

        this.$el.off('dispose-control', this.proxy(this.dispose))
        this.$el.removeData('wn.blocks')

        this.$el = null
        this.$sortable = null
        this.options = null

        BaseProto.dispose.call(this)
    }

    // Deprecated
    Blocks.prototype.unbind = function() {
        this.dispose()
    }

    Blocks.prototype.bindSorting = function() {
        var sortableOptions = {
            handle: this.options.sortableHandle,
            nested: false,
            vertical: this.options.mode === 'list',
        }

        this.$sortable.sortable(sortableOptions)
    }

    Blocks.prototype.clickAddGroupButton = function(ev) {
        var $self = this
        var templateHtml = $('> [data-group-palette-template]', this.$el).html(),
            $target = $(ev.target),
            $form = this.$el.closest('form'),
            $loadContainer = $target.closest('.loading-indicator-container')

        $target.ocPopover({
            content: templateHtml
        })

        var $container = $target.data('oc.popover').$container

        // Initialize the scrollpad control in the popup
        $container.trigger('render')

        $container
            .on('click', 'a', function (ev) {
                setTimeout(function() { $(ev.target).trigger('close.oc.popover') }, 1)
            })
            .on('ajaxPromise', '[data-repeater-add]', function(ev, context) {
                $loadContainer.loadIndicator()

                $(window).one('ajaxUpdateComplete', function() {
                    $loadContainer.loadIndicator('hide')
                    $self.togglePrompt()
                    $($self.$el).find('.field-repeater-items > .field-repeater-add-item').each(function () {
                        if ($(this).children().length === 0) {
                            $(this).remove()
                        }
                    })
                })
            })

        $('[data-repeater-add]', $container).data('request-form', $form)
    }

    Blocks.prototype.onRemoveItemSuccess = function(ev) {
        var $target = $(ev.target)

        // Allow any widgets inside a deleted item to be disposed
        $target.closest('.field-repeater-item').find('[data-disposable]').each(function () {
            var $elem = $(this),
                control = $elem.data('control'),
                widget = $elem.data('oc.' + control)

            if (widget && typeof widget['dispose'] === 'function') {
                widget.dispose()
            }
        })

        $target.closest('[data-field-name]').trigger('change.oc.formwidget')
        $target.closest('.field-repeater-item').remove()
        this.togglePrompt()
    }

    Blocks.prototype.onAddItemSuccess = function(ev) {
        window.requestAnimationFrame(() => {
            this.togglePrompt()
            $(ev.target).closest('[data-field-name]').trigger('change.oc.formwidget')
            $(this.$el).find('.field-repeater-items > .field-repeater-add-item').each(function () {
                if ($(this).children().length === 0) {
                    $(this).remove()
                }
            })
        })
    }

    Blocks.prototype.togglePrompt = function () {
        if (this.options.minItems && this.options.minItems > 0) {
            var repeatedItems = this.$el.find('> .field-repeater-items > .field-repeater-item').length,
                $removeItemBtn = this.$el.find('> .field-repeater-items > .field-repeater-item > .repeater-item-remove')

            $removeItemBtn.toggleClass('disabled', !(repeatedItems > this.options.minItems))
        }

        if (this.options.maxItems && this.options.maxItems > 0) {
            var repeatedItems = this.$el.find('> .field-repeater-items > .field-repeater-item').length,
                $addItemBtn = this.$el.find('> .field-repeater-add-item')

            $addItemBtn.toggle(repeatedItems < this.options.maxItems)
        }
    }

    Blocks.prototype.toggleCollapse = function(ev) {
        var $item = $(ev.target).closest('.field-repeater-item'),
            isCollapsed = $item.hasClass('collapsed')

        ev.preventDefault()

        if (this.getStyle() === 'accordion') {
            if (isCollapsed) {
                this.expand($item)
            }
            return
        }

        if (ev.ctrlKey || ev.metaKey) {
            isCollapsed ? this.expandAll() : this.collapseAll()
        }
        else {
            isCollapsed ? this.expand($item) : this.collapse($item)
        }
    }

    Blocks.prototype.collapseAll = function() {
        var self = this,
            items = $(this.$el).children('.field-repeater-items').children('.field-repeater-item')

        $.each(items, function(key, item){
            self.collapse($(item))
        })
    }

    Blocks.prototype.expandAll = function() {
        var self = this,
            items = $(this.$el).children('.field-repeater-items').children('.field-repeater-item')

        $.each(items, function(key, item){
            self.expand($(item))
        })
    }

    Blocks.prototype.collapse = function($item) {
        $item.addClass('collapsed')
        $('.repeater-item-collapsed-title', $item).text(this.getCollapseTitle($item))
    }

    Blocks.prototype.expand = function($item) {
        if (this.getStyle() === 'accordion') {
            this.collapseAll()
        }
        $item.removeClass('collapsed')
    }

    Blocks.prototype.getCollapseTitle = function($item) {
        var $target,
            defaultText = '',
            explicitText = $item.data('collapse-title')

        if (explicitText) {
            return explicitText
        }

        if (this.options.titleFrom) {
            $target = $('[data-field-name="'+this.options.titleFrom+'"]', $item)
            if (!$target.length) {
                $target = $item
            }
        }
        else {
            $target = $item
        }

        var $textInput = $('input[type=text]:first, select:first', $target).first()
        if ($textInput.length) {
            switch($textInput.prop("tagName")) {
                case 'SELECT':
                    return $textInput.find('option:selected').text()
                default:
                    return $textInput.val()
            }
        } else {
            var $disabledTextInput = $('.text-field:first > .form-control', $target)
            if ($disabledTextInput.length) {
                return $disabledTextInput.text()
            }
        }

        return defaultText
    }

    Blocks.prototype.getStyle = function() {
        var style = 'default'

        // Validate style
        if (this.options.style && ['collapsed', 'accordion'].indexOf(this.options.style) !== -1) {
            style = this.options.style
        }

        return style
    }

    Blocks.prototype.applyStyle = function() {
        if (this.options.mode === 'grid') {
            return
        }

        var style = this.getStyle(),
            self = this,
            items = $(this.$el).children('.field-repeater-items').children('.field-repeater-item')

        $.each(items, function(key, item) {
            switch (style) {
                case 'collapsed':
                    self.collapse($(item))
                    break
                case 'accordion':
                    if (key !== 0) {
                        self.collapse($(item))
                    }
                    break
            }
        })
    }

    Blocks.prototype.onItemMouseOver = function(event) {
        event.stopPropagation()

        $(this.$el).find('.field-repeater-item').removeClass('hover')
        $(event.currentTarget).closest('.field-repeater-item').addClass('hover')
    }

    Blocks.prototype.onItemMouseOut = function(event) {
        event.stopPropagation()

        if ($(event.currentTarget).closest('.field-repeater-item').find('.inspector-open').length) {
            return
        }

        $(event.currentTarget).closest('.field-repeater-item').removeClass('hover')
    }

    Blocks.prototype.onItemFocus = function(event) {
        event.stopPropagation()

        $(event.currentTarget).closest('.field-repeater-item').addClass('focus')
    }

    Blocks.prototype.onItemBlur = function(event) {
        event.stopPropagation()

        $(event.currentTarget).closest('.field-repeater-item').removeClass('focus')
    }

    // FIELD REPEATER PLUGIN DEFINITION
    // ============================

    var old = $.fn.fieldBlocks

    $.fn.fieldBlocks = function (option) {
        var args = Array.prototype.slice.call(arguments, 1), result
        this.each(function () {
            var $this   = $(this)
            var data    = $this.data('wn.blocks')
            var options = $.extend({}, Blocks.DEFAULTS, $this.data(), typeof option == 'object' && option)
            if (!data) $this.data('wn.blocks', (data = new Blocks(this, options)))
            if (typeof option == 'string') result = data[option].apply(data, args)
            if (typeof result != 'undefined') return false
        })

        return result ? result : this
    }

    $.fn.fieldBlocks.Constructor = Blocks

    // FIELD REPEATER NO CONFLICT
    // =================

    $.fn.fieldBlocks.noConflict = function () {
        $.fn.fieldBlocks = old
        return this
    }

    // FIELD REPEATER DATA-API
    // ===============

    $(document).render(function() {
        $('[data-control="fieldblocks"]').fieldBlocks()
    })

}(window.jQuery);
