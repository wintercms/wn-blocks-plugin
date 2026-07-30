<?php

namespace Winter\Blocks\Tests\Classes;

use Cms\Classes\Theme;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Classes\BlockManager;
use Winter\Blocks\Tests\Fixtures\Classes\BlockManagerCacheTestDouble;
use Illuminate\Support\Facades\Cache;
use Winter\Storm\Filesystem\PathResolver;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Support\Facades\Event;

/**
 * @testdox Block manager (Winter\Blocks\Classes\BlockManager)
 * @covers \Winter\Blocks\Classes\BlockManager
 */
class BlockManagerTest extends PluginTestCase
{
    protected BlockManager $manager;

    protected string $fixturePath;
    protected string $pluginPath;

    public function setUp(): void
    {
        parent::setUp();

        Config::set('cms.activeTheme', 'blocktest');
        Config::set('cms.themesPath', '/plugins/winter/blocks/tests/fixtures/themes');

        Event::flush('cms.theme.getActiveTheme');
        Theme::resetCache();

        $this->manager = BlockManager::instance();
        $this->fixturePath = dirname(__DIR__) . '/fixtures/blocks/';
        $this->pluginPath = dirname(dirname(__DIR__)) . '/blocks/';
    }

    /**
     * @var int Monotonically increasing mtime used by writeMutableBlock(), so
     * that successive writes within the same test are guaranteed a distinct
     * mtime regardless of how close together (in wall-clock time) they happen.
     */
    protected int $nextMutableBlockMtime = 0;

    /**
     * Writes a block file to a scratch path (outside version control) with the
     * given "default" field value, so it can be rewritten mid-test to simulate
     * an edit to the block definition between requests.
     */
    protected function writeMutableBlock(string $path, string $defaultValue): void
    {
        file_put_contents($path, <<<BLOCK
name: Mutable
description: Mutable block used to test the config cache
icon: icon-square
tags: ["content"]
fields:
    content:
        label: false
        type: text
        default: {$defaultValue}
==

BLOCK);
        // Force a distinct, monotonically increasing mtime so the cache
        // signature actually changes. Two writes within the same test can
        // otherwise land in the same wall-clock second, which would leave the
        // mtime -- and therefore the signature -- unchanged.
        if ($this->nextMutableBlockMtime === 0) {
            $this->nextMutableBlockMtime = time();
        }
        $this->nextMutableBlockMtime++;
        touch($path, $this->nextMutableBlockMtime);
        clearstatcache(true, $path);
    }

    /**
     * @testdox memoizes getConfigs() results within a single request/instance
     */
    public function testGetConfigsIsMemoizedWithinRequest()
    {
        $path = sys_get_temp_dir() . '/winter_blocks_cache_test_' . uniqid() . '.block';
        $this->writeMutableBlock($path, 'original');

        $this->manager->registerBlock('mutable', $path);

        $first = $this->manager->getConfigs();
        $this->assertEquals('original', $first['mutable']['fields']['content']['default']);

        // Edit the file (and bump its mtime) after the first call.
        $this->writeMutableBlock($path, 'changed');

        // Same manager instance/request: the in-memory memoization must return
        // the exact same result without re-reading the file, even though its
        // mtime (and thus the signature) has changed.
        $second = $this->manager->getConfigs();
        $this->assertEquals($first, $second);
        $this->assertEquals('original', $second['mutable']['fields']['content']['default']);

        unlink($path);
    }

    /**
     * @testdox serves getConfigs() from the cross-request cache without rebuilding when the block set is unchanged
     */
    public function testGetConfigsServesFromCrossRequestCache()
    {
        $path = sys_get_temp_dir() . '/winter_blocks_cache_test_' . uniqid() . '.block';
        $this->writeMutableBlock($path, 'original');

        BlockManagerCacheTestDouble::forgetInstance();
        BlockManagerCacheTestDouble::$buildCallCount = 0;
        BlockManagerCacheTestDouble::$buildReturn = ['built_by' => 'first-request'];

        /** @var BlockManagerCacheTestDouble $requestOne */
        $requestOne = BlockManagerCacheTestDouble::instance();
        $requestOne->registerBlock('mutable', $path);

        $first = $requestOne->getConfigs();
        $this->assertEquals(['built_by' => 'first-request'], $first);
        $this->assertEquals(1, BlockManagerCacheTestDouble::$buildCallCount);

        $store = 'winter.blocks.configs.' . md5(json_encode(null));
        $cached = Cache::get($store);
        $this->assertIsArray($cached, 'Expected the cross-request cache entry to have been written.');
        $this->assertArrayHasKey('signature', $cached);
        $this->assertEquals($first, $cached['data']);

        // Simulate a brand new request: new instance, block set unchanged,
        // but configured to build something different if it were rebuilt.
        BlockManagerCacheTestDouble::forgetInstance();
        BlockManagerCacheTestDouble::$buildReturn = ['built_by' => 'second-request'];

        /** @var BlockManagerCacheTestDouble $requestTwo */
        $requestTwo = BlockManagerCacheTestDouble::instance();
        $requestTwo->registerBlock('mutable', $path);

        $second = $requestTwo->getConfigs();

        // The signature is unchanged, so it must be served from cache: the
        // build method must not have been called again, and the data must
        // still be the first request's payload, not the (different) value
        // the second request's buildConfigs() would have produced.
        $this->assertEquals(1, BlockManagerCacheTestDouble::$buildCallCount);
        $this->assertEquals($first, $second);
        $this->assertNotEquals(['built_by' => 'second-request'], $second);

        unlink($path);
    }

    /**
     * @testdox invalidates the cross-request cache when a block file's mtime changes
     */
    public function testGetConfigsCacheInvalidatesOnFileChange()
    {
        $path = sys_get_temp_dir() . '/winter_blocks_cache_test_' . uniqid() . '.block';
        $this->writeMutableBlock($path, 'original');

        BlockManagerCacheTestDouble::forgetInstance();
        BlockManagerCacheTestDouble::$buildCallCount = 0;
        BlockManagerCacheTestDouble::$buildReturn = ['built_by' => 'first-request'];

        /** @var BlockManagerCacheTestDouble $requestOne */
        $requestOne = BlockManagerCacheTestDouble::instance();
        $requestOne->registerBlock('mutable', $path);

        $first = $requestOne->getConfigs();
        $this->assertEquals(1, BlockManagerCacheTestDouble::$buildCallCount);

        // Simulate editing the block file between requests (changes its mtime,
        // and therefore the block-set signature).
        $this->writeMutableBlock($path, 'changed');

        BlockManagerCacheTestDouble::forgetInstance();
        BlockManagerCacheTestDouble::$buildReturn = ['built_by' => 'second-request'];

        /** @var BlockManagerCacheTestDouble $requestTwo */
        $requestTwo = BlockManagerCacheTestDouble::instance();
        $requestTwo->registerBlock('mutable', $path);

        $second = $requestTwo->getConfigs();

        // The signature changed, so the cache must be considered stale: the
        // build method must have been called again, and the fresh data
        // returned instead of the first request's cached payload.
        $this->assertEquals(2, BlockManagerCacheTestDouble::$buildCallCount);
        $this->assertEquals(['built_by' => 'second-request'], $second);
        $this->assertNotEquals($first, $second);

        unlink($path);
    }

    /**
     * @testdox includes nested theme block files in the cache signature
     */
    public function testBlocksSignatureIncludesNestedThemeBlocks()
    {
        // Themes may nest .block files in subfolders (see README "Registering
        // Blocks"), and getBlocks() picks those up via a recursive Halcyon
        // scan. The signature must scan just as deep, or an edit to a nested
        // theme block would go unnoticed and the cache would serve stale data.
        $themeBlocksDir = Theme::getActiveTheme()->getPath() . '/blocks';
        $nestedDir = $themeBlocksDir . '/nested_signature_test';
        mkdir($nestedDir, 0777, true);
        $path = $nestedDir . '/mutable.block';
        file_put_contents($path, "name: Nested\ndescription: Nested theme block\n");
        touch($path, time());

        $reflection = new \ReflectionMethod(BlockManager::class, 'blocksSignature');
        $reflection->setAccessible(true);

        try {
            BlockManager::forgetInstance();
            $before = $reflection->invoke(BlockManager::instance());

            touch($path, time() + 10);

            BlockManager::forgetInstance();
            $after = $reflection->invoke(BlockManager::instance());

            $this->assertNotEquals(
                $before,
                $after,
                'Expected the signature to change when a nested theme block file\'s mtime changes.'
            );
        } finally {
            unlink($path);
            rmdir($nestedDir);
        }
    }

    public function testCanRegisterBlocksDirectly()
    {
        $this->manager->registerBlock('container', $this->fixturePath . 'container.block');

        $this->assertIsArray($this->manager->getRegisteredBlocks());
        $this->assertEquals([
            'container' => PathResolver::standardize($this->fixturePath . 'container.block'),
            'button_group' => PathResolver::standardize($this->pluginPath . 'button_group.block'),
            'button' => PathResolver::standardize($this->pluginPath . 'button.block'),
            'cards' => PathResolver::standardize($this->pluginPath . 'cards.block'),
            'code' => PathResolver::standardize($this->pluginPath . 'code.block'),
            'columns_two' => PathResolver::standardize($this->pluginPath . 'columns_two.block'),
            'divider' => PathResolver::standardize($this->pluginPath . 'divider.block'),
            'image' => PathResolver::standardize($this->pluginPath . 'image.block'),
            'plaintext' => PathResolver::standardize($this->pluginPath . 'plaintext.block'),
            'richtext' => PathResolver::standardize($this->pluginPath . 'richtext.block'),
            'title' => PathResolver::standardize($this->pluginPath . 'title.block'),
            'video' => PathResolver::standardize($this->pluginPath . 'video.block'),
            'vimeo' => PathResolver::standardize($this->pluginPath . 'vimeo.block'),
            'youtube' => PathResolver::standardize($this->pluginPath . 'youtube.block'),
        ], $this->manager->getRegisteredBlocks());
    }
}
