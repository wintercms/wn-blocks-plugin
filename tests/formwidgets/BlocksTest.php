<?php

namespace Winter\Blocks\Tests\FormWidgets;

use Backend\Classes\Controller;
use Backend\Classes\FormField;
use Backend\Widgets\Form;
use Cms\Classes\Theme;
use Illuminate\Support\Facades\Request;
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

    protected function createTestFormWidget(array $config = [], ?array $content = null): Blocks
    {
        Theme::load('blocktest');
        $controller = new Controller();
        $model = new Page();
        if ($content !== null) {
            $model->content = $content;
        }
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

    /**
     * Swaps in a fake POST request so the post() helper (used throughout
     * Repeater/Blocks AJAX handlers) returns the given values for the
     * duration of the test.
     */
    protected function fakePostRequest(array $data): void
    {
        Request::swap(\Illuminate\Http\Request::create('/', 'POST', $data));
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

    /**
     * @testdox onCopyItem() returns the full saved data for a block, including a switch and a nested repeater of blocks
     */
    public function testOnCopyItemReturnsFullSavedData()
    {
        BlockManager::instance()->registerBlock('complex', $this->fixturePath . 'complex.block');
        BlockManager::instance()->registerBlock('title', $this->fixturePath . 'title.block');

        $widget = $this->createTestFormWidget(['alias' => 'blocks'], [
            [
                '_group' => 'complex',
                'title' => 'Hello',
                'enabled' => '1',
                'items' => [
                    ['_group' => 'title', 'content' => 'Nested text'],
                ],
            ],
        ]);

        // onCopyItem() builds its Form widget and reads its value via
        // getSaveData(), which (like a real save) reads straight from the
        // posted form data at the item's array-name path -- this is exactly
        // what a normal AJAX request submits alongside the handler name, so
        // we replicate the full nested POST structure here rather than only
        // the two meta fields.
        $this->fakePostRequest([
            '_repeater_index' => 0,
            '_repeater_group' => 'complex',
            'content' => [
                0 => [
                    'title' => 'Hello',
                    'enabled' => '1',
                    'items' => [
                        0 => ['_group' => 'title', 'content' => 'Nested text'],
                    ],
                ],
            ],
        ]);

        $result = $widget->onCopyItem();
        $payload = json_decode($result['result'], true);

        $this->assertEquals('complex', $payload['group']);
        $this->assertEquals('Hello', $payload['data']['title']);
        $this->assertEquals(1, (int) $payload['data']['enabled']);
        $this->assertCount(1, $payload['data']['items']);
        $this->assertEquals('Nested text', $payload['data']['items'][0]['content']);
    }

    /**
     * @testdox onAddItem() with _paste_data seeds the new item (including its nested repeater of blocks) without touching existing items
     */
    public function testOnAddItemWithPasteDataSeedsNewItemWithoutClobberingExistingItems()
    {
        BlockManager::instance()->registerBlock('complex', $this->fixturePath . 'complex.block');
        BlockManager::instance()->registerBlock('title', $this->fixturePath . 'title.block');

        $existingItem = [
            '_group' => 'complex',
            'title' => 'Existing item',
            'enabled' => '0',
            'items' => [],
        ];

        $widget = $this->createTestFormWidget(['alias' => 'blocks'], [$existingItem]);

        $pasteData = [
            'title' => 'Pasted item',
            'enabled' => '1',
            'items' => [
                ['_group' => 'title', 'content' => 'Pasted nested text'],
            ],
        ];

        // Unlike onCopyItem(), a pasted item hasn't been submitted via a real
        // form post yet -- onAddItem() seeds it purely from _paste_data, so
        // no matching "content" POST array is needed here.
        $this->fakePostRequest([
            '_repeater_group' => 'complex',
            '_paste_data' => json_encode($pasteData),
            '_paste_config' => json_encode(['some' => 'config']),
        ]);

        $widget->onAddItem();

        // The new item (index 1, since index 0 already existed) is built
        // from the pasted data -- including its nested repeater of blocks,
        // not just the top-level fields a client-side DOM scrape could see.
        /** @var \Backend\Widgets\Form $newItemWidget */
        $newItemWidget = $widget->vars['widget'];

        $this->assertEquals($pasteData, (array) $newItemWidget->config->data);

        $reflectionFormWidgets = new \ReflectionProperty(\Backend\FormWidgets\Repeater::class, 'formWidgets');
        $reflectionFormWidgets->setAccessible(true);
        $formWidgets = $reflectionFormWidgets->getValue($widget);

        $this->assertArrayHasKey(1, $formWidgets, 'Expected a new item to have been added at index 1.');

        /** @var \Backend\FormWidgets\Repeater $nestedItems */
        $nestedItems = $formWidgets[1]->getFormWidget('items');
        $reflectionNestedWidgets = new \ReflectionProperty(\Backend\FormWidgets\Repeater::class, 'formWidgets');
        $reflectionNestedWidgets->setAccessible(true);
        $nestedFormWidgets = $reflectionNestedWidgets->getValue($nestedItems);

        $this->assertCount(1, $nestedFormWidgets, 'Expected the pasted nested repeater item to have been built.');
        $this->assertEquals('Pasted nested text', ((array) $nestedFormWidgets[0]->config->data)['content']);

        // The pasted config is tracked against the new item's index.
        $reflection = new \ReflectionProperty(Blocks::class, 'indexConfigMeta');
        $reflection->setAccessible(true);
        $this->assertEquals(['some' => 'config'], json_decode($reflection->getValue($widget)[1], true));

        // The pre-existing item at index 0 was not rebuilt/clobbered by the paste:
        // its Form widget still carries the data it was originally built with.
        $this->assertEquals($existingItem, (array) $formWidgets[0]->config->data);
    }
}
