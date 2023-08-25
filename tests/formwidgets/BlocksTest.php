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
