<?php
    $visibleCount = 0;
?>
<div class="layout control-scrollpanel" id="cms-side-panel">
    <div class="layout-cell">
        <div class="layout-relative fix-button-container">
            <?php if ($this->user->hasAccess('winter.blocks.manage_blocks')): ?>
                <!-- Partials -->
                <form
                    role="form"
                    class="layout <?= ++$visibleCount == 1 ? '' : 'hide' ?>"
                    data-content-id="blocks"
                    data-template-type="block"
                    data-type-icon="wn-icon-tags"
                    onsubmit="return false">
                    <?= $this->widget->blockList->render() ?>
                </form>
            <?php endif ?>
        </div>
    </div>
</div>
