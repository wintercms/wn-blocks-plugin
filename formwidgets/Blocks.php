<?php

namespace Winter\Blocks\FormWidgets;

use Backend\FormWidgets\Repeater;
use Lang;
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
     * Defines a single tag, or list of tags to filter by. If `null`, all tags are allowed.
     */
    public string|array|null $tags = null;

    /**
     * Configuration stored with each index.
     */
    public array $indexConfigMeta = [];

    /**
     * Block data to seed into a freshly added item, keyed by index. Used by the
     * server-side paste flow so a pasted block renders fully populated (switches,
     * mediafinders, nested repeaters) instead of relying on lossy client-side
     * field scraping.
     */
    protected array $pasteData = [];

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->fillFromConfig([
            'ignore',
            'allow',
            'tags',
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
        // Collapsible-section behaviour is bootstrapped inline in the block
        // widget partial (formwidgets/blocks/partials/_block.php) so it loads
        // reliably regardless of asset-path resolution or the asset combiner.
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
     * @param array|mixed $value
     * @return array|mixed
     */
    protected function processSaveValue($value)
    {
        if (!is_array($value) || !$value) {
            return null;
        }

        $count = count($value);

        if ($this->minItems && $count < $this->minItems) {
            throw new ApplicationException(Lang::get('backend::lang.repeater.min_items_failed', [
                'name' => $this->fieldName,
                'min' => $this->minItems,
                'items' => $count,
            ]));
        }
        if ($this->maxItems && $count > $this->maxItems) {
            throw new ApplicationException(Lang::get('backend::lang.repeater.max_items_failed', [
                'name' => $this->fieldName,
                'max' => $this->maxItems,
                'items' => $count,
            ]));
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
     *
     * Returns the data at a given index, preferring data seeded for the
     * server-side paste flow (see onAddItem).
     */
    protected function getValueFromIndex($index)
    {
        if (array_key_exists($index, $this->pasteData)) {
            return $this->pasteData[$index];
        }

        return parent::getValueFromIndex($index);
    }

    /**
     * Returns the full saved data for a single block item, for the copy/cut/
     * duplicate clipboard. Building the item's Form widget and calling
     * getSaveData() captures every field type correctly — including switches,
     * mediafinders and nested repeaters — which a client-side DOM scrape cannot.
     */
    public function onCopyItem()
    {
        $index = post('_repeater_index');
        $groupCode = post('_repeater_group');

        $this->prepareVars();
        $widget = $this->makeItemFormWidget($index, $groupCode);

        // The Inspector config is repeater meta, not a form field, so read it
        // straight from the posted item value rather than getSaveData().
        $config = array_get(parent::getValueFromIndex($index), '_config') ?: null;

        return [
            'result' => json_encode([
                'group'  => $groupCode,
                'config' => $config,
                'data'   => $widget->getSaveData(),
            ]),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Accepts optional `_paste_data` (a JSON-encoded block data array from
     * onCopyItem) and `_paste_config` to render the new item pre-populated.
     */
    public function onAddItem()
    {
        $groupCode = post('_repeater_group');

        $index = $this->getNextIndex();

        $pasteConfig = null;
        $isPaste = false;
        if ($pasteRaw = post('_paste_data')) {
            $decoded = json_decode($pasteRaw, true);
            if (is_array($decoded)) {
                $this->pasteData[$index] = $decoded;
                $pasteConfig = post('_paste_config') ?: null;
                $isPaste = true;
            }
        }

        // Keep the static $onAddItemCalled flag set during prepareVars() so the
        // OUTER repeater skips re-processing every existing item (Repeater.php:179)
        // — we only render the new item here, so rebuilding the rest is wasted work.
        $this->prepareVars();

        // Repeater::$onAddItemCalled is a *static* flag set during init() of the
        // repeater handling this AJAX request. While set, every repeater —
        // including any nested repeater inside the new item — skips processItems()
        // (Repeater.php:158,179), which normally stops a new empty item pulling a
        // sibling's data. But when pasting we *want* the new item's nested
        // repeaters to build their rows from the seeded data, so clear the flag
        // only while building and rendering the new item, then restore it.
        $restoreAddItemFlag = self::$onAddItemCalled;
        if ($isPaste) {
            self::$onAddItemCalled = false;
        }

        try {
            $this->vars['widget'] = $this->makeItemFormWidget($index, $groupCode);
            $this->vars['indexValue'] = $index;
            $this->indexConfigMeta[$index] = $pasteConfig;

            $html = $this->makePartial('block_item') . $this->makePartial('block_add_item');
        } finally {
            self::$onAddItemCalled = $restoreAddItemFlag;
        }

        $itemContainer = '@#' . $this->getId('items');
        $addItemContainer = '#' . $this->getId('add-item');

        return [
            $addItemContainer => '',
            $itemContainer => $html
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
        foreach (BlockManager::instance()->getConfigs($this->tags) as $code => $config) {
            if (!empty($config['tags']) && !$this->isBlockAllowed($code, $config['tags'])) {
                continue;
            }

            $definitions[$code] = [
                'code'          => $code,
                'name'          => array_get($config, 'name'),
                'icon'          => array_get($config, 'icon', 'icon-square-o'),
                'description'   => array_get($config, 'description'),
                'fields'        => $this->normalizeBlockFields((array) array_get($config, 'fields', [])),
                'config'        => array_get($config, 'config', null),
            ];
        }

        // Sort the builder blocks by translated name label
        uasort($definitions, fn ($a, $b) => trans($a['name']) <=> trans($b['name']));

        $this->groupDefinitions = $definitions;
        $this->useGroups = true;
    }

    /**
     * Translates block YAML shorthands before the fields are handed to the form widget.
     *
     * Supported shorthands on `type: section` fields:
     *   collapsible: true          — makes the section click-to-collapse
     *   collapsed: true|false      — initial state (true = start collapsed, false = start open)
     *                                defaults to true when collapsible is set
     */
    protected function normalizeBlockFields(array $fields): array
    {
        foreach ($fields as &$field) {
            if (($field['type'] ?? '') !== 'section') {
                continue;
            }

            if (!array_key_exists('collapsible', $field)) {
                continue;
            }

            if ($field['collapsible']) {
                $startCollapsed = $field['collapsed'] ?? true;

                // Use our own data attribute (NOT data-field-collapsible) so the
                // core form widget's bindCollapsibleSections() never touches these
                // sections. Core re-runs that on every FormWidget init — including
                // when a nested repeater adds an item — which would re-collapse and
                // double-bind handlers on sections the user manually opened. We own
                // the behaviour entirely in collapsible.js instead.
                $field['containerAttributes'] = array_merge(
                    $field['containerAttributes'] ?? [],
                    ['data-block-collapsible' => 1]
                );

                if (!$startCollapsed) {
                    $field['containerAttributes']['data-block-collapsible-open'] = 1;
                }
            }

            unset($field['collapsible'], $field['collapsed']);
        }

        return $fields;
    }

    /**
     * Determines if a block is allowed according to the widget's ignore/allow list.
     */
    protected function isBlockAllowed(string $code, array|string $blockTags): bool
    {
        $blockTags = is_array($blockTags) ? $blockTags : [$blockTags];

        if (isset($this->ignore['blocks']) || isset($this->ignore['tags'])) {
            $ignoredBlocks = isset($this->ignore['blocks']) ? $this->ignore['blocks'] : [];
            $ignoredTags = isset($this->ignore['tags']) ? $this->ignore['tags'] : [];
        } else {
            $ignoredBlocks = $this->ignore;
            $ignoredTags = [];
        }
        if (isset($this->allow['blocks']) || isset($this->allow['tags'])) {
            $allowedBlocks = isset($this->allow['blocks']) ? $this->allow['blocks'] : [];
            $allowedTags = isset($this->allow['tags']) ? $this->allow['tags'] : [];
        } else {
            $allowedBlocks = $this->allow;
            $allowedTags = [];
        }

        // Reject explicitly ignored blocks
        if (count($ignoredBlocks) && in_array($code, $ignoredBlocks)) {
            return false;
        }

        // Reject blocks that have any ignored tags
        if (count($ignoredTags) && array_intersect($blockTags, $ignoredTags)) {
            return false;
        }

        // Reject blocks that are not explicitly allowed
        if (count($allowedBlocks) && !in_array($code, $allowedBlocks)) {
            return false;
        }

        // Reject blocks that do not have any allowed tags
        if (count($allowedTags) && !array_intersect($blockTags, $allowedTags)) {
            return false;
        }

        return true;
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
    public function getGroupDescription(string $groupCode): ?string
    {
        return array_get($this->groupDefinitions, $groupCode . '.description');
    }

    /**
     * Returns the group icon from its unique code.
     */
    public function getGroupIcon(string $groupCode): ?string
    {
        return array_get($this->groupDefinitions, $groupCode . '.icon');
    }

    /**
     * Determines if the given block has an Inspector config.
     */
    public function hasInspectorConfig(string $groupCode): bool
    {
        return isset($this->groupDefinitions[$groupCode]['config']);
    }

    /**
     * Returns the Inspector config, as a JSON string, for the given group code.
     */
    public function getInspectorConfig(string $groupCode): string
    {
        return json_encode($this->processInspectorConfig(array_get($this->groupDefinitions, $groupCode . '.config', [])));
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
                'title' => Lang::get(array_get($schema, 'title', array_get($schema, 'label'))),
                'description' => Lang::get(array_get($schema, 'description', array_get($schema, 'comment', array_get($schema, 'commentAbove')))),
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

            if (isset($defined['options']) && is_array($defined['options'])) {
                foreach ($defined['options'] as $key => &$value) {
                    $value = Lang::get($value);
                }
            }

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
            case 'radio':
                return 'dropdown';
        }

        return $type;
    }
}
