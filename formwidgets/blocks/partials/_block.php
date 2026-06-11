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

        // Copy button
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-copy]');
            if (!btn) { return; }
            e.stopPropagation();
            var li = btn.closest('.field-block-item');
            if (!li) { return; }
            ssSet(CLIPBOARD_KEY, JSON.stringify(serializeBlockItem(li)));
        });

        // Cut button: copy then trigger the existing remove button (with confirm).
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-block-cut]');
            if (!btn) { return; }
            e.stopPropagation();
            var li = btn.closest('.field-block-item');
            if (!li) { return; }
            ssSet(CLIPBOARD_KEY, JSON.stringify(serializeBlockItem(li)));
            var removeBtn = li.querySelector('[data-repeater-remove]');
            if (removeBtn) { removeBtn.click(); }
        });

        // Paste button (injected into the palette by applyPasteItem below).
        // Sets a pending-paste flag, then programmatically clicks the matching block's
        // add-link so onAddItem runs normally. The MutationObserver detects the new <li>
        // and fills its fields from the clipboard.
        document.addEventListener('click', function (e) {
            var a = e.target.closest('[data-block-paste]');
            if (!a) { return; }
            e.preventDefault();
            e.stopPropagation();
            var cb = getClipboard();
            if (!cb || !cb.group) { return; }
            window.__pendingPaste = cb.fields;
            var grid = a.closest('.blocks-group-grid');
            var addLink = grid && grid.querySelector(
                'a[data-block-code="' + cb.group + '"][data-repeater-add]'
            );
            if (addLink) { addLink.click(); }
        });

        // Inject a "Paste block" item at the top of the palette when clipboard has data
        // and the block type is available in this widget's palette.
        function applyPasteItem() {
            document.querySelectorAll('.blocks-group-grid').forEach(function (grid) {
                var existing = grid.querySelector('[data-block-paste]');
                if (existing) { existing.closest('.blocks-group-item').remove(); }

                var cb = getClipboard();
                if (!cb || !cb.group) { return; }

                var matchLink = grid.querySelector(
                    'a[data-block-code="' + cb.group + '"][data-repeater-add]'
                );
                if (!matchLink) { return; }

                var item = document.createElement('div');
                item.className = 'blocks-group-item';
                item.innerHTML =
                    '<a href="javascript:;" data-block-paste data-block-code="' + cb.group + '">' +
                    '<i class="icon-paste"></i>' +
                    '<div>' +
                    '<span class="title">Paste block</span>' +
                    '<span class="description">Paste copied “' + cb.group + '” block</span>' +
                    '</div></a>';
                grid.insertBefore(item, grid.firstChild);
            });
        }

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
        function runAll() { initSections(); applyRecent(); applyPasteItem(); }

        runAll();

        var scheduled = false;
        var observer = new MutationObserver(function (mutations) {
            // Fill clipboard fields into a newly added block item immediately,
            // before the debounced runAll() fires, so inputs are ready.
            if (window.__pendingPaste) {
                for (var i = 0; i < mutations.length; i++) {
                    mutations[i].addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && node.classList &&
                                node.classList.contains('field-block-item')) {
                            fillFromClipboard(node, window.__pendingPaste);
                            window.__pendingPaste = null;
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
