<?php

namespace Winter\Blocks\Tests\Fixtures\Components;

use Cms\Classes\ComponentBase;

/**
 * Fixture component used to test `component: key` resolution in
 * BlockManager::resolveComponent().
 */
class TestComponent extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Test Component',
            'description' => 'A component used for testing block config component resolution.',
        ];
    }

    public function defineProperties()
    {
        return [
            'headline' => [
                'title' => 'Headline',
                'description' => 'The headline to display.',
                'type' => 'string',
                'default' => 'Hello world',
            ],
            'style' => [
                'title' => 'Style',
                'type' => 'dropdown',
                'options' => [
                    'bold' => 'Bold',
                    'italic' => 'Italic',
                ],
            ],
            'featured' => [
                'title' => 'Featured',
                'type' => 'checkbox',
                'default' => false,
            ],
        ];
    }
}
