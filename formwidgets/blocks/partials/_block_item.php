<?php
$groupCode = $this->getGroupCodeFromIndex($indexValue);
$itemTitle = $this->getGroupTitle($groupCode);
$itemIcon = $this->getGroupIcon($groupCode);
?>
<li
    class="field-repeater-item<?php if (!count($widget->getFields())): ?> empty<?php endif ?>">

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

    <?php if (count($widget->getFields())): ?>
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
