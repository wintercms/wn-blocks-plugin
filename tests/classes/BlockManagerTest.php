<?php

namespace Winter\Blocks\Tests\Classes;

use Winter\Blocks\Classes\BlockManager;
use System\Tests\Bootstrap\PluginTestCase;

class BlockManagerTest extends PluginTestCase
{
    protected BlockManager $manager;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager = BlockManager::instance();
    }

    public function testGetBlocks()
    {
        $this->assertIsArray($this->manager->getRegisteredBlocks());
    }
}
