<div class="form-buttons loading-indicator-container">
    <a
        href="javascript:;"
        class="btn btn-primary wn-icon-check save"
        data-request="onSave"
        data-load-indicator="<?= e(trans('backend::lang.form.saving')) ?>"
        data-hotkey="ctrl+s, cmd+s">
        <?= e(trans('backend::lang.form.save')) ?>
    </a>

    <?= $this->makePartial('common_toolbar_actions', ['toolbarSource' => 'block']); ?>
</div>