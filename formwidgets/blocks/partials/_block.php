<div class="field-blocks"
    data-control="fieldblocks"
    <?= $titleFrom ? 'data-title-from="'.$titleFrom.'"' : '' ?>
    <?= $minItems ? 'data-min-items="'.$minItems.'"' : '' ?>
    <?= $maxItems ? 'data-max-items="'.$maxItems.'"' : '' ?>
    <?= $style ? 'data-style="'.$style.'"' : '' ?>
    data-mode="<?= $mode ?>"
    data-add-handler="<?= $this->getEventHandler('onAddItem') ?>"
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
                                    data-block-code="<?= $item['code'] ?>"
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
     * Inline bootstrap for block widget enhancements:
     *   - Collapsible sections (persisting open/closed state per section)
     *   - Recently used blocks pinned to the top of the "add block" palette
     *
     * Loaded inline (rather than only via addJs) so it is guaranteed to reach the
     * page regardless of widget asset-path resolution or the asset combiner. A
     * global guard ensures the handlers are registered only once even when several
     * block widgets render on the same page.
     *
     * Collapse behaviour lives on the data-block-collapsible attribute, which the
     * core form widget's bindCollapsibleSections() never selects — so core cannot
     * re-collapse or double-bind these sections when a nested repeater adds an item.
     */
    ?>
    <script>
    (function () {
        if (window.__blockEnhancementsInit) { return; }
        window.__blockEnhancementsInit = true;

        var READY = 'block-collapsible-ready';
        var COLLAPSE_PREFIX = 'wnBlocksCollapse:';
        var RECENT_KEY = 'wnBlocksRecent';
        var RECENT_MAX = 6;
        var CLIPBOARD_KEY = 'wnBlocksClipboard';

        // --- inject toolbar styles once ------------------------------------
        // Kept here (rather than only in blocks.less) so the per-block toolbar
        // (collapse / cut / paste / duplicate / config / delete) renders
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
                '.blocks-group-item.blocks-paste-item a .title{color:#0072bc}' +
                '.blocks-group-item.blocks-paste-item i{color:#0072bc}';
            var style = document.createElement('style');
            style.id = 'wn-blocks-toolbar-css';
            style.textContent = css;
            (document.head || document.documentElement).appendChild(style);
        })();

        // --- safe localStorage helpers -------------------------------------
        function lsGet(key) {
            try { return window.localStorage.getItem(key); } catch (e) { return null; }
        }
        function lsSet(key, val) {
            try { window.localStorage.setItem(key, val); } catch (e) {}
        }

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

        // Serialize all named inputs in a block <li> by their trailing [key] segment.
        // The _group hidden input gives us the block type; all other inputs are field data.
        // Nested blocks fields store their JSON in a hidden input, so they serialize cleanly.
        function serializeBlockItem(li) {
            var data = { group: null, fields: {} };
            li.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
                var m = el.name.match(/\[([^\[\]]+)\]$/);
                if (!m) { return; }
                var key = m[1];
                if (key === '_group') {
                    if (!data.group) { data.group = el.value; }
                } else {
                    data.fields[key] = el.value;
                }
            });
            return data;
        }

        // Fill form inputs in a newly added <li> from stored field values.
        // Matches by trailing [key] segment — works for flat fields and JSON hidden inputs.
        function fillFromClipboard(li, fields) {
            li.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
                var m = el.name.match(/\[([^\[\]]+)\]$/);
                if (!m) { return; }
                var key = m[1];
                if (Object.prototype.hasOwnProperty.call(fields, key)) {
                    el.value = fields[key];
                    // Dispatch change so any widget listeners (e.g. select2, codemirror) react.
                    el.dispatchEvent(new Event('change', { bubbles: true }));
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
                btn.style.display = (ok && blockTypeAvailable(fieldBlocks, cb.group)) ? '' : 'none';
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

        // Fire onAddItem on a widget, with the empty-add-item cleanup applied once
        // the AJAX update has been applied.
        function requestAdd(fieldBlocks, group, fields, afterLi) {
            var handler = findAddHandler(fieldBlocks);
            if (!handler || typeof $ === 'undefined') { return; }
            window.__pendingPaste = { fields: fields, afterLi: afterLi || null };
            $(window).one('ajaxUpdateComplete', function () {
                cleanupAddItems(fieldBlocks);
            });
            $(fieldBlocks).request(handler, { data: { _repeater_group: group } });
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
            if (!li) { return; }
            ssSet(CLIPBOARD_KEY, JSON.stringify(serializeBlockItem(li)));
            updatePasteButtons();
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
            var data = serializeBlockItem(li);
            if (!data.group) { return; }
            ssSet(CLIPBOARD_KEY, JSON.stringify(data));
            updatePasteButtons();
            requestAdd(li.closest('.field-blocks'), data.group, data.fields, li);
        });

        // Cut button: copy then trigger the existing remove button (with confirm).
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-cut]');
            if (!btn) { return; }
            e.stopPropagation();
            var li = btn.closest('.field-block-item');
            if (!li) { return; }
            ssSet(CLIPBOARD_KEY, JSON.stringify(serializeBlockItem(li)));
            updatePasteButtons();
            var removeBtn = li.querySelector('[data-repeater-remove]');
            if (removeBtn) { removeBtn.click(); }
        });

        // Remember which widget's Add-Item palette is open, so a paste entry
        // injected into the (body-level) popover knows where to insert.
        document.addEventListener('click', function (e) {
            var add = e.target.closest('[data-repeater-add-group]');
            if (add) { window.__activeBlocksWidget = add.closest('.field-blocks'); }
        });

        // Inject a "Paste block" entry at the top of an Add-Item palette grid when
        // the clipboard holds a block this widget accepts. The popover is rendered
        // at body level, so compatibility is checked against the active widget.
        function injectPalettePaste(grid) {
            var old = grid.querySelector('.blocks-paste-item');
            if (old) { old.remove(); }

            var cb = getClipboard();
            var fieldBlocks = window.__activeBlocksWidget;
            if (!cb || !cb.group || !blockTypeAvailable(fieldBlocks, cb.group)) { return; }

            var item = document.createElement('div');
            item.className = 'blocks-group-item blocks-paste-item';
            item.innerHTML =
                '<a href="javascript:;" data-block-paste-palette>' +
                '<i class="icon-paste"></i>' +
                '<div><span class="title">Paste block</span>' +
                '<span class="description">Insert the copied block</span></div></a>';
            grid.insertBefore(item, grid.firstChild);
        }

        // Click on the injected palette paste entry. Set the pending fields, then
        // fire the AJAX request on the real block-add link in the same popover grid
        // — using $(link).request() (not a synthetic DOM click, which doesn't
        // reliably trigger the handler). That link already carries the onAddItem
        // handler, the _repeater_group data, and the form context, so this reuses
        // core's proven add flow; the observer then fills the new block.
        document.addEventListener('click', function (e) {
            var a = e.target.closest('[data-block-paste-palette]');
            if (!a) { return; }
            e.preventDefault();
            e.stopPropagation();
            var cb = getClipboard();
            if (!cb || !cb.group || typeof $ === 'undefined') { return; }
            var grid = a.closest('.blocks-group-grid');
            var addLink = grid && grid.querySelector(
                'a[data-block-code="' + cb.group + '"][data-repeater-add]'
            );
            if (!addLink) { return; }
            window.__pendingPaste = { fields: cb.fields, afterLi: null };
            $(window).one('ajaxUpdateComplete', function () {
                cleanupAddItems(window.__activeBlocksWidget);
            });
            // Use the link's own handler + form context (it lives in the popover,
            // outside the form, so its data('request-form') is what makes it work).
            $(addLink).request(addLink.getAttribute('data-request'), {
                data: { _repeater_group: cb.group }
            });
        });

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
            requestAdd(li.closest('.field-blocks'), cb.group, cb.fields, li);
        });

        // --- collapsible sections ------------------------------------------
        function followingFields(section) {
            var els = [], el = section.nextElementSibling;
            while (el && !el.classList.contains('section-field')) {
                els.push(el);
                el = el.nextElementSibling;
            }
            return els;
        }

        function setCollapsed(section, collapsed) {
            section.classList.toggle('collapsed', collapsed);
            followingFields(section).forEach(function (el) {
                el.style.display = collapsed ? 'none' : '';
            });
        }

        function storageKey(section) {
            var name = section.getAttribute('data-field-name') || '';
            return name ? (COLLAPSE_PREFIX + name) : null;
        }

        function initSections() {
            document.querySelectorAll(
                '.section-field[data-block-collapsible]:not(.' + READY + ')'
            ).forEach(function (section) {
                section.classList.add(READY);
                var header = section.querySelector('.field-section');
                if (header) { header.classList.add('is-collapsible'); }

                // Restore persisted state if present, else fall back to config default.
                var key = storageKey(section);
                var stored = key ? lsGet(key) : null;
                var collapsed = stored !== null
                    ? stored === '1'
                    : !section.hasAttribute('data-block-collapsible-open');
                setCollapsed(section, collapsed);
            });
        }

        document.addEventListener('click', function (e) {
            var section = e.target.closest('.section-field[data-block-collapsible]');
            if (!section) { return; }
            var collapsed = !section.classList.contains('collapsed');
            setCollapsed(section, collapsed);
            var key = storageKey(section);
            if (key) { lsSet(key, collapsed ? '1' : '0'); }
        });

        // --- recently used blocks ------------------------------------------
        function getRecent() {
            try {
                var raw = lsGet(RECENT_KEY);
                var arr = raw ? JSON.parse(raw) : [];
                return Array.isArray(arr) ? arr : [];
            } catch (e) { return []; }
        }

        function pushRecent(code) {
            if (!code) { return; }
            var arr = getRecent().filter(function (c) { return c !== code; });
            arr.unshift(code);
            arr = arr.slice(0, RECENT_MAX);
            lsSet(RECENT_KEY, JSON.stringify(arr));
        }

        // Record a block as recently used whenever one is added from the palette.
        document.addEventListener('click', function (e) {
            var a = e.target.closest('[data-block-code]');
            if (!a) { return; }
            pushRecent(a.getAttribute('data-block-code'));
        });

        // When a palette grid appears, move recently used blocks to the front.
        // Reordering (rather than cloning) avoids duplicates and styling issues
        // and degrades gracefully if the markup differs.
        function applyRecent() {
            document.querySelectorAll('.blocks-group-grid:not([data-recent-processed])')
                .forEach(function (grid) {
                    grid.setAttribute('data-recent-processed', '1');
                    var recent = getRecent();
                    // Reverse so the most recent ends up first after successive prepends.
                    recent.slice().reverse().forEach(function (code) {
                        var link = grid.querySelector('a[data-block-code="' + code + '"]');
                        var item = link && link.closest('.blocks-group-item');
                        if (item && item.parentNode === grid) {
                            grid.insertBefore(item, grid.firstChild);
                        }
                    });
                });
        }

        // Add the "Paste block" entry to any open Add-Item palette grid. Runs after
        // applyRecent so it ends up first. Idempotent (removes any prior entry).
        function applyPalettePaste() {
            document.querySelectorAll('.blocks-group-grid').forEach(injectPalettePaste);
        }

        // --- shared init + observer ----------------------------------------
        function runAll() {
            initSections();
            applyRecent();
            applyPalettePaste();
            updatePasteButtons();
            // Self-heal any empty "add new item" rows left by direct add requests.
            document.querySelectorAll('.field-blocks').forEach(cleanupAddItems);
        }

        runAll();

        var scheduled = false;
        var observer = new MutationObserver(function (mutations) {
            // When a paste is pending, find the newly added block <li>, move it after
            // the source block, and fill its fields — all before the debounced runAll fires.
            if (window.__pendingPaste) {
                var pending = window.__pendingPaste;
                for (var i = 0; i < mutations.length; i++) {
                    mutations[i].addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && node.classList &&
                                node.classList.contains('field-block-item')) {
                            window.__pendingPaste = null;
                            // Move the new item to immediately after the source item.
                            if (pending.afterLi && pending.afterLi.parentNode) {
                                pending.afterLi.parentNode.insertBefore(
                                    node, pending.afterLi.nextSibling
                                );
                            }
                            fillFromClipboard(node, pending.fields);
                        }
                    });
                }
            }
            if (scheduled) { return; }
            scheduled = true;
            setTimeout(function () { scheduled = false; runAll(); }, 0);
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
