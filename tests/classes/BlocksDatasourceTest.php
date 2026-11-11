<?php

namespace Winter\Blocks\Tests\Classes;

use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Classes\BlockProcessor;
use Winter\Blocks\Classes\BlocksDatasource;

/**
 * @testdox Blocks datasource (Winter\Blocks\Classes\BlocksDatasource)
 * @covers \Winter\Blocks\Classes\BlocksDatasource
 */
class BlocksDatasourceTest extends PluginTestCase
{
    public function testUsesTheBlockProcessorWithoutDynamicProperties()
    {
        $deprecations = [];
        // Collected directly: dynamic property deprecations never reach PHPUnit as failures.
        set_error_handler(function ($level, $message) use (&$deprecations) {
            $deprecations[] = $message;
            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $datasource = new BlocksDatasource();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertInstanceOf(BlockProcessor::class, $datasource->getPostProcessor());
    }
}
