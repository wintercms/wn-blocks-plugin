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

    /**
     * Invokes the protected resolveIncludes() with the given block config.
     */
    protected function resolveIncludes(array $config): array
    {
        $method = new \ReflectionMethod(BlockManager::class, 'resolveIncludes');
        $method->setAccessible(true);

        return $method->invoke($this->manager, $config);
    }

    protected function includePath(string $file): string
    {
        return '$/winter/blocks/tests/fixtures/blocks/includes/' . $file;
    }

    /**
     * @testdox merges fields and config from an included file
     */
    public function testIncludeMergesDefinitions()
    {
        $result = $this->resolveIncludes([
            'include' => $this->includePath('_shared.yaml'),
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text'],
            ],
        ]);

        // The include key itself is stripped.
        $this->assertArrayNotHasKey('include', $result);

        // Fields from both the include and the block are present.
        $this->assertArrayHasKey('shared_field', $result['fields']);
        $this->assertArrayHasKey('title', $result['fields']);

        // config from the include is merged in too.
        $this->assertArrayHasKey('shared_config', $result['config']);

        // tabs are not a supported merge key.
        $this->assertArrayNotHasKey('tabs', $result);
    }

    /**
     * @testdox lets the block's own definitions override the include on collision
     */
    public function testBlockOverridesInclude()
    {
        $result = $this->resolveIncludes([
            'include' => $this->includePath('_shared.yaml'),
            'fields' => [
                'overridden' => ['label' => 'From block', 'type' => 'textarea'],
            ],
        ]);

        $this->assertEquals('From block', $result['fields']['overridden']['label']);
        $this->assertEquals('textarea', $result['fields']['overridden']['type']);
    }

    /**
     * @testdox resolves nested includes recursively
     */
    public function testNestedIncludesAreResolved()
    {
        $result = $this->resolveIncludes([
            'include' => $this->includePath('_with_nested.yaml'),
            'fields' => [
                'own_field' => ['label' => 'Own', 'type' => 'text'],
            ],
        ]);

        // base_field (deepest), mid_field (middle), own_field (block) all present.
        $this->assertArrayHasKey('base_field', $result['fields']);
        $this->assertArrayHasKey('mid_field', $result['fields']);
        $this->assertArrayHasKey('own_field', $result['fields']);
    }

    /**
     * @testdox does not loop on circular includes and still merges what it can
     */
    public function testCircularIncludeIsGuarded()
    {
        $result = $this->resolveIncludes([
            'include' => $this->includePath('_cycle_a.yaml'),
            'fields' => [
                'own_field' => ['label' => 'Own', 'type' => 'text'],
            ],
        ]);

        // Both ends of the cycle contribute their fields; no infinite recursion.
        $this->assertArrayHasKey('field_a', $result['fields']);
        $this->assertArrayHasKey('field_b', $result['fields']);
        $this->assertArrayHasKey('own_field', $result['fields']);
    }

    /**
     * @testdox skips a missing include file without error
     */
    public function testMissingIncludeIsSkipped()
    {
        $result = $this->resolveIncludes([
            'include' => $this->includePath('_does_not_exist.yaml'),
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text'],
            ],
        ]);

        $this->assertArrayNotHasKey('include', $result);
        $this->assertArrayHasKey('title', $result['fields']);
        $this->assertCount(1, $result['fields']);
    }

    /**
     * @testdox accepts a list of includes merged in order
     */
    public function testMultipleIncludes()
    {
        $result = $this->resolveIncludes([
            'include' => [
                $this->includePath('_base.yaml'),
                $this->includePath('_shared.yaml'),
            ],
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text'],
            ],
        ]);

        $this->assertArrayHasKey('base_field', $result['fields']);
        $this->assertArrayHasKey('shared_field', $result['fields']);
        $this->assertArrayHasKey('title', $result['fields']);
    }

    /**
     * @testdox leaves a block without an include untouched
     */
    public function testNoIncludeIsNoop()
    {
        $config = [
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text'],
            ],
        ];

        $this->assertEquals($config, $this->resolveIncludes($config));
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
