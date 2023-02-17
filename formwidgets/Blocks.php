<?php

namespace Winter\Blocks\FormWidgets;

use Backend\FormWidgets\Repeater;
use Winter\Blocks\Classes\BlockManager;

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
     * @inheritDoc
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
     * This method overrides the base repeater processGroupMode to implement block functionality without pre-defining a
     * group.
     */
    protected function processGroupMode(): void
    {
        $definitions = [];
        foreach (BlockManager::instance()->getConfigs($this->config->blockContext ?? null) as $code => $config) {
            if (in_array($code, $this->ignore)) {
                continue;
            }

            if (!empty($this->allow) && !in_array($code, $this->allow)) {
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
}
