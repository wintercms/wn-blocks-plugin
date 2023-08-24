<?php

namespace Winter\Blocks\Tests;

use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Plugin;

/**
 * @testdox Plugin definition class (Winter\Blocks\Plugin)
 * @covers \Winter\Blocks\Plugin
 */
class PluginTest extends PluginTestCase
{
    protected Plugin $plugin;

    /**
     * @before
     */
    public function createPlugin(): void
    {
        $this->plugin = new Plugin($this->createApplication());
    }

    public function testSetsCorrectPluginDetails()
    {
        $details = $this->plugin->pluginDetails();

        $this->assertIsArray($details);
        $this->assertArrayHasKey('name', $details);
        $this->assertArrayHasKey('description', $details);
        $this->assertArrayHasKey('icon', $details);
        $this->assertArrayHasKey('author', $details);

        $this->assertEquals('Winter CMS', $details['author']);
    }

    public function testRegistersPermissions()
    {
        $this->markTestSkipped('Permissions have not been implemented yet.');

        $permissions = $this->plugin->registerPermissions();

        $this->assertIsArray($permissions);
    }
}
