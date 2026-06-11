<?php

namespace Winter\Blocks\Classes;

use Cms\Classes\CmsObjectCollection;
use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Event;
use File;
use Yaml;
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

            $config = $this->resolveIncludes($config);

            $configs[pathinfo($block['fileName'])['filename']] = $config;
        }

        return $configs;
    }

    /**
     * Resolves an `include` directive in a block definition by merging field
     * definitions from one or more external YAML files.
     *
     * A block may declare:
     *
     *     include: $/author/plugin/blocks/_shared.yaml
     *     # or
     *     include:
     *         - $/author/plugin/blocks/_seo.yaml
     *         - ~/app/blocks/_tracking.yaml
     *
     * Each included file is a plain YAML file that may contain any of the keys
     * `fields`, `tabs`, `secondaryTabs` and `config`. Included definitions are
     * merged in order and act as a base; the block's own definitions take
     * precedence on key collisions.
     *
     * Paths are resolved with File::symbolizePath(), so the usual Winter symbols
     * are supported ($ = plugins, ~ = app, # = app/storage/...).
     */
    protected function resolveIncludes(array $config): array
    {
        if (empty($config['include'])) {
            unset($config['include']);
            return $config;
        }

        $paths = (array) $config['include'];
        unset($config['include']);

        $mergeKeys = ['fields', 'tabs', 'secondaryTabs', 'config'];

        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $realPath = File::symbolizePath($path);
            if (!$realPath || !File::exists($realPath)) {
                continue;
            }

            $included = Yaml::parse(File::get($realPath));
            if (!is_array($included)) {
                continue;
            }

            foreach ($mergeKeys as $key) {
                if (!isset($included[$key]) || !is_array($included[$key])) {
                    continue;
                }

                // Included definitions form the base; the block's own win on collision.
                $config[$key] = array_replace_recursive(
                    $included[$key],
                    (isset($config[$key]) && is_array($config[$key])) ? $config[$key] : []
                );
            }
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
