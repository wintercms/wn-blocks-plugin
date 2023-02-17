<?php

namespace Winter\Blocks\FormWidgets;

use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;
use Winter\Blocks\Classes\BlockManager;
use Cms\Classes\Controller;
use Winter\Storm\Support\Str;

/**
 * Block FormWidget for rendering blocks in a form
 */
class Block extends FormWidgetBase
{
    public const TYPE_PREFIX = 'block_';
    public const GROUP_BLOCKS = 'blocks';

    public function render()
    {
        return (new Controller())->renderPartial(
            Str::after($this->config->type, static::TYPE_PREFIX) . '.' . BlockManager::BLOCK_EXTENSION,
            ['data' => $this->formField->config['data'] ?? []]
        );
    }

    public function getSaveValue($value)
    {
        return FormField::NO_SAVE_DATA;
    }
}
