# Blocks Plugin

![Blocks Plugin](https://github.com/wintercms/wn-blocks-plugin/blob/main/.github/banner.png?raw=true)

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/wintercms/wn-blocks-plugin/blob/main/LICENSE)

Provides a "block based" content management experience in Winter CMS

>**NOTE:** This plugin is still in development and is likely to undergo changes. Do not use in production environments without using a version constraint in your composer.json file and carefully monitoring for breaking changes.

> **Block definition features:** **collapsible sections** (with persisted state), **tabs/secondaryTabs** support, **shared field includes** (with nested includes), and **recently used blocks** in the palette. See [Collapsible Sections](#collapsible-sections), [Tabs](#tabs), [Including shared field definitions](#including-shared-field-definitions), and [Recently used blocks](#recently-used-blocks) below. Full list in [CHANGELOG.md](CHANGELOG.md).

## Installation

This plugin is available for installation via [Composer](http://getcomposer.org/).

```bash
composer require winter/wn-blocks-plugin
```

After installing the plugin you will need to run the migrations and (if you are using a [public folder](https://wintercms.com/docs/develop/docs/setup/configuration#using-a-public-folder)) [republish your public directory](https://wintercms.com/docs/develop/docs/console/setup-maintenance#mirror-public-files).

```bash
php artisan migrate
```

>**NOTE:** In order to have the `actions` support function correctly, you need to load `/plugins/winter/blocks/assets/dist/js/blocks.js` after the Snowboard framework has been loaded.

## Core Concepts

### Blocks

This plugin manages the concept of "blocks" in Winter CMS. Blocks are self contained pieces of structured content that can be managed and rendered in a variety of ways.

Blocks can be provided by both plugins and themes and can be overridden by themes.

### Actions

This plugin also introduces the concepts of "actions"; a way to define and execute client side actions that can be triggered by various events. Currently, actions are only defined in the `$/winter/blocks/meta/actions.yaml` file and must exist as a function on the `window.actions` object in the frontend keyed by the action's identifier that receives the `data` object as the first argument and (optionally) the `event` object that triggered the action as the second argument.

>**NOTE:** This is very much a WIP API and is subject to change. Feedback very much welcome here for ideas around how to register, manage, extend, and provide actions to the frontend.

### Tags

Blocks may have one or more tags, which is a way of defining and grouping blocks. For example, you may have a Gallery block which allows only "image" tagged blocks to be used, or a container block which allows all "content" tagged blocks but does not allow another "container" tagged block within.

Tags are defined in the blocks, and can be used to filter the available blocks in the Blocks form widget.


## Registering Blocks

Themes can have their blocks automatically registered by placing `.block` files in the `/blocks` folder and subfolders.

Plugins can register blocks by providing a `registerBlocks()` method in their Plugin.php file. The method should return an array of block definitions in the following format:

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


## Block Definition

Blocks are defined as `.block` files that consist of 2 to 3 parts:

- A YAML configuration section that defines the block's name, description, and other metadata as well as the block's properties and the form used to edit those properties.
- A PHP code section that allows for basic code to be executed when the block is rendered, similar to a partial.
- A Twig template section that defines the HTML markup template of the block.

When there are two parts, they are the Settings (YAML) & Markup (Twig) sections.

The following property values (name, description, etc) can be defined in the Settings (YAML) section of the `.block` files:

```yaml
name: Example
description: Example Block Description
icon: icon-name
tags: [] # Defines the tags that this block is associated with
permissions: [] # List of permissions required to interact with the block
fields: # The form fields used to populate the block's content
config: # The block configuration options
```

Blocks can use components in them, although they may face lifecycle limitations with complex AJAX handlers similar to component support in partials.

### Fields and Configuration

Blocks may define both `fields` as well as a `config` property in the Settings. Both of these parameters accept a [form schema](https://wintercms.com/docs/backend/forms#form-fields), but serve different purposes. In general, `fields` should contain the fields that actually fill in the content of the block, whereas the `config` should contain the fields that define the appearance or structure of the block itself. Fields are displayed within the block in the `blocks` form widget and configuration is displayed in an Inspector which can be shown by clicking on the "cogwheel" icon of a block in the `blocks` form widget.

For example, let's say you have a **Title** block which can display a heading tag in your content. You may optionally want to align it to left, center or right, and define which heading tag to use. The best practice would be to have a `content` field in the `fields` definition, because it's the actual content being displayed. The `alignment` and `tag` would become part of the `config` configuration.

**Example:**

```
name: Title
description: Adds a title
icon: icon-heading
tags: ["content"]
fields:
    content:
        label: false
        span: full
        type: text
config:
    size:
        label: Size
        span: auto
        type: dropdown
        default: h2
        options:
            h1: H1
            h2: H2
            h3: H3
            h4: H4
            h5: H5
    alignment_x:
        label: Alignment
        span: auto
        type: dropdown
        default: center
        options:
            left: Left
            center: Centre
            right: Right
==
{% if config.alignment_x == 'left' %}
    {% set alignment = 'text-left' %}
{% elseif config.alignment_x == 'center' or not config.alignment_x %}
    {% set alignment = 'text-center' %}
{% elseif config.alignment_x == 'right' %}
    {% set alignment = 'text-right' %}
{% endif %}

<{{ config.size }} class="{{ alignment }}">
    {{ content }}
</{{ config.size }}>
```

## Collapsible Sections

Block fields that use `type: section` can be made collapsible directly in the block YAML.

```yaml
fields:
    section_advanced:
        label: Advanced settings
        type: section
        collapsible: true        # makes the section click-to-collapse
        collapsed: true          # initial state: true = start collapsed (default), false = start open
    some_field:
        label: Some field
        type: text
```

**Shorthand rules:**

| Key | Type | Default | Description |
|---|---|---|---|
| `collapsible` | bool | — | Set to `true` to enable the collapse toggle |
| `collapsed` | bool | `true` | Initial state. `false` = section starts open |

When `collapsible: true` is set, the section header becomes a click target. Sections start collapsed by default; set `collapsed: false` to have the section open on first load. Each section's open/closed state is **remembered across page reloads** (stored in `localStorage`, keyed by field name), so the editor returns to the state you left it in.

> **Note:** Collapsible behaviour is handled via the `data-block-collapsible` attribute, bootstrapped inline in the block widget partial (`formwidgets/blocks/partials/_block.php`), independent of WinterCMS's core collapsible-section JS. This is deliberate: core re-collapses and re-binds every section on each form-widget init — including when a nested repeater adds an item — which broke manually-opened sections and stalled repeater "Add item" clicks. Owning the behaviour avoids that entirely, so collapsible sections work correctly even with repeater fields nested inside them.

---

## Tabs

Block definitions can include `tabs` and/or `secondaryTabs` at the top level. These are passed through to the WinterCMS `Backend\Widgets\Form` widget exactly as they would be in a standard `fields.yaml` file.

```yaml
name: My Block
description: A block with tabs
icon: icon-th

tabs:
    cssClass: master-tabs
    fields:
        content:
            label: Content
            type: textarea
            tab: Content

        title:
            label: Title
            type: text
            tab: Content

        meta_title:
            label: Meta title
            type: text
            tab: SEO

        meta_description:
            label: Meta description
            type: textarea
            tab: SEO

secondaryTabs:
    fields:
        is_active:
            label: Active
            type: checkbox
            tab: Settings
==
<div>{{ content }}</div>
```

Fields declared under `tabs` / `secondaryTabs` are placed in the tabbed area of the form widget. You can combine `tabs`, `secondaryTabs`, and the top-level `fields` array in the same block.

---

## Including shared field definitions

To avoid repeating the same fields (or sections/tabs) across many blocks, a block can pull them in from one or more external YAML files via the top-level `include` key.

```yaml
name: Article
description: An article block
icon: icon-newspaper

include: $/myauthor/myplugin/blocks/_seo.yaml

fields:
    title:
        label: Title
        type: text
==
<article>{{ title }}</article>
```

`_seo.yaml` is a plain YAML file (no `==` markup, no block metadata) containing any of `fields`, `tabs`, `secondaryTabs`, or `config`:

```yaml
# blocks/_seo.yaml
tabs:
    fields:
        meta_title:
            label: Meta title
            type: text
            tab: SEO
        meta_description:
            label: Meta description
            type: textarea
            tab: SEO
```

**Multiple includes** — pass a list; they are merged in order:

```yaml
include:
    - $/myauthor/myplugin/blocks/_seo.yaml
    - ~/app/blocks/_tracking.yaml
```

**Merge rules:**

| | |
|---|---|
| Merged keys | `fields`, `tabs`, `secondaryTabs`, `config` |
| Precedence | Included files form the base; the block's own definitions **override** on key collision |
| Order | Multiple includes merge top-to-bottom (later files override earlier ones, the block still wins overall) |
| Nested includes | An included file may itself declare `include:` — resolved recursively, with a circular-reference guard |
| Type guard | Redefining a field with a different `type` than the include logs a warning |
| Missing files | Skipped, and logged as a warning |

**Path resolution** uses the standard Winter path symbols via `File::symbolizePath()`:

| Symbol | Resolves to |
|---|---|
| `$/author/plugin/...` | `plugins/author/plugin/...` |
| `~/...` | application root |
| `#/...` | `storage/app/...` |

This works for `collapsible` sections and tabs too — an included file can define a complete collapsible section (or a whole tab group) that every block reuses without copy-paste.

---

## Recently used blocks

When adding a block from the palette, the blocks you use most recently are pinned to the top of the list (tracked per browser in `localStorage`, most-recent first). This speeds up repetitive content building where the same few block types are added over and over. No configuration is required.

---

## Cut, paste and duplicate blocks

Every block has a single horizontal toolbar (top-right) with, in order:
**collapse**, **copy**, **cut**, **paste**, **duplicate**, *(config, if the
block has an inspector)* and **delete**.

- **Copy** — places the block's field values on the clipboard (non-destructive).
- **Cut** — places the block's field values on the clipboard, then removes it (with the usual confirmation prompt).
- **Paste after** — once the clipboard holds a block, a per-block paste icon inserts the copied block immediately **after** that block. A **Paste block** entry also appears at the top of the "Add Block" palette (the popover opened by *+ Add New Item*), which is handy for inserting into an empty widget or at the end of a list.
- **Duplicate** — one-step clone: serialises the current block, saves it to the clipboard, and immediately inserts a filled copy right after it.

Use **Duplicate** for a quick in-place clone. Use **Paste after** (or **Paste block** in the palette) when you want to insert a previously copied block at a specific position or into a different widget.

Paste/duplicate respect the widget's `allow` / `ignore` / `tags` constraints: the paste affordances only appear where the copied block type is actually offered. The clipboard persists for the duration of the browser session (`sessionStorage`), so you can paste across different pages in the same tab.

> **Note on nested blocks:** Field values — including nested `blocks` fields, which store their content as JSON — are captured and restored correctly. Complex editor widgets (e.g. rich-text, code editors) may require an extra click to refresh their UI after paste, as those widgets listen for native `change` events.

---

## Using the `blocks` FormWidget

In order to provide an interface for managing block-based content, this plugin provides the `blocks` FormWidget. This widget can be used in the backend as a form field to manage blocks.

The `blocks` FormWidget supports the following additional properties:

- `allow`: An array of block types that are allowed to be added to the widget. If specified, only those block types listed will be available to add to the current instance of the field. You can define either a straight array of individual blocks to allow, or define an object with `tags` and/or `blocks` to allow whole tags or individual blocks.
- `ignore`: A list of block types that are not allowed to be added to the widget. If not specified, all block types will be available to add to the current instance of the field. You can define either a straight array of individual blocks to ignore, or define an object with `tags` and/or `blocks` to ignore whole tags or individual blocks.
- `tags`: A list of block tags that are allowed to be added to the widget. If specified, only block types that have at least one of the listed tags will be available to add to the current instance of the field.

Those properties allow you to limit the block types that can be added to a specific instance of the widget, which can be very helpful when building "container" type blocks that need to avoid including themselves or only support a specific set of blocks as "children".

### Examples

The `button_group` block type only allows a `button` block to be added to it:

```yaml
buttons:
    label: Buttons
    span: full
    type: blocks
    allow:
        - button
```

The `container` block type allows any block called `title`, or has a tag of `content`, to be added to it:

```yaml
container:
    label: Container
    span: full
    type: blocks
    allow:
        blocks:
            - title
        tags:
            - content
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
{variable type="blocks" name="blocks" tags="pages" tab="winter.pages::lang.editor.content"}{/variable}
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
        {{ renderBlock(block) }}
    {% else %}
        <section class="flex flex-wrap items-center mx-auto max-w-screen-xl">
            <div class="w-full p-4">
                {{ renderBlock(block) }}
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
