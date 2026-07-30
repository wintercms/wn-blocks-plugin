<div class="field-blocks"
    data-control="fieldblocks"
    <?= $titleFrom ? 'data-title-from="'.$titleFrom.'"' : '' ?>
    <?= $minItems ? 'data-min-items="'.$minItems.'"' : '' ?>
    <?= $maxItems ? 'data-max-items="'.$maxItems.'"' : '' ?>
    <?= $style ? 'data-style="'.$style.'"' : '' ?>
    data-mode="<?= $mode ?>"
    data-add-handler="<?= $this->getEventHandler('onAddItem') ?>"
    data-copy-handler="<?= $this->getEventHandler('onCopyItem') ?>"
    data-block-codes="<?= e(implode(',', array_keys($groupDefinitions))) ?>"
    <?php if ($mode === 'grid'): ?> data-columns="<?= $columns ?>" <?php endif ?>
    <?php if ($sortable) : ?>
    data-sortable="true"
    data-sortable-container="#<?= $this->getId('items') ?>"
    data-sortable-handle=".<?= $this->getId('items') ?>-handle"
    <?php endif; ?>
>
    <?php if (!$this->previewMode): ?>
        <input type="hidden" name="<?= $this->getFieldName(); ?>">
    <?php endif ?>

    <ul id="<?= $this->getId('items') ?>" class="field-repeater-items field-block-items">
        <?php foreach ($formWidgets as $index => $widget) : ?>
            <?= $this->makePartial('block_item', [
                'widget' => $widget,
                'indexValue' => $index,
                'height' => ($mode === 'grid') ? $rowHeight : null,
            ]) ?>
        <?php endforeach ?>

        <?= $this->makePartial('block_add_item', [
            'useGroups' => $useGroups,
            'height' => ($mode === 'grid') ? $rowHeight : null,
        ]) ?>
    </ul>

    <?php if (!$this->previewMode) : ?>
        <input type="hidden" name="<?= $this->alias; ?>_loaded" value="1">
    <?php endif ?>

    <script type="text/template" data-group-palette-template>
        <div class="popover-head">
            <h3><?= e(trans($prompt)) ?></h3>
            <button type="button" class="close"
                data-dismiss="popover"
                aria-hidden="true">&times;</button>
        </div>
        <div class="blocks-group-search-container">
            <div>
                <label for="blocks-group-search-<?= $this->getId() ?>" class="sr-only">Search items</label>
                <i class="icon-search"></i>
                <input type="text"
                    id="blocks-group-search-<?= $this->getId() ?>"
                    class="form-control blocks-group-search"
                    placeholder="Search items..."
                    autocomplete="off">
                <button type="button" class="blocks-group-search-clear">
                    <i class="icon-close"></i>
                </button>
            </div>
        </div>
        <div class="blocks-group-no-results">
            No items found
        </div>
        <div class="popover-fixed-height blocks-group-items-container">
            <div class="control-scrollpad" data-control="scrollpad">
                <div class="scroll-wrapper">

                    <div class="control-filelist filelist-hero blocks-group-grid" data-control="filelist">
                        <?php foreach ($groupDefinitions as $item) : ?>
                            <div class="blocks-group-item">
                                <a
                                    href="javascript:;"
                                    data-repeater-add
                                    data-request="<?= $this->getEventHandler('onAddItem') ?>"
                                    data-request-data="_repeater_group: '<?= $item['code'] ?>'">
                                    <i class="<?= $item['icon'] ?>"></i>
                                    <div>
                                        <span class="title"><?= e(trans($item['name'])) ?></span>
                                        <span class="description"><?= e(trans($item['description'])) ?></span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach ?>
                    </div>

                </div>
            </div>
        </div>
    </script>

    <?php
    /*
     * Inline bootstrap for copy/cut/paste/duplicate of blocks. Loaded inline
     * (rather than only via addJs) so it is guaranteed to reach the page
     * regardless of widget asset-path resolution or the asset combiner. A
     * global guard ensures the handlers are registered only once even when
     * several block widgets render on the same page.
     *
     * Copying/duplicating a block round-trips to the server (onCopyItem),
     * which builds the item's Form widget and calls getSaveData() — this
     * correctly captures every field type (switches, mediafinders, nested
     * repeaters) which a client-side DOM scrape cannot. Pasting posts the
     * clipboard payload back to onAddItem as `_paste_data`, and the server
     * renders the new item fully populated.
     */
    ?>
    <script>
    (function () {
        if (window.__blockCopyPasteInit) { return; }
        window.__blockCopyPasteInit = true;

        var CLIPBOARD_KEY = 'wnBlocksClipboard';

        // --- inject toolbar styles once ------------------------------------
        // Kept here (rather than only in blocks.less) so the per-block toolbar
        // (collapse / copy / cut / paste / duplicate / config / delete) renders
        // correctly even if the compiled CSS is stale.
        (function injectToolbarCss() {
            if (document.getElementById('wn-blocks-toolbar-css')) { return; }
            var css =
                '.field-block-item>.repeater-item-remove.block-item-toolbar{width:auto!important;' +
                'height:auto!important;display:inline-flex!important;align-items:center;' +
                'gap:1px;top:4px;right:5px;white-space:nowrap}' +
                '.block-item-action{float:none;flex:0 0 auto;display:inline-flex;' +
                'align-items:center;justify-content:center;width:22px;height:22px;padding:0;' +
                'margin:0;border:0;background:none;cursor:pointer;color:#333;opacity:.6;' +
                'font-size:13px;line-height:1;border-radius:3px;' +
                'transition:background .15s,color .15s,opacity .15s}' +
                '.block-item-action>i{line-height:1}' +
                '.block-item-action:hover,.block-item-action:focus{opacity:1;' +
                'background:rgba(0,0,0,.06);color:#333;text-decoration:none}' +
                '.block-item-action-remove:hover{color:#cc3300}' +
                '.field-block-item.collapsed>.repeater-item-remove .repeater-item-collapse-one' +
                '{transform:rotate(180deg)}' +
                // Dim-show the toolbar on blocks where clipboard paste is available so the
                // user sees the paste icon without having to hover. Full opacity on hover.
                '.field-block-item.has-paste>.repeater-item-remove.block-item-toolbar{opacity:.45!important}' +
                '.field-block-item.has-paste.hover>.repeater-item-remove.block-item-toolbar,' +
                '.field-block-item.has-paste.focus>.repeater-item-remove.block-item-toolbar{opacity:1!important}';
            var style = document.createElement('style');
            style.id = 'wn-blocks-toolbar-css';
            style.textContent = css;
            (document.head || document.documentElement).appendChild(style);
        })();

        // --- safe sessionStorage helpers -----------------------------------
        function ssGet(key) {
            try { return window.sessionStorage.getItem(key); } catch (e) { return null; }
        }
        function ssSet(key, val) {
            try { window.sessionStorage.setItem(key, val); } catch (e) {}
        }

        // --- block clipboard (copy / cut / paste) -------------------------
        function getClipboard() {
            try {
                var raw = ssGet(CLIPBOARD_KEY);
                return raw ? JSON.parse(raw) : null;
            } catch (e) { return null; }
        }

        // Copy a block item's full saved data to the clipboard via the server.
        // A server round-trip (onCopyItem -> Form::getSaveData) captures every
        // field type correctly — switches, mediafinders, nested repeaters — which
        // a client-side DOM scrape cannot. Stored payload: { group, config, data }.
        // `done(payload)` runs once the clipboard has been set.
        function copyItemToClipboard(li, done) {
            if (typeof $ === 'undefined' || !li) { return; }
            var fieldBlocks = li.closest('.field-blocks');
            var handler = fieldBlocks && fieldBlocks.getAttribute('data-copy-handler');
            var index = li.getAttribute('data-block-index');
            var group = li.getAttribute('data-block-group');
            if (!handler || index === null) { return; }
            $(fieldBlocks).request(handler, {
                data: { _repeater_index: index, _repeater_group: group },
                success: function (response) {
                    var payload;
                    try { payload = JSON.parse(response.result); } catch (e) { return; }
                    if (!payload || !payload.group) { return; }
                    ssSet(CLIPBOARD_KEY, JSON.stringify(payload));
                    updatePasteButtons();
                    if (typeof done === 'function') { done(payload); }
                }
            });
        }

        // Is the given block code offered by this widget? Reads the explicit
        // data-block-codes list rendered server-side on the .field-blocks element.
        function blockTypeAvailable(fieldBlocks, group) {
            if (!fieldBlocks) { return false; }
            var list = fieldBlocks.getAttribute('data-block-codes') || '';
            return list.split(',').indexOf(group) !== -1;
        }

        // Show/hide paste affordances based on clipboard state and widget availability.
        // Per-item paste buttons show only when the clipboard holds a block this
        // widget accepts. (The append case is handled in the Add-Item palette.)
        function updatePasteButtons() {
            var cb = getClipboard();
            var ok = cb && cb.group;
            document.querySelectorAll('[data-block-paste]').forEach(function (btn) {
                var fieldBlocks = btn.closest('.field-blocks');
                var show = ok && blockTypeAvailable(fieldBlocks, cb.group);
                btn.style.display = show ? '' : 'none';
                // Add/remove has-paste on the parent li so the CSS can dim-show the toolbar.
                var li = btn.closest('.field-block-item');
                if (li) { li.classList.toggle('has-paste', !!show); }
            });
        }

        // The onAddItem AJAX handler name, rendered server-side on .field-blocks.
        function findAddHandler(fieldBlocks) {
            return fieldBlocks ? fieldBlocks.getAttribute('data-add-handler') : null;
        }

        // onAddItem returns an empty add-item plus a fresh one; the core popover
        // flow removes the empty leftovers afterwards, but our direct requests
        // bypass that — so replicate the cleanup ourselves to avoid stray
        // "Add new item" buttons piling up.
        function cleanupAddItems(fieldBlocks) {
            if (typeof $ === 'undefined' || !fieldBlocks) { return; }
            $(fieldBlocks).find('.field-repeater-items > .field-repeater-add-item')
                .each(function () {
                    if (this.children.length === 0) { $(this).remove(); }
                });
        }

        // Fire onAddItem with the copied block's data so the server renders the
        // new item fully populated. We only remember where to move the new <li>
        // once it arrives; the server handles all field population.
        function requestPaste(fieldBlocks, payload, afterLi) {
            var handler = findAddHandler(fieldBlocks);
            if (!handler || typeof $ === 'undefined' || !payload || !payload.group) { return; }
            window.__pendingPasteMove = { afterLi: afterLi || null };
            $(window).one('ajaxUpdateComplete', function () {
                cleanupAddItems(fieldBlocks);
            });
            $(fieldBlocks).request(handler, {
                data: {
                    _repeater_group: payload.group,
                    _paste_data: JSON.stringify(payload.data || {}),
                    _paste_config: payload.config || ''
                }
            });
        }

        // Collapse chevron (moved into the toolbar, so the core delegated handler
        // — bound to .repeater-item-collapse .repeater-item-collapse-one — no longer
        // fires on it). Toggle the item's collapsed state ourselves; the CSS handles
        // the rest. Document-level delegation also covers dynamically added blocks.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.block-item-toolbar .repeater-item-collapse-one');
            if (!btn) { return; }
            e.preventDefault();
            e.stopPropagation();
            var item = btn.closest('.field-repeater-item');
            if (item) { item.classList.toggle('collapsed'); }
        });

        // Copy button: place the block's field values on the clipboard (non-destructive).
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-copy]');
            if (!btn) { return; }
            e.preventDefault();
            e.stopPropagation();
            var li = btn.closest('.field-block-item');
            copyItemToClipboard(li);
        });

        // Duplicate button: clone this block in place (insert a copy right after it)
        // and also place it on the clipboard so it can be pasted into other widgets.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-duplicate]');
            if (!btn) { return; }
            e.preventDefault();
            e.stopPropagation();
            var li = btn.closest('.field-block-item');
            if (!li) { return; }
            copyItemToClipboard(li, function (payload) {
                requestPaste(li.closest('.field-blocks'), payload, li);
            });
        });

        // Cut button: copy then trigger the existing remove button (with confirm).
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-cut]');
            if (!btn) { return; }
            e.stopPropagation();
            var li = btn.closest('.field-block-item');
            if (!li) { return; }
            copyItemToClipboard(li, function () {
                var removeBtn = li.querySelector('[data-repeater-remove]');
                if (removeBtn) { removeBtn.click(); }
            });
        });

        // Remember which widget's Add-Item palette is open, so a paste entry
        // injected into the (body-level) popover knows where to insert.
        document.addEventListener('click', function (e) {
            var add = e.target.closest('[data-repeater-add-group]');
            if (add) { window.__activeBlocksWidget = add.closest('.field-blocks'); }
        });

        // Inject a "Paste block" entry at the top of the Add-Item palette grid.
        // Looks identical to real block items. Injected once per palette open;
        // subsequent MutationObserver calls hit the early-exit guard (matching
        // data-paste-group) and make no DOM changes, so the observer loop stops.
        function injectPalettePaste(grid) {
            var cb = getClipboard();
            var fieldBlocks = window.__activeBlocksWidget;
            var show = !!(cb && cb.group && blockTypeAvailable(fieldBlocks, cb.group));

            var existing = grid.querySelector('.blocks-paste-item');
            if (existing) {
                // Already injected with the right group — nothing to do.
                if (show && existing.dataset.pasteGroup === cb.group) { return; }
                existing.remove();
            }
            if (!show) { return; }

            var capturedFieldBlocks = fieldBlocks;
            var capturedGroup = cb.group;
            var capturedPayload = cb;

            var a = document.createElement('a');
            a.href = 'javascript:;';
            a.innerHTML =
                '<i class="icon-paste"></i>' +
                '<div><span class="title">Paste block</span>' +
                '<span class="description">Insert copied block</span></div>';
            a.addEventListener('click', function () {
                requestPaste(capturedFieldBlocks, capturedPayload, null);
            });

            var item = document.createElement('div');
            item.className = 'blocks-group-item blocks-paste-item';
            item.dataset.pasteGroup = capturedGroup;
            item.appendChild(a);
            grid.insertBefore(item, grid.firstChild);
        }

        // Paste button on each block item — inserts the copied block immediately
        // after it. Fires onAddItem; the MutationObserver moves the new <li> into place.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-paste]');
            if (!btn || !btn.closest('.field-block-item')) { return; }
            e.preventDefault();
            e.stopPropagation();
            var cb = getClipboard();
            if (!cb || !cb.group) { return; }
            var li = btn.closest('.field-block-item');
            requestPaste(li.closest('.field-blocks'), cb, li);
        });

        // --- refresh rich text / code editors after a paste fill ----------
        // The server fills the new item's fields directly into the DOM, which
        // bypasses the richeditor/codeeditor widgets' own init — so their visual
        // editor still shows empty. Re-sync them from the underlying textarea
        // once the item is in place.
        function refreshRichWidgets(li) {
            if (typeof $ === 'undefined' || !li) { return; }
            $(li).find('[data-control="richeditor"]').each(function () {
                var $el = $(this);
                if ($el.data('oc.richeditor') && typeof $el.richEditor === 'function') {
                    $el.richEditor('setContent', $el.val());
                }
            });
            $(li).find('[data-control="codeeditor"]').each(function () {
                var $el = $(this);
                if ($el.data('oc.codeeditor') && typeof $el.codeEditor === 'function') {
                    $el.codeEditor('refresh');
                }
            });
        }

        // --- shared init + observer ----------------------------------------
        function runAll() {
            document.querySelectorAll('.blocks-group-grid').forEach(injectPalettePaste);
        }

        runAll();
        updatePasteButtons();
        document.querySelectorAll('.field-blocks').forEach(cleanupAddItems);

        var scheduled = false;
        var pendingNewNodes = false;
        var observer = new MutationObserver(function (mutations) {
            // When a paste is pending, find the newly added block <li>, move it after
            // the source block, and fill its fields — all before the debounced runAll fires.
            if (window.__pendingPasteMove) {
                var pending = window.__pendingPasteMove;
                for (var i = 0; i < mutations.length; i++) {
                    mutations[i].addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && node.classList &&
                                node.classList.contains('field-block-item')) {
                            window.__pendingPasteMove = null;
                            // The server already populated the fields; we only
                            // move the new item to sit after the source block,
                            // then refresh any rich text / code editor widgets.
                            if (pending.afterLi && pending.afterLi.parentNode) {
                                pending.afterLi.parentNode.insertBefore(
                                    node, pending.afterLi.nextSibling
                                );
                            }
                            refreshRichWidgets(node);
                        }
                    });
                }
            }
            // Track whether any element nodes were added in this batch.
            // updatePasteButtons and cleanupAddItems are called explicitly by
            // copy/cut/duplicate handlers; the observer only needs to run them
            // when the DOM gains new elements (e.g. a block was added).
            if (!pendingNewNodes) {
                pendingNewNodes = mutations.some(function (m) { return m.addedNodes.length > 0; });
            }
            if (scheduled) { return; }
            scheduled = true;
            setTimeout(function () {
                scheduled = false;
                var hadNewNodes = pendingNewNodes;
                pendingNewNodes = false;
                runAll();
                if (hadNewNodes) {
                    updatePasteButtons();
                    document.querySelectorAll('.field-blocks').forEach(cleanupAddItems);
                }
            }, 0);
        });
        function startObserving() {
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            } else {
                document.addEventListener('DOMContentLoaded', startObserving);
            }
        }
        startObserving();
    })();
    </script>
</div>
