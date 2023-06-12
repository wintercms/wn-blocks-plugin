# Blocks Plugin

Provides a "block based" content management experience in Winter CMS

>**NOTE:** This plugin is still in development and is likely to undergo changes. Do not use in production environments without using a version constraint in your composer.json file and carefully monitoring for breaking changes.

>**NOTE:** This plugin requires Winter CMS v1.2.2 or above.


## Installation

This plugin is available for installation via [Composer](http://getcomposer.org/).

```bash
composer require winter/wn-blocks-plugin
```

In order to have the `actions` support function correctly, you need to load `/plugins/winter/blocks/assets/dist/js/blocks.js` after the Snowboard framework has been loaded.


## Core Concepts

### Blocks

This plugin manages the concept of "blocks" in Winter CMS. Blocks are self contained pieces of structured content that can be managed and rendered in a variety of ways.

Blocks can be provided by both plugins and themes and can be overridden by themes.

### Actions

This plugin also introduces the concepts of "actions"; a way to define and execute client side actions that can be triggered by various events. Currently, actions are only defined in the `$/winter/blocks/meta/actions.yaml` file and must exist as a function on the `window.actions` object in the frontend keyed by the action's identifier that receives the `data` object as the first argument and (optionally) the `event` object that triggered the action as the second argument.

>**NOTE:** This is very much a WIP API and is subject to change. Feedback very much welcome here for ideas around how to register, manage, extend, and provide actions to the frontend.


## Registering Blocks

Themes can have their blocks automatically registered by placing `.block` files in the `/blocks` folder and subfolders.

Plugins can register blocks by providing a `registerBlocks()` method in their Plugin.php
file. The method should return an array of block definitions in the following format:

```php
public function registerBlocks(): array
{
    return [
        'example' => '$/myauthor/myplugin/blocks/example.block',
    ];
}
```

<!--
    @TODO: For future implementation, consider performance hit of scanning directory

    Plugins can also have their content blocks be automatically registered by placing `.block` files in the `/blocks` folder and subfolders of the plugin.
-->



### Block Definition

Blocks are defined as `.block` files that consist of 1 to 3 parts:
- A YAML configuration section that defines the block's name, description, and other metadata
as well as the block's properties and the form used to edit those properties
- A PHP code section that allows for basic code to be executed when the block is rendered,
similar to a partial
- A Twig template section that defines the HTML markup template of the block

When there are two parts, they are the Settings & Markup sections. When there is just one part
it is the Markup section.

The following property values (name, description, etc) can be defined in the Settings section
of the `.block` files:

```yaml
name: Example
description: Example Block Description
icon: icon-name
ignoreContext: [] # List of contexts to never include the block in
allowContext: [] # List of contexts to only include the block in
permissions: [] # List of permissions required to interact with the block
fields: # The form fields used to configure the block
```

Blocks can use components in them, although they may face lifecycle limitations with complex
AJAX handlers similar to component support in partials.


## Using the `blocks` FormWidget

In order to provide an interface for managing block-based content, this plugin provides the `blocks` FormWidget. This widget can be used in the backend as a form field to manage blocks.

The `blocks` FormWidget supports two additional properties:

- `allow`: An array of block types that are allowed to be added to the widget. If specified, only those block types listed will be available to add to the current instance of the field.
- `ignore`: A list of block types that are not allowed to be added to the widget. If specified, all block types will be available to add to the current instance of the field, except those listed.

Those properties allow you to limit the block types that can be added to a specific instance of the widget, which can be very helpful when building "container" type blocks that need to avoid including themselves or only support a specific set of blocks as "children".

### Examples

The `button_group` block type only allows `button` blocks to be added to it:

```yaml
buttons:
    label: Buttons
    span: full
    type: blocks
    allow:
        - button
```

The `columns_two` block type allows every block except for itself to be added to it:

```yaml
left:
    label: Left Column
    span: left
    type: blocks
    ignore:
        - columns_two
right:
    label: Right Column
    span: right
    type: blocks
    ignore:
        - columns_two
```

### Integration with the Winter.Pages plugin:

Include the following line in your layout file to include the blocks FormWidget on a Winter.Pages page:

```twig
{variable type="blocks" name="blocks" blockContext="pages" tab="winter.pages::lang.editor.content"}{/variable}
```


## Rendering Blocks

### Using Twig

Twig functions are provided by this plugin for rendering blocks.
You can then use the following Twig snippet to render the blocks data in your layout:

```twig
{{ renderBlocks(blocks) }}
```

You can use it anywhere an expression is accepted:

```twig
{{ ('<p>Some text</p>' ~ renderBlocks(blocks) ~ '<p>Some more text<p/>') | raw }}

{% set myContent = renderBlocks(blocks) %}
```

If you need to render a single block, you can use the `renderBlock` function:

```twig
{{ renderBlock({
    '_group':'title',
    'content':'Lorem ipsum dolor sit amet.',
    'alignment_x':'left',
    'size':'h1',
}) }}

{{ renderBlock('title', {
    'content':'Lorem ipsum dolor sit amet.',
    'alignment_x':'left',
    'size':'h1',
}) }}
```

### Using a partial

If you need to customize the rendering of blocks according to their group, you can use a special `blocks.htm` partial in your theme:

```twig
{% for blockIndex, block in blocks %}
    {# Adding blocks to the following array allows them to implement their own containers #}
    {% if block._group in ["hero", "section"] %}
        {% partial block._group ~ ".block" data=block blockIndex=blockIndex %}
    {% else %}
        <section class="flex flex-wrap items-center mx-auto max-w-screen-xl">
            <div class="w-full p-4">
                {% partial block._group ~ ".block" data=block blockIndex=blockIndex %}
            </div>
        </section>
    {% endif %}
{% endfor %}
```

You can then use the following Twig snippet to render the block data in your layout:

```twig
{% partial 'blocks' blocks=blocks %}
```

### Using PHP

```php
use Winter\Blocks\Classes\Block;

// Render a single block from stored data
Block::render($model->blocks[0]);

// Render an array of blocks from stored data
Block::renderAll($model->blocks);

// Render a single block manually
Block::render('title', [
    'content' => 'Lorem ipsum dolor sit amet.',
    'alignment_x' => 'left',
    'size' => 'h1',
]);

// Render a single block manually using only array data
Block::render([
    '_group' => 'title',
    'content' => 'Lorem ipsum dolor sit amet.',
    'alignment_x' => 'left',
    'size' => 'h1',
]);
```


## Integrating with TailwindCSS / CSS Purging

If your theme uses CSS class purging (i.e. Tailwind), it can be useful to add the following paths to your build configuration to include the styles for any blocks defined by the theme or plugins.

```js
// tailwind.config.js
module.exports = {
    content: [
        // Winter.Pages static page content
        './content/**/*.htm',
        './layouts/**/*.htm',
        './pages/**/*.htm',
        './partials/**/*.htm',
        './blocks/**/*.block',

        // Blocks provided by plugins
        '../../plugins/*/*/blocks/*.block',
    ],
};
```


## Feedback

> The Winter.Blocks is perfect for my block-based themes. I've been looking for something like this for a long time
