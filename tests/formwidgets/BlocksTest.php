<?php

namespace Winter\Blocks\Tests\FormWidgets;

use Backend\Classes\Controller;
use Backend\Classes\FormField;
use Backend\Widgets\Form;
use Cms\Classes\Theme;
use System\Tests\Bootstrap\PluginTestCase;
use Winter\Blocks\Classes\BlockManager;
use Winter\Blocks\FormWidgets\Blocks;
use Winter\Blocks\Tests\Fixtures\Models\Page;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Support\Facades\Event;

/**
 * @testdox Blocks form widget (Winter\Blocks\FormWidgets\Blocks)
 * @covers \Winter\Blocks\FormWidgets\Blocks
 */
class BlocksTest extends PluginTestCase
{
    protected string $fixturePath;

    public function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = dirname(__DIR__) . '/fixtures/blocks/';

        Config::set('cms.activeTheme', 'blocktest');
        Config::set('cms.themesPath', '/plugins/winter/blocks/tests/fixtures/themes');

        Event::flush('cms.theme.getActiveTheme');
        Theme::resetCache();
    }

    protected function createTestFormWidget(array $config = []): Blocks
    {
        Theme::load('blocktest');
        $controller = new Controller();
        $model = new Page();
        $form = new Form($controller, [
            'model' => $model,
            'fields' => [],
        ]);
        $form->bindToController();

        $widget = new Blocks(
            $controller,
            new FormField('content', 'Content'),
            array_merge($config, [
                'parentForm' => $form,
                'model' => $model,
            ]),
        );

        $widget->init();
        return $widget;
    }

    public function testCanCreateFormWidget()
    {
        $this->assertInstanceOf(Blocks::class, $this->createTestFormWidget());
    }

    /**
     * Invokes the protected normalizeBlockFields() on a widget instance.
     */
    protected function normalizeBlockFields(Blocks $widget, array $fields): array
    {
        $method = new \ReflectionMethod(Blocks::class, 'normalizeBlockFields');
        $method->setAccessible(true);

        return $method->invoke($widget, $fields);
    }

    /**
     * @testdox translates collapsible: true into the data-block-collapsible attribute
     */
    public function testCollapsibleShorthandAddsAttribute()
    {
        $widget = $this->createTestFormWidget();

        $result = $this->normalizeBlockFields($widget, [
            'section_advanced' => [
                'type' => 'section',
                'label' => 'Advanced',
                'collapsible' => true,
            ],
        ]);

        $field = $result['section_advanced'];

        $this->assertArrayHasKey('data-block-collapsible', $field['containerAttributes']);
        // Defaults to collapsed: no "open" marker.
        $this->assertArrayNotHasKey('data-block-collapsible-open', $field['containerAttributes']);
        // Shorthand keys are removed once translated.
        $this->assertArrayNotHasKey('collapsible', $field);
        $this->assertArrayNotHasKey('collapsed', $field);
    }

    /**
     * @testdox marks a section as initially open when collapsed: false
     */
    public function testCollapsedFalseAddsOpenAttribute()
    {
        $widget = $this->createTestFormWidget();

        $result = $this->normalizeBlockFields($widget, [
            'section_open' => [
                'type' => 'section',
                'label' => 'Open',
                'collapsible' => true,
                'collapsed' => false,
            ],
        ]);

        $attrs = $result['section_open']['containerAttributes'];

        $this->assertArrayHasKey('data-block-collapsible', $attrs);
        $this->assertArrayHasKey('data-block-collapsible-open', $attrs);
    }

    /**
     * @testdox does not add the open marker when collapsed: true
     */
    public function testCollapsedTrueOmitsOpenAttribute()
    {
        $widget = $this->createTestFormWidget();

        $result = $this->normalizeBlockFields($widget, [
            'section_closed' => [
                'type' => 'section',
                'label' => 'Closed',
                'collapsible' => true,
                'collapsed' => true,
            ],
        ]);

        $attrs = $result['section_closed']['containerAttributes'];

        $this->assertArrayHasKey('data-block-collapsible', $attrs);
        $this->assertArrayNotHasKey('data-block-collapsible-open', $attrs);
    }

    /**
     * @testdox leaves non-section fields and sections without the shorthand untouched
     */
    public function testNonCollapsibleFieldsUntouched()
    {
        $widget = $this->createTestFormWidget();

        $result = $this->normalizeBlockFields($widget, [
            'title' => [
                'type' => 'text',
                'label' => 'Title',
            ],
            'plain_section' => [
                'type' => 'section',
                'label' => 'Plain',
            ],
        ]);

        $this->assertArrayNotHasKey('containerAttributes', $result['title']);
        $this->assertArrayNotHasKey('containerAttributes', $result['plain_section']);
    }

    public function testCanLimitAvailableBlocksByTag()
    {
        BlockManager::instance()->registerBlock('container', $this->fixturePath . 'container.block');
        BlockManager::instance()->registerBlock('richtext', $this->fixturePath . 'richtext.block');
        BlockManager::instance()->registerBlock('title', $this->fixturePath . 'title.block');

        $widget = $this->createTestFormWidget([
            'tags' => 'content',
        ]);

        // Only way we can see if the block is available through the public API is through getting the title of
        // the block. If the title is missing, the block isn't available.
        $this->assertEquals('Rich text', $widget->getGroupTitle('richtext'));
        $this->assertEquals('Title', $widget->getGroupTitle('title'));
        $this->assertNull($widget->getGroupTitle('container'));
    }
}
