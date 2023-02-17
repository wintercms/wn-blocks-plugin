<?php

namespace Winter\Blocks\Classes;

use Cms\Classes\CmsObjectCollection;
use Cms\Classes\Controller;
use Cms\Classes\Theme;
use Event;
use File;
use System\Classes\PluginManager;
use Winter\Storm\Support\Traits\Singleton;
use Winter\Storm\Support\Str;
use Winter\Storm\Exception\SystemException;

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

        $this->blocks[$key] = $realPath;
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
    public function getConfigs(?string $context = null): array
    {
        $configs = [];
        foreach ($this->getBlocks() as $block) {
            if (isset($block->context) && !is_null($context) && !in_array($context, $block->context)) {
                continue;
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
}
