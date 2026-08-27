<?php

namespace Winter\Blocks\Tests\Console;

use Artisan;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Console\ScaffoldCommand;

/**
 * Guards the safety behaviour of the demo-content scaffolder.
 *
 * Winter.Blocks stores no database records of its own — this command writes a
 * demo layout and Winter.Pages static pages into the active theme's files. That
 * full theme-file seeding is verified against a real install rather than here, so
 * the isolated test does not mutate the on-disk demo theme; it asserts the command
 * is wired up and refuses to run in production.
 */
class ScaffoldCommandTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Plugin console commands are registered via ConsoleApplication::starting, which has
        // already fired by the time the test harness boots the plugin — so the command isn't
        // resolvable through Artisan here. Register it directly with the kernel for the test.
        $this->app->make(ConsoleKernel::class)->registerCommand(new ScaffoldCommand());
    }

    public function testCommandIsRegistered()
    {
        $this->assertArrayHasKey('scaffold:winter.blocks', Artisan::all());
    }

    public function testRefusesToRunInProduction()
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('scaffold:winter.blocks');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('production', Artisan::output());

        $this->app['env'] = 'testing';
    }
}
