<?php

namespace Winter\Blocks\Tests\Classes;

use Cms\Classes\Theme;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Classes\BlockManager;
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
            'container' => $this->fixturePath . 'container.block',
            'button_group' => $this->pluginPath . 'button_group.block',
            'button' => $this->pluginPath . 'button.block',
            'cards' => $this->pluginPath . 'cards.block',
            'code' => $this->pluginPath . 'code.block',
            'columns_two' => $this->pluginPath . 'columns_two.block',
            'divider' => $this->pluginPath . 'divider.block',
            'image' => $this->pluginPath . 'image.block',
            'plaintext' => $this->pluginPath . 'plaintext.block',
            'richtext' => $this->pluginPath . 'richtext.block',
            'title' => $this->pluginPath . 'title.block',
            'video' => $this->pluginPath . 'video.block',
            'vimeo' => $this->pluginPath . 'vimeo.block',
            'youtube' => $this->pluginPath . 'youtube.block',
        ], $this->manager->getRegisteredBlocks());
    }
}
