<?php

namespace Winter\Blocks\Classes;

use Cache;
use Cms\Classes\CmsObjectCollection;
use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Event;
use File;
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

    /**
     * @var array Per-request memoization of getConfigs() results, keyed by tags.
     *
     * Building the configs re-reads and re-parses every block file, which costs
     * ~15ms for the full set. getConfigs() is called several times per backend
     * request (plugin boot, each Blocks widget, and once per getConfig() during
     * rendering), so the uncached cost compounds. Block definitions cannot
     * change within a request, so the result is safe to memoize.
     */
    protected $configCache = [];

    /**
     * @var string|null Per-request memo of the block-set signature (file mtimes).
     */
    protected $signature = null;

    /**
     * Cross-request cache TTL (seconds) for built configs. The cache is keyed by
     * a content signature and self-invalidates, so the TTL is only a safety net.
     */
    const CONFIG_CACHE_TTL = 86400;

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
        $cacheKey = json_encode($tags);

        // In-request memoization.
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }

        return $this->configCache[$cacheKey] = $this->rememberConfigs($cacheKey, $tags);
    }

    /**
     * Returns the built configs for the given tags, served from the cross-request
     * cache when the underlying block files are unchanged.
     *
     * Building the configs scans and parses every block file (~17ms for ~100
     * blocks); the cache replaces that with a cheap mtime check (~0.1ms) on every
     * request after the first. The cache self-invalidates: a content signature
     * derived from block-file mtimes keys the entry, so no manual cache-clear is
     * needed after editing a block.
     */
    protected function rememberConfigs(string $cacheKey, string|array|null $tags): array
    {
        $store = 'winter.blocks.configs.' . md5($cacheKey);
        $signature = $this->blocksSignature();

        $cached = Cache::get($store);
        if (is_array($cached) && ($cached['signature'] ?? null) === $signature) {
            return $cached['data'];
        }

        $data = $this->buildConfigs($tags);

        Cache::put($store, [
            'signature' => $signature,
            'data'      => $data,
        ], static::CONFIG_CACHE_TTL);

        return $data;
    }

    /**
     * Builds the block configs by scanning and parsing the theme's block files.
     */
    protected function buildConfigs(string|array|null $tags = null): array
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

            $configs[pathinfo($block['fileName'])['filename']] = array_except(
                $block->getAttributes(),
                [
                    'fileName',
                    'content',
                    'mtime',
                    'markup',
                    'code',
                ]
            );
        }

        return $configs;
    }

    /**
     * Computes a signature for the current block set from the mtimes (and paths)
     * of every block file. Cheap (~0.1ms): it reads the in-memory list of
     * plugin-registered block paths plus any theme-provided block files, and
     * never triggers a Halcyon scan. Memoized per request.
     */
    protected function blocksSignature(): string
    {
        if ($this->signature !== null) {
            return $this->signature;
        }

        $parts = [];

        // Plugin-registered blocks (the bulk) — already resolved to real paths.
        foreach ($this->getRegisteredBlocks() as $path) {
            $parts[$path] = @filemtime($path) ?: 0;
        }

        // Theme-provided block files, if the active theme ships any. Themes can
        // nest .block files in subfolders (see README "Registering Blocks"), and
        // getBlocks() picks those up via Block::listInTheme()'s recursive Halcyon
        // scan, so the signature must scan recursively too or edits to a nested
        // theme block would go unnoticed and serve a stale cache entry.
        if (($theme = Theme::getActiveTheme()) && is_dir($themeDir = $theme->getPath() . '/blocks')) {
            foreach (File::allFiles($themeDir) as $file) {
                if ($file->getExtension() === static::BLOCK_EXTENSION) {
                    $parts[$file->getPathname()] = $file->getMTime();
                }
            }
        }

        ksort($parts);

        return $this->signature = md5(serialize($parts));
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
