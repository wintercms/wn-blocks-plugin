<?php

return [
    'plugin' => [
        'name' => 'Blocks',
        'description' => 'Block based content management plugin for Winter CMS.',
    ],
    'actions' => [
        'open_url' => [
            'name' => 'Open URL',
            'description' => 'Open the provided URL',
            'href' => 'URL',
            'target' => 'Open URL in',
            'target_self' => 'Same tab',
            'target_blank' => 'New tab',
        ],
    ],
    'blocks' => [
        'button' => [
            'name' => 'Button',
            'description' => 'A clickable button',
        ],
        'button_group' => [
            'name' => 'Button Group',
            'description' => 'Group of clickable buttons',
            'buttons' => 'Buttons',
            'position_center' => 'Center',
            'position_left' => 'Left',
            'position_right' => 'Right',
            'position' => 'Position',
            'width_auto' => 'Auto',
            'width_full' => 'Full',
            'width' => 'Width',
        ],
        'cards' => [
            'name' => 'Cards',
            'description' => 'Content in card format',
        ],
        'code' => [
            'name' => 'Code',
            'description' => 'Custom HTML content',
        ],
        'columns_two' => [
            'name' => 'Two Columns',
            'description' => 'Two columns of content',
            'left' => 'Left Column',
            'right' => 'Right Column',
        ],
        'divider' => [
            'name' => 'Divider',
            'description' => 'Horizontal dividing line',
        ],
        'image' => [
            'name' => 'Image',
            'description' => 'Single image from Media Library',
            'alt_text' => 'Description (for screen readers)',
            'size' => [
                'w-full' => 'Full',
                'w-2/3' => 'Two Thirds',
                'w-1/2' => 'Half',
                'w-1/3' => 'Third',
                'w-1/4' => 'Quarter',
            ],
        ],
        'plaintext' => [
            'name' => 'Plain text',
            'description' => 'Content with no formatting',
        ],
        'richtext' => [
            'name' => 'Rich Text',
            'description' => 'Content with basic formatting',
        ],
        'title' => [
            'name' => 'Title',
            'description' => 'Large text with size options',
            'size' => [
                'h4' => 'Small',
                'h3' => 'Medium',
                'h2' => 'Large',
            ],
        ],
        'video' => [
            'name' => 'Video',
            'description' => 'Embed a Media Library video',
        ],
        'vimeo' => [
            'name' => 'Vimeo',
            'description' => 'Embed a Vimeo video',
            'vimeo_id' => 'Vimeo Video ID',
        ],
        'youtube' => [
            'name' => 'YouTube',
            'description' => 'Embed a YouTube video',
            'youtube_id' => 'YouTube Video ID',
        ],
    ],
    'fields' => [
        'actions_prompt' => 'Add action',
        'actions' => 'Actions',
        'blocks_prompt' => 'Add block',
        'blocks' => 'Blocks',
        'color' => 'Color',
        'content' => 'Content',
        'icon' => 'Icon',
        'label' => 'Label',
        'size' => 'Size',
        'alignment_x' => [
            'label' => 'Horizontal Alignment',
            'left' => 'Left',
            'center' => 'Center',
            'right' => 'Right',
        ],
    ],
];
