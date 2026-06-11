<?php if (!$this->previewMode): ?>
    <li
        id="<?= $this->getId('add-item') ?>"
        class="field-repeater-add-item loading-indicator-container indicator-center"
        <?php if ($mode === 'grid'): ?>style="min-height: <?= $rowHeight ?>px"<?php endif ?>
    >
        <a
            href="javascript:;"
            class="wn-icon-plus"
            data-repeater-add-group
            data-load-indicator>
            <?= e(trans($prompt)) ?>
        </a>
    </li>
<?php endif ?>
