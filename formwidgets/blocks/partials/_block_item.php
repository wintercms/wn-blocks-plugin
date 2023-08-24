<?php
$groupCode = $this->getGroupCodeFromIndex($indexValue);
$groupConfig = $this->getGroupConfigFromIndex($indexValue);
$itemTitle = $this->getGroupTitle($groupCode);
$itemDescription = $this->getGroupDescription($groupCode);
$itemIcon = $this->getGroupIcon($groupCode);
?>
<li
    class="field-repeater-item<?php if (!count($widget->getFields())): ?> empty<?php endif ?>"
    <?php if ($mode === 'grid'): ?>style="min-height: <?= $rowHeight ?>px"<?php endif ?>
>

    <?php if (!$this->previewMode) : ?>
        <div class="repeater-item-remove">
            <button
                type="button"
                class="close"
                aria-label="Remove"
                data-repeater-remove
                data-request="<?= $this->getEventHandler('onRemoveItem') ?>"
                data-request-data="'_repeater_index': '<?= $indexValue ?>', '_repeater_group': '<?= $groupCode ?>'"
                data-request-confirm="<?= e(trans('backend::lang.form.action_confirm')) ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif ?>

    <?php if (count($widget->getFields()) && $mode !== 'grid'): ?>
        <div class="repeater-item-collapse">
            <a href="javascript:;" class="repeater-item-collapse-one">
                <i class="icon-chevron-up"></i>
            </a>
        </div>
    <?php endif ?>

    <div class="repeater-item-title<?php if (!$this->previewMode && $sortable): ?> repeater-item-handle <?= $this->getId('items') ?>-handle"<?php endif ?>>
        <span class="icon">
            <i class="<?= $itemIcon ?>"></i>
        </span>
        <span class="name"><?= e(trans($itemTitle)) ?></span>

        <input type="hidden" name="<?= $widget->arrayName ?>[_group]" value="<?= $groupCode ?>" />
    </div>

    <?php if ($this->hasInspectorConfig($groupCode)): ?>
        <a
            href="javascript:;"
            class="block-config"
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
