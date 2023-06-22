<?php

namespace Winter\Blocks\Tests;

use Winter\Blocks\Plugin;
use System\Tests\Bootstrap\PluginTestCase;

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
        $this->markTestIncomplete('Permissions are not being set yet');

        $permissions = $this->plugin->registerPermissions();

        $this->assertIsArray($permissions);
    }
}
