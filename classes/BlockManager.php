<?php

namespace Winter\Blocks\Classes;

use Cms\Classes\CmsObjectCollection;
use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Event;
use File;
use Log;
use System\Classes\PluginManager;
use Winter\Storm\Support\Traits\Singleton;
use Winter\Storm\Support\Str;
use Winter\Storm\Exception\SystemException;
use Winter\Storm\Filesystem\PathResolver;

/**
 * Manages the available Blocks that can be used in the application
 */
class BlockManager
{
    use Singleton;

    /**
     * @todo Replace with Block::$allowedExtensions
     */
    const BLOCK_EXTENSION = 'block';

    /**
     * @var array Local cache of registered blocks
     */
    protected $blocks = [];

    public function init(): void
    {
        // @TODO: Find a better way to handle rendering blocks that doesn't require a "blocks" partial in the theme
        // or require hooking into the CMS beforeRenderPartial event
        Event::listen('cms.page.beforeRenderPartial', function (Controller $controller, string $partialName) {
            if (Str::endsWith($partialName, '.' . static::BLOCK_EXTENSION)) {
                if ($block = Block::loadCached(Theme::getActiveTheme(), $partialName)) {
                    // Execute the block lifecycle events and return the block object
                    return $block->executeLifecycle($controller);
                } else {
                    throw new SystemException("The block '$partialName' can not found.");
                }
            }
        });

        foreach (PluginManager::instance()->getRegistrationMethodValues('registerBlocks') as $plugin => $blocks) {
            foreach ($blocks as $key => $path) {
                $this->registerBlock($key, $path);
            }
        }
    }

    /**
     * Get the list of registered blocks in the form of ['key' => '$/path/to/block.block']
     */
    public function getRegisteredBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Register the provided key & path as a block
     */
    public function registerBlock(string $key, string $path): void
    {
        $realPath = File::symbolizePath($path);

        if (!File::exists($realPath)) {
            return;
        }

        $this->blocks[$key] = PathResolver::standardize($realPath);
    }

    /**
     * Get a collection of Block instances using the active theme
     */
    public function getBlocks(): CmsObjectCollection
    {
        return Block::listInTheme(Theme::getActiveTheme());
    }

    /**
     * Get an array of blocks and their configuration details in the form of ['key' => $config]
     */
    public function getConfigs(string|array|null $tags = null): array
    {
        $configs = [];
        foreach ($this->getBlocks() as $block) {
            if (isset($tags)) {
                $tags = (is_array($tags)) ? $tags : [$tags];
                $blockTags = (isset($block->tags) && is_array($block->tags)) ? $block->tags : [];

                if (count(array_intersect($tags, $blockTags)) === 0) {
                    continue;
                }
            }

            $config = array_except(
                $block->getAttributes(),
                [
                    'fileName',
                    'content',
                    'mtime',
                    'markup',
                    'code',
                ]
            );

            $config = $this->resolveComponent($config);

            $configs[pathinfo($block['fileName'])['filename']] = $config;
        }

        return $configs;
    }

    /**
     * Resolves a `component:` key in a block config by merging the component's
     * `defineProperties()` into the block's `fields:` as base definitions.
     *
     * A block may declare:
     *
     *     component: mfaGateway
     *
     * The component's `defineProperties()` entries are converted to block field
     * definitions and merged as defaults; the block's own `fields:` always win.
     *
     * Property-to-field mapping:
     *   title       → label
     *   description → comment
     *   type        → type (string → text, dropdown → dropdown, checkbox → checkbox)
     *   default     → default
     */
    protected function resolveComponent(array $config): array
    {
        if (empty($config['component']) || !is_string($config['component'])) {
            unset($config['component']);
            return $config;
        }

        $componentName = $config['component'];
        unset($config['component']);

        try {
            $class = \Cms\Classes\ComponentManager::instance()->resolve($componentName);
            if (!$class) {
                Log::warning("Winter.Blocks: component '{$componentName}' not found for block.");
                return $config;
            }

            /** @var \Cms\Classes\ComponentBase $instance */
            $instance = new $class();
            $properties = method_exists($instance, 'defineProperties') ? $instance->defineProperties() : [];

            // Map component type names to block/form field type names.
            $typeMap = [
                'string'   => 'text',
                'text'     => 'text',
                'integer'  => 'number',
                'float'    => 'number',
                'checkbox' => 'checkbox',
                'dropdown' => 'dropdown',
                'set'      => 'checkboxlist',
            ];

            $fromComponent = [];
            foreach ($properties as $name => $def) {
                $propType  = $def['type'] ?? 'string';
                $fieldType = $typeMap[$propType] ?? 'text';
                $field = [
                    'label'   => $def['title']       ?? $name,
                    'type'    => $fieldType,
                ];
                if (isset($def['default']))     $field['default'] = $def['default'];
                if (!empty($def['description'])) $field['comment'] = $def['description'];
                if (!empty($def['placeholder'])) $field['placeholder'] = $def['placeholder'];
                if (!empty($def['options']))     $field['options'] = $def['options'];

                $fromComponent[$name] = $field;
            }

            // Block's own fields win; component fields fill in the rest.
            $ownFields = (isset($config['fields']) && is_array($config['fields'])) ? $config['fields'] : [];
            $config['fields'] = array_replace($fromComponent, $ownFields);

        } catch (\Throwable $e) {
            Log::warning("Winter.Blocks: could not resolve component '{$componentName}': " . $e->getMessage());
        }

        return $config;
    }

    /**
     * Get the configuration of the provided block type
     */
    public function getConfig(string $type): ?array
    {
        return $this->getConfigs()[$type] ?? null;
    }

    /**
     * Check if the provided string is a valid block type
     */
    public function isBlock(string $type): bool
    {
        return !!$this->getConfig($type);
    }

    /**
     * Remove a block by key
     */
    public function removeBlock(string|array $key): void
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $this->removeBlock($k);
            }

            return;
        }

        unset($this->blocks[$key]);
    }
}
