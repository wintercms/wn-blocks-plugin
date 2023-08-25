<?php

namespace Winter\Blocks\Tests\Classes;

use Cms\Classes\Theme;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Classes\BlockManager;
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
