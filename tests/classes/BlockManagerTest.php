<?php

namespace Winter\Blocks\Tests\Classes;

use Cms\Classes\ComponentManager;
use Cms\Classes\Theme;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Classes\BlockManager;
use Winter\Blocks\Tests\Fixtures\Components\TestComponent;
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
     * Invokes the protected resolveComponent() on the manager instance.
     */
    protected function resolveComponent(array $config): array
    {
        $method = new \ReflectionMethod(BlockManager::class, 'resolveComponent');
        $method->setAccessible(true);

        return $method->invoke($this->manager, $config);
    }

    /**
     * @testdox merges a component's defineProperties() into fields, with the block's own fields winning
     */
    public function testComponentPropertiesAreMergedIntoFields()
    {
        ComponentManager::instance()->registerComponent(TestComponent::class, 'testcomponent');

        $result = $this->resolveComponent([
            'component' => 'testcomponent',
            'fields' => [
                'style' => [
                    'label' => 'Style override',
                    'type' => 'dropdown',
                ],
            ],
        ]);

        // The component key is stripped once resolved.
        $this->assertArrayNotHasKey('component', $result);

        // Fields derived from the component's properties are present.
        $this->assertArrayHasKey('headline', $result['fields']);
        $this->assertEquals('Headline', $result['fields']['headline']['label']);
        $this->assertEquals('text', $result['fields']['headline']['type']);
        $this->assertEquals('Hello world', $result['fields']['headline']['default']);

        $this->assertArrayHasKey('featured', $result['fields']);
        $this->assertEquals('checkbox', $result['fields']['featured']['type']);

        // The block's own field definition for "style" wins over the component's.
        $this->assertEquals('Style override', $result['fields']['style']['label']);
        $this->assertEquals('dropdown', $result['fields']['style']['type']);
    }

    /**
     * @testdox leaves the config untouched when no component key is present
     */
    public function testNoComponentKeyIsNoop()
    {
        $config = [
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text'],
            ],
        ];

        $this->assertEquals($config, $this->resolveComponent($config));
    }

    /**
     * @testdox ignores an unresolvable component reference without throwing
     */
    public function testUnknownComponentIsIgnored()
    {
        $result = $this->resolveComponent([
            'component' => 'does_not_exist_component',
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text'],
            ],
        ]);

        $this->assertArrayNotHasKey('component', $result);
        $this->assertArrayHasKey('title', $result['fields']);
        $this->assertCount(1, $result['fields']);
    }

    /**
     * @testdox resolves a block file's `component:` key end-to-end through getConfigs()
     */
    public function testGetConfigsResolvesComponentKeyFromBlockFile()
    {
        ComponentManager::instance()->registerComponent(TestComponent::class, 'testcomponent');

        $this->manager->registerBlock('with_component', $this->fixturePath . 'with_component.block');

        $configs = $this->manager->getConfigs();

        $this->assertArrayHasKey('with_component', $configs);
        $this->assertArrayNotHasKey('component', $configs['with_component']);
        $this->assertArrayHasKey('headline', $configs['with_component']['fields']);
        $this->assertEquals('Hello world', $configs['with_component']['fields']['headline']['default']);
        // Block's own field definition still wins.
        $this->assertEquals('Style override', $configs['with_component']['fields']['style']['label']);
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
