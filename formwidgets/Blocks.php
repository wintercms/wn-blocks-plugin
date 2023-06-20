<?php

namespace Winter\Blocks\FormWidgets;

use Lang;
use Backend\FormWidgets\Repeater;
use Winter\Blocks\Classes\BlockManager;
use Winter\Storm\Exception\ApplicationException;

/**
 * "Blocks" FormWidget for defining and managing multiple blocks
 */
class Blocks extends Repeater
{
    /**
     * List of blocks to ignore for this specific instance
     */
    public array $ignore = [];

    /**
     * List of blocks to explicitly allow for this specific instance
     */
    public array $allow = [];

    /**
     * List of inspector configs for the blocks.
     */
    public array $inspectorConfigs = [];

    /**
     * Configuration stored with each index.
     */
    public array $indexConfigMeta = [];

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->fillFromConfig([
            'ignore',
            'allow',
        ]);

        parent::init();
    }

    /**
     * {@inheritDoc}
     */
    protected function loadAssets()
    {
        $this->addCss('css/blocks.css', 'Winter.Blocks');
        $this->addJs('js/blocks.js', 'Winter.Blocks');
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('block');
    }

    /**
     * Splices in some meta data (group and index values) to the dataset.
     * @param array $value
     * @return array
     */
    protected function processSaveValue($value)
    {
        if (!is_array($value) || !$value) {
            return $value;
        }

        if ($this->minItems && count($value) < $this->minItems) {
            throw new ApplicationException(Lang::get('backend::lang.repeater.min_items_failed', ['name' => $this->fieldName, 'min' => $this->minItems, 'items' => count($value)]));
        }
        if ($this->maxItems && count($value) > $this->maxItems) {
            throw new ApplicationException(Lang::get('backend::lang.repeater.max_items_failed', ['name' => $this->fieldName, 'max' => $this->maxItems, 'items' => count($value)]));
        }

        /*
         * Give repeated form field widgets an opportunity to process the data.
         */
        foreach ($value as $index => $data) {
            if (isset($this->formWidgets[$index])) {
                $value[$index] = array_merge($this->formWidgets[$index]->getSaveData(), [
                    '_group' => $data['_group'],
                    '_config' => (!empty($data['_config'])) ? $data['_config'] : null,
                ]);
            }
        }

        return array_values($value);
    }

    /**
     * {@inheritDoc}
     */
    protected function processItems()
    {
        $currentValue = ($this->loaded === true)
            ? post($this->formField->getName())
            : $this->getLoadValue();

        // Detect when a child widget is trying to run an AJAX handler
        // outside of the form element that contains all the repeater
        // fields that would normally be used to identify that case
        $handler = $this->controller->getAjaxHandler();
        if (!$this->loaded && starts_with($handler, $this->alias . 'Form')) {
            // Attempt to get the index of the repeater
            $handler = str_after($handler, $this->alias . 'Form');
            preg_match("~^(\d+)~", $handler, $matches);

            if (isset($matches[1])) {
                $index = $matches[1];
                $this->makeItemFormWidget($index);
            }
        }

        // Ensure that the minimum number of items are preinitialized
        // ONLY DONE WHEN NOT IN GROUP MODE
        if (!$this->useGroups && $this->minItems > 0) {
            if (!is_array($currentValue)) {
                $currentValue = [];
                for ($i = 0; $i < $this->minItems; $i++) {
                    $currentValue[$i] = [];
                }
            } elseif (count($currentValue) < $this->minItems) {
                for ($i = 0; $i < ($this->minItems - count($currentValue)); $i++) {
                    $currentValue[] = [];
                }
            }
        }

        if (!$this->childAddItemCalled && $currentValue === null) {
            $this->formWidgets = [];
            return;
        }

        if ($this->childAddItemCalled && !isset($currentValue[$this->childIndexCalled])) {
            // If no value is available but a child repeater has added an item, add a "stub" repeater item
            $this->makeItemFormWidget($this->childIndexCalled);
        }

        if (!is_array($currentValue)) {
            return;
        }

        collect($currentValue)->each(function ($value, $index) {
            $this->makeItemFormWidget($index, array_get($value, '_group', null));
            $this->indexConfigMeta[$index] = array_get($value, '_config', null);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function onAddItem()
    {
        $groupCode = post('_repeater_group');

        $index = $this->getNextIndex();

        $this->prepareVars();
        $this->vars['widget'] = $this->makeItemFormWidget($index, $groupCode);
        $this->vars['indexValue'] = $index;

        $itemContainer = '@#' . $this->getId('items');
        $addItemContainer = '#' . $this->getId('add-item');

        return [
            $addItemContainer => '',
            $itemContainer => $this->makePartial('block_item') . $this->makePartial('block_add_item')
        ];
    }

    /**
     * {@inheritDoc}
     *
     * This method overrides the base repeater processGroupMode to implement block functionality without pre-defining a
     * group.
     */
    protected function processGroupMode(): void
    {
        $definitions = [];
        foreach (BlockManager::instance()->getConfigs($this->config->blockContext ?? null) as $code => $config) {
            if (
                in_array($code, $this->ignore)
                || count(array_intersect($config['context'], $this->ignore))
            ) {
                continue;
            }

            if (
                !empty($this->allow)
                && !in_array($code, $this->allow)
                && !count(array_intersect($config['context'], $this->allow))
            ) {
                continue;
            }

            $config = $this->handleFieldContext($config, $this->config->blockContext ?? null);

            $definitions[$code] = [
                'code' => $code,
                'name' => array_get($config, 'name'),
                'icon' => array_get($config, 'icon', 'icon-square-o'),
                'description' => array_get($config, 'description'),
                'fields' => array_get($config, 'fields')
            ];

            $this->inspectorConfigs[$code] = array_get($config, 'config', null);
        }

        // Sort the builder blocks by translated name label
        uasort($definitions, fn ($a, $b) => trans($a['name']) <=> trans($b['name']));

        $this->groupDefinitions = $definitions;
        $this->useGroups = true;
    }

    /**
     * Recursively iterates through fields and applies context filtering, may not handle all form types
     */
    protected function handleFieldContext(array $config, ?string $context): array
    {
        if (!$context) {
            return $config;
        }

        $target = null;

        if (isset($config['fields'])) {
            $target = &$config['fields'];
        }

        if (isset($config['tabs']['fields'])) {
            $target = &$config['tabs']['fields'];
        }

        if (!$target) {
            return $config;
        }

        foreach ($target as $key => $field) {
            if (isset($field['blockContext']) && !in_array($context, $field['blockContext'])) {
                unset($target[$key]);
                continue;
            }

            if (isset($field['form'])) {
                $target[$key]['form'] = $this->handleFieldContext($field['form'], $context);
            }
        }

        return $config;
    }

    /**
     * Gets the configuration of a block.
     */
    public function getGroupConfigFromIndex(int $index)
    {
        return $this->indexConfigMeta[$index] ?? null;
    }

    /**
     * Returns the group description from its unique code.
     */
    public function getGroupDescription(string $groupCode): string
    {
        return array_get($this->groupDefinitions, $groupCode.'.description');
    }

    /**
     * Returns the group icon from its unique code.
     */
    public function getGroupIcon(string $groupCode): string
    {
        return array_get($this->groupDefinitions, $groupCode.'.icon');
    }

    /**
     * Determines if the given block has an Inspector config.
     */
    public function hasInspectorConfig(string $groupCode): bool
    {
        return isset($this->inspectorConfigs[$groupCode]);
    }

    /**
     * Returns the Inspector config, as a JSON string, for the given group code.
     */
    public function getInspectorConfig(string $groupCode): string
    {
        return json_encode($this->processInspectorConfig(array_get($this->inspectorConfigs, $groupCode, [])));
    }

    /**
     * Converts a Form widget configuration into an Inspector configuration.
     */
    protected function processInspectorConfig(array $config): array
    {
        $properties = [];

        foreach ($config as $property => $schema) {
            $defined = [
                'property' => $property,
                'title' => array_get($schema, 'title', array_get($schema, 'label')),
                'description' => array_get($schema, 'description', array_get($schema, 'comment', array_get($schema, 'commentAbove'))),
                'type' => $this->getBestInspectorField(array_get($schema, 'type', 'string')),
                'group' => array_get($schema, 'group', array_get($schema, 'tab')),
            ];

            $defined = array_merge($defined, array_except($schema, [
                'title',
                'label',
                'description',
                'comment',
                'commentAbove',
                'type',
                'group',
                'span',
            ]));

            $properties[] = array_filter($defined);
        }

        return $properties;
    }

    /**
     * Converts a Form widget field type into the best Inspector field type.
     *
     * If it cannot convert the type, it is returned as-is.
     */
    protected function getBestInspectorField(string $type): string
    {
        switch ($type) {
            case 'text':
                return 'string';
            case 'textarea':
                return 'text';
            case 'checkboxlist':
                return 'set';
            case 'balloon-selector':
                return 'dropdown';
        }

        return $type;
    }
}
