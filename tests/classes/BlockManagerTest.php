<?php

namespace Winter\Blocks\Tests\Classes;

use Cms\Classes\CmsObjectCollection;
use Cms\Classes\Theme;
use Winter\Blocks\Classes\BlockManager;
use System\Tests\Bootstrap\PluginTestCase;
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

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = BlockManager::instance();
        $this->fixturePath = dirname(__DIR__) . '/fixtures/blocks/';
    }

    public function testCanRegisterBlocksDirectly()
    {
        $this->manager->registerBlock('container', $this->fixturePath . 'container.block');

        $this->assertIsArray($this->manager->getRegisteredBlocks());
        $this->assertEquals([
            'container' => $this->fixturePath . 'container.block'
        ], $this->manager->getRegisteredBlocks());
    }
}
