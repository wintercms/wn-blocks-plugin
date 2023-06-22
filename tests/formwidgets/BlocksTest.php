<?php

namespace Winter\Blocks\Tests\FormWidgets;

use Cms\Classes\Theme;
use Backend\Classes\Controller;
use Backend\Classes\FormField;
use Backend\Widgets\Form;
use System\Tests\Bootstrap\PluginTestCase;
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
    protected Blocks $formWidget;

    public function setUp(): void
    {
        parent::setUp();

        $this->createTestFormWidget();
    }

    protected function createTestFormWidget(array $config = [])
    {
        $controller = new Controller();
        $model = new Page();
        $form = new Form($controller, [
            'model' => $model,
            'fields' => [],
        ]);
        $form->bindToController();

        $this->formWidget = new Blocks(
            $controller,
            new FormField('content', 'Content'),
            array_merge($config, [
                'parentForm' => $form,
                'model' => $model,
            ]),
        );
    }

    public function testCanCreateFormWidget()
    {
        $this->assertInstanceOf(Blocks::class, $this->formWidget);
    }
}
