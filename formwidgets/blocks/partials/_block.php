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
     * Inline bootstrap for collapsible block sections.
     *
     * Loaded inline (rather than only via addJs) so it is guaranteed to reach the
     * page regardless of widget asset-path resolution or the asset combiner. A
     * global guard ensures the handlers are registered only once even when several
     * block widgets render on the same page.
     *
     * Behaviour lives on the data-block-collapsible attribute, which the core form
     * widget's bindCollapsibleSections() never selects — so core cannot re-collapse
     * or double-bind these sections when a nested repeater adds an item.
     */
    ?>
    <script>
    (function () {
        if (window.__blockCollapsibleInit) { return; }
        window.__blockCollapsibleInit = true;

        var READY = 'block-collapsible-ready';

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

        function initSections() {
            document.querySelectorAll(
                '.section-field[data-block-collapsible]:not(.' + READY + ')'
            ).forEach(function (section) {
                section.classList.add(READY);
                var header = section.querySelector('.field-section');
                if (header) { header.classList.add('is-collapsible'); }
                var startOpen = section.hasAttribute('data-block-collapsible-open');
                setCollapsed(section, !startOpen);
            });
        }

        document.addEventListener('click', function (e) {
            var section = e.target.closest('.section-field[data-block-collapsible]');
            if (!section) { return; }
            setCollapsed(section, !section.classList.contains('collapsed'));
        });

        initSections();

        var scheduled = false;
        var observer = new MutationObserver(function () {
            if (scheduled) { return; }
            scheduled = true;
            setTimeout(function () { scheduled = false; initSections(); }, 0);
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
