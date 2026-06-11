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
    <li class="field-block-paste-row" data-block-paste-append-row style="display:none">
        <a href="javascript:;" data-block-paste-append>
            <i class="icon-paste"></i> Paste block
        </a>
    </li>
<?php endif ?>
