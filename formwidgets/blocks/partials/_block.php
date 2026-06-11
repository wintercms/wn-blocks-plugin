<div class="field-blocks"
    data-control="fieldblocks"
    <?= $titleFrom ? 'data-title-from="'.$titleFrom.'"' : '' ?>
    <?= $minItems ? 'data-min-items="'.$minItems.'"' : '' ?>
    <?= $maxItems ? 'data-max-items="'.$maxItems.'"' : '' ?>
    <?= $style ? 'data-style="'.$style.'"' : '' ?>
    data-mode="<?= $mode ?>"
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
        // Kept here (rather than only in blocks.less) so the per-block copy/cut/
        // paste/remove toolbar renders correctly even if the compiled CSS is stale.
        (function injectToolbarCss() {
            if (document.getElementById('wn-blocks-toolbar-css')) { return; }
            var css =
                '.block-item-toolbar{display:inline-flex;align-items:center;gap:1px}' +
                '.block-item-action{float:none;display:inline-flex;align-items:center;' +
                'justify-content:center;width:22px;height:22px;padding:0;margin:0;border:0;' +
                'background:none;cursor:pointer;color:#95a5a6;opacity:.7;font-size:13px;' +
                'line-height:1;border-radius:3px;transition:background .15s,color .15s,opacity .15s}' +
                '.block-item-action:hover{opacity:1;background:rgba(0,0,0,.06);color:#34495e}' +
                '.block-item-action-remove:hover{color:#cc3300}' +
                '.field-block-paste-row{list-style:none;text-align:center;padding:6px 0}' +
                '.field-block-paste-row a{font-size:13px;color:#0072bc;text-decoration:none}' +
                '.field-block-paste-row a:hover{text-decoration:underline}';
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

        // Is the given block code offered by this widget's palette?
        // Returns true if we can't tell (fail open) so a paste option is never
        // hidden by a markup quirk; the paste action itself no-ops on a bad type.
        function blockTypeAvailable(fieldBlocks, group) {
            var tmpl = fieldBlocks && fieldBlocks.querySelector('[data-group-palette-template]');
            if (!tmpl) { return true; }
            var text = tmpl.textContent || tmpl.innerHTML || '';
            if (text.indexOf('data-block-code=') === -1) { return true; }
            return text.indexOf('data-block-code="' + group + '"') !== -1;
        }

        // Show/hide paste affordances based on clipboard state and widget availability.
        // Per-item paste buttons toggle themselves; the append link toggles its own
        // <li> row (so it sits on its own line under the Add button, never clipped).
        function setPasteVisible(el, visible) {
            if (el) { el.style.display = visible ? '' : 'none'; }
        }
        function updatePasteButtons() {
            var cb = getClipboard();
            var ok = cb && cb.group;

            document.querySelectorAll('[data-block-paste]').forEach(function (btn) {
                var fieldBlocks = btn.closest('.field-blocks');
                setPasteVisible(btn, ok && blockTypeAvailable(fieldBlocks, cb.group));
            });

            document.querySelectorAll('[data-block-paste-append]').forEach(function (link) {
                var fieldBlocks = link.closest('.field-blocks');
                var row = link.closest('[data-block-paste-append-row]') || link;
                setPasteVisible(row, ok && blockTypeAvailable(fieldBlocks, cb.group));
            });
        }

        // Find the onAddItem AJAX handler name for a given group code by reading the
        // palette template embedded in the same .field-blocks widget.
        function findAddHandler(fieldBlocks, groupCode) {
            var tmpl = fieldBlocks && fieldBlocks.querySelector('[data-group-palette-template]');
            if (!tmpl) { return null; }
            // Parse the template text to extract data-request from the matching link.
            var div = document.createElement('div');
            div.innerHTML = tmpl.textContent || tmpl.innerHTML;
            var link = div.querySelector('a[data-block-code="' + groupCode + '"]');
            return link ? link.getAttribute('data-request') : null;
        }

        // Copy button
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-copy]');
            if (!btn) { return; }
            e.stopPropagation();
            var li = btn.closest('.field-block-item');
            if (!li) { return; }
            ssSet(CLIPBOARD_KEY, JSON.stringify(serializeBlockItem(li)));
            updatePasteButtons();
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

        // Paste-append button in the add-item row — appends the copied block at the end.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-paste-append]');
            if (!btn) { return; }
            e.preventDefault();
            e.stopPropagation();
            var cb = getClipboard();
            if (!cb || !cb.group) { return; }
            var fieldBlocks = btn.closest('.field-blocks');
            var handler = findAddHandler(fieldBlocks, cb.group);
            if (!handler) { return; }
            // No afterLi — the MutationObserver will fill the block where onAddItem appends it.
            window.__pendingPaste = { fields: cb.fields, afterLi: null };
            if (typeof $ !== 'undefined') {
                $(fieldBlocks).request(handler, { data: { _repeater_group: cb.group } });
            }
        });

        // Paste button on each block item — inserts the copied block immediately after.
        // Reads the AJAX handler name from the palette template (no popover needed),
        // fires onAddItem directly, then the MutationObserver moves the new <li> into place.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-paste]');
            if (!btn || !btn.closest('.field-block-item')) { return; }
            e.preventDefault();
            e.stopPropagation();
            var cb = getClipboard();
            if (!cb || !cb.group) { return; }
            var li = btn.closest('.field-block-item');
            var fieldBlocks = li.closest('.field-blocks');
            var handler = findAddHandler(fieldBlocks, cb.group);
            if (!handler) { return; }
            window.__pendingPaste = { fields: cb.fields, afterLi: li };
            if (typeof $ !== 'undefined') {
                $(fieldBlocks).request(handler, { data: { _repeater_group: cb.group } });
            }
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

        // --- shared init + observer ----------------------------------------
        function runAll() { initSections(); applyRecent(); updatePasteButtons(); }

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
