<?php
$groupCode = $this->getGroupCodeFromIndex($indexValue);
$groupConfig = $this->getGroupConfigFromIndex($indexValue);
$itemTitle = $this->getGroupTitle($groupCode);
$itemDescription = $this->getGroupDescription($groupCode);
$itemIcon = $this->getGroupIcon($groupCode);
?>
<li
    class="field-repeater-item field-block-item<?php if (!count($widget->getFields())): ?> empty<?php endif ?>"
    <?php if ($mode === 'grid'): ?>style="min-height: <?= $rowHeight ?>px"<?php endif ?>
>

    <?php
    /*
     * All item controls live in one horizontal toolbar so they never overflow.
     * Order: collapse, copy, cut, paste, duplicate, [config], delete.
     */
    ?>
    <div class="repeater-item-remove block-item-toolbar">
        <?php if (count($widget->getFields()) && $mode !== 'grid'): ?>
            <a href="javascript:;" class="repeater-item-collapse-one block-item-action" aria-label="Collapse" title="Collapse">
                <i class="icon-chevron-up"></i>
            </a>
        <?php endif ?>

        <?php if (!$this->previewMode): ?>
            <button type="button" class="block-item-action" aria-label="Copy block" title="Copy" data-block-copy>
                <i class="icon-copy"></i>
            </button>
            <button type="button" class="block-item-action" aria-label="Cut block" title="Cut" data-block-cut>
                <i class="icon-scissors"></i>
            </button>
            <button type="button" class="block-item-action" aria-label="Paste after this block" title="Paste after" data-block-paste style="display:none">
                <i class="icon-paste"></i>
            </button>
            <button type="button" class="block-item-action" aria-label="Duplicate block" title="Duplicate" data-block-duplicate>
                <i class="icon-clone"></i>
            </button>
        <?php endif ?>

        <?php if ($this->hasInspectorConfig($groupCode)): ?>
            <a
                href="javascript:;"
                class="block-config block-item-action"
                data-inspectable
                data-inspector-title="<?= e(trans($itemTitle)) ?>"
                data-inspector-description="<?= e(trans($itemDescription)) ?>"
                data-inspector-config="<?= e($this->getInspectorConfig($groupCode)) ?>"
                data-inspector-offset-y="-5"
                data-inspector-offset-x="-15"
            >
                <i class="icon-cog"></i>
                <input type="hidden" data-inspector-values name="<?= $widget->arrayName ?>[_config]" value="<?= e($groupConfig ?? '') ?>" />
            </a>
        <?php endif ?>

        <?php if (!$this->previewMode): ?>
            <button
                type="button"
                class="block-item-action block-item-action-remove"
                aria-label="Remove"
                data-repeater-remove
                data-request="<?= $this->getEventHandler('onRemoveItem') ?>"
                data-request-data="'_repeater_index': '<?= $indexValue ?>', '_repeater_group': '<?= $groupCode ?>'"
                data-request-confirm="<?= e(trans('backend::lang.form.action_confirm')) ?>">
                <i class="icon-times"></i>
            </button>
        <?php endif ?>
    </div>

    <div class="repeater-item-collapsed-handle">&nbsp;</div>

    <div class="repeater-item-title<?php if (!$this->previewMode && $sortable): ?> repeater-item-handle <?= $this->getId('items') ?>-handle"<?php endif ?>>
        <span class="icon">
            <i class="<?= $itemIcon ?>"></i>
        </span>
        <span class="name"><?= e(trans($itemTitle)) ?></span>

        <input type="hidden" name="<?= $widget->arrayName ?>[_group]" value="<?= $groupCode ?>" />
    </div>

    <div class="field-repeater-form"
         data-control="formwidget"
         data-refresh-handler="<?= $this->getEventHandler('onRefresh') ?>"
         data-refresh-data="'_repeater_index': '<?= $indexValue ?>', '_repeater_group': '<?= $groupCode ?>'">
        <?php foreach ($widget->getFields() as $field) : ?>
            <?= $widget->renderField($field) ?>
        <?php endforeach ?>

        <input type="hidden" name="<?= $widget->arrayName ?>[_group]" value="<?= $groupCode ?>" />
    </div>

</li>
