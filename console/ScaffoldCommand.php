<?php

namespace Winter\Blocks\Console;

use Backend;
use Cms\Classes\Layout;
use Cms\Classes\Theme;
use File;
use Illuminate\Console\Command;
use System\Classes\MediaLibrary;
use Winter\Pages\Classes\Page;

/**
 * Scaffolds Winter.Blocks demo content for local development and testing.
 *
 * Winter.Blocks has no backend UI, models, controllers or storage of its own: it
 * ships the `blocks` FormWidget (a Repeater subclass) plus a set of `.block`
 * definitions, and its content lives wherever a host embeds the widget. The
 * intended host is a Winter.Pages static page whose layout declares a
 * `{variable type="blocks" name="blocks"}` field — the widget then renders in the
 * page editor and its value is persisted in the page's `viewBag` (an INI section
 * in the page's theme `.htm` file).
 *
 * This command therefore scaffolds:
 *   - a demo layout ("scaffold-blocks") that declares the blocks variable, so the
 *     blocks editor actually appears in the page form;
 *   - a spread of static pages whose `blocks` viewBag is pre-populated with every
 *     shippable block type (title, richtext, plaintext, image, code, divider,
 *     button, button_group, cards, columns_two, video, youtube, vimeo), including
 *     one very long "kitchen sink" page and enough filler pages to fill the Pages
 *     tree;
 *   - a copy of a source image into the media library for image/video blocks.
 *
 * Mirrors the env-guarded, idempotent `scaffold:*` pattern used elsewhere. All
 * artefacts are prefixed `scaffold-blocks` so `--fresh` can scope its cleanup.
 */
class ScaffoldCommand extends Command
{
    protected $signature = 'scaffold:winter.blocks
        {--fresh : Delete any existing scaffold data before recreating it}';

    protected $description = 'Scaffold Winter.Blocks demo content (a blocks-enabled layout + Winter.Pages static pages exercising every block type) for local development/testing.';

    /**
     * Prefix applied to every scaffolded page file name and to the demo layout so
     * `--fresh` deletion (and the idempotency check) can be scoped to scaffold data.
     */
    const PREFIX = 'scaffold-blocks';

    /**
     * File name (sans extension) of the demo layout that declares the blocks field.
     */
    const LAYOUT = 'scaffold-blocks';

    /**
     * Media library file created for image/video blocks.
     */
    const MEDIA_IMAGE = '/scaffold-blocks-winter.png';

    public function handle(): int
    {
        // Never inject demo content into a production install.
        if ($this->getLaravel()->environment('production')) {
            $this->error('scaffold:winter.blocks cannot run in the production environment.');

            return self::FAILURE;
        }

        if (!class_exists(Page::class)) {
            $this->error('Winter.Pages is required to scaffold Blocks demo content (it hosts the blocks form widget). Install/enable winter/wn-pages-plugin and retry.');

            return self::FAILURE;
        }

        $theme = Theme::getEditTheme();
        if (!$theme) {
            $this->error('No editable theme is active; cannot scaffold static pages.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->deleteExisting($theme);
        }

        if ($this->scaffoldExists($theme)) {
            $this->warn('Blocks scaffold content already exists. Use --fresh to recreate it.');

            return self::SUCCESS;
        }

        $this->createLayout($theme);
        $this->copyMediaImage();

        $count = $this->createPages($theme);
        $this->info("Created demo layout '" . self::LAYOUT . "' and {$count} static page(s) with populated blocks.");

        $this->newLine();
        $this->line('Pages list:  ' . Backend::url('winter/pages'));
        $this->line('Edit a page: open the Pages list above and pick a "Scaffold Blocks" page to open the blocks editor.');

        return self::SUCCESS;
    }

    /**
     * Whether any scaffold pages already exist in the theme.
     */
    protected function scaffoldExists(Theme $theme): bool
    {
        foreach (Page::listInTheme($theme, true) as $page) {
            if (str_starts_with($page->getBaseFileName(), self::PREFIX)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove previously scaffolded pages, the demo layout and the copied media file.
     */
    protected function deleteExisting(Theme $theme): void
    {
        $removed = 0;
        foreach (Page::listInTheme($theme, true) as $page) {
            if (str_starts_with($page->getBaseFileName(), self::PREFIX)) {
                $page->delete();
                $removed++;
            }
        }

        $layout = Layout::load($theme, self::LAYOUT);
        if ($layout) {
            $layout->delete();
        }

        try {
            if (MediaLibrary::instance()->exists(self::MEDIA_IMAGE)) {
                MediaLibrary::instance()->deleteFiles([self::MEDIA_IMAGE]);
            }
        } catch (\Throwable $e) {
            // Best-effort; media may already be gone.
        }

        if ($removed > 0 || $layout) {
            $this->info("Removed {$removed} scaffold page(s) and the demo layout.");
        }
    }

    /**
     * Create the demo layout that declares a `blocks` (and helper `subtitle`)
     * variable in its markup body, so the Pages editor renders the blocks widget.
     */
    protected function createLayout(Theme $theme): void
    {
        $markup = <<<'TWIG'
{variable type="text" name="subtitle" label="Subtitle" tab="Content" placement="primary"}{/variable}
{variable type="blocks" name="blocks" tags="pages" tab="Content"}{/variable}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ this.page.title }}</title>
    {% styles %}
</head>
<body>
    <h1>{{ this.page.title }}</h1>
    {% if subtitle %}<p class="subtitle">{{ subtitle }}</p>{% endif %}
    {{ renderBlocks(blocks) }}
    {% page %}
    {% scripts %}
</body>
</html>
TWIG;

        $layout = Layout::load($theme, self::LAYOUT) ?: Layout::inTheme($theme);
        $layout->fileName = self::LAYOUT;
        $layout->settings = ['description' => 'Scaffold: Blocks demo layout'];
        $layout->markup = $markup;
        $layout->save();
    }

    /**
     * Copy a bundled image into the media library so image/video blocks resolve to
     * a real file. Guarded by File::exists; silently skips if no source is present.
     */
    protected function copyMediaImage(): void
    {
        $sources = [
            base_path('themes/demo/assets/images/winter.png'),
            base_path('themes/demo/assets/images/theme-preview.png'),
            base_path('modules/backend/assets/images/wordmark.png'),
        ];

        foreach ($sources as $source) {
            if (!File::exists($source)) {
                continue;
            }

            MediaLibrary::instance()->put(self::MEDIA_IMAGE, File::get($source));
            MediaLibrary::instance()->resetCache();

            return;
        }
    }

    /**
     * The media path referenced by image/video blocks. Falls back to an existing
     * media file if the copy above failed, and finally to an empty string.
     */
    protected function imagePath(): string
    {
        if (MediaLibrary::instance()->exists(self::MEDIA_IMAGE)) {
            return self::MEDIA_IMAGE;
        }

        foreach (['/sticker-1.png', '/Sticker.png'] as $candidate) {
            if (MediaLibrary::instance()->exists($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Create the spread of static pages. Returns the number of pages created.
     */
    protected function createPages(Theme $theme): int
    {
        $image = $this->imagePath();
        $count = 0;

        // 1. Kitchen-sink page: every block type, including nested/container blocks
        //    and deliberately long content, so the editor and every block field is
        //    exercised in one place.
        $this->makePage($theme, 'kitchen-sink', 'Scaffold Blocks: Kitchen Sink', [
            'subtitle' => 'Every shippable block type on a single, deliberately long page.',
            'blocks' => $this->kitchenSinkBlocks($image),
        ]);
        $count++;

        // 2. Long-content page: one very long richtext + many title/plaintext blocks
        //    to stress the blocks list height, scrolling and reordering.
        $this->makePage($theme, 'long-content', 'Scaffold Blocks: Long Content', [
            'subtitle' => 'A long stack of content blocks.',
            'blocks' => $this->longContentBlocks(),
        ]);
        $count++;

        // 3. Media page: image, video, youtube and vimeo blocks together.
        $this->makePage($theme, 'media', 'Scaffold Blocks: Media', [
            'subtitle' => 'Image and embedded-video blocks.',
            'blocks' => [
                $this->block('image', ['image' => $image, 'alt_text' => 'A scaffolded demo image', 'size' => 'w-2/3']),
                $this->block('video', ['video' => $image]),
                $this->block('youtube', ['youtube_id' => 'dQw4w9WgXcQ']),
                $this->block('vimeo', ['vimeo_id' => '76979871']),
            ],
        ]);
        $count++;

        // 4. Container page: columns_two, cards and button_group (blocks-in-blocks).
        $this->makePage($theme, 'containers', 'Scaffold Blocks: Containers', [
            'subtitle' => 'Container blocks that nest other blocks.',
            'blocks' => [
                $this->block('columns_two', [
                    'left' => [
                        $this->block('title', ['content' => 'Left column', 'size' => 'h3', 'alignment_x' => 'left']),
                        $this->block('richtext', ['content' => '<p>Left column rich text content.</p>']),
                    ],
                    'right' => [
                        $this->block('title', ['content' => 'Right column', 'size' => 'h3', 'alignment_x' => 'left']),
                        $this->block('plaintext', ['content' => 'Right column plain text content.']),
                    ],
                ]),
                $this->block('cards', [
                    'cards' => [
                        ['blocks' => [
                            $this->block('image', ['image' => $image, 'alt_text' => 'Card one', 'size' => 'w-full']),
                            $this->block('richtext', ['content' => '<p><strong>Card one</strong> body.</p>']),
                        ]],
                        ['blocks' => [
                            $this->block('richtext', ['content' => '<p><strong>Card two</strong> body, no image.</p>']),
                        ]],
                        ['blocks' => [
                            $this->block('richtext', ['content' => '<p><strong>Card three</strong> body.</p>']),
                            $this->block('button', $this->buttonConfig('Learn more')),
                        ]],
                    ],
                ]),
                $this->block('button_group', [
                    'position' => 'justify-center',
                    'width' => 'w-auto',
                    'buttons' => [
                        $this->block('button', $this->buttonConfig('Primary')),
                        $this->block('button', $this->buttonConfig('Secondary')),
                    ],
                ]),
            ],
        ]);
        $count++;

        // 5. Empty page: no blocks — exercises the widget's empty state.
        $this->makePage($theme, 'empty', 'Scaffold Blocks: Empty State', [
            'subtitle' => 'No blocks yet — shows the empty blocks editor.',
            'blocks' => [],
        ]);
        $count++;

        // 6-... Filler pages to populate the Pages tree/list.
        for ($i = 1; $i <= 12; $i++) {
            $this->makePage($theme, 'filler-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), "Scaffold Blocks: Sample Page {$i}", [
                'subtitle' => "Filler page #{$i}.",
                'blocks' => [
                    $this->block('title', ['content' => "Sample page {$i}", 'size' => 'h2', 'alignment_x' => 'center']),
                    $this->block('plaintext', ['content' => "Scaffolded filler content for sample page {$i}."]),
                    $this->block('divider'),
                    $this->block('richtext', ['content' => "<p>Filler rich text for page <strong>{$i}</strong>.</p>"]),
                ],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Persist a static page using the blocks-enabled demo layout.
     */
    protected function makePage(Theme $theme, string $slug, string $title, array $vars): Page
    {
        $fileName = self::PREFIX . '-' . $slug;

        $page = Page::load($theme, $fileName . '.htm') ?: Page::inTheme($theme);
        $page->fileName = $fileName;
        $page->fill([
            'settings' => [
                'viewBag' => array_merge([
                    'title' => $title,
                    'url' => '/' . $fileName,
                    'layout' => self::LAYOUT,
                    'is_hidden' => 0,
                    'navigation_hidden' => 0,
                ], $vars),
            ],
            'markup' => '',
        ]);
        $page->save();

        return $page;
    }

    /**
     * Build a single block entry: a `_group` key plus its field/config values,
     * matching the shape the blocks form widget stores and `renderBlock` expects.
     */
    protected function block(string $group, array $data = []): array
    {
        return array_merge(['_group' => $group], $data);
    }

    /**
     * Config payload for a button block (its fields live under a nestedform).
     */
    protected function buttonConfig(string $label): array
    {
        return [
            'config' => [
                'label' => $label,
                'color' => '#1f6feb',
                'actions' => [],
            ],
        ];
    }

    /**
     * The all-block-types payload for the kitchen-sink page.
     */
    protected function kitchenSinkBlocks(string $image): array
    {
        $long = 'This is a long paragraph of rich text used to give the blocks editor '
            . 'something substantial to render. ' . str_repeat(
                'It repeats a sentence several times so the block grows tall enough to '
                . 'exercise scrolling, wrapping and reordering within the blocks widget. ',
                6
            );

        return [
            $this->block('title', ['content' => 'The complete block showcase', 'size' => 'h2', 'alignment_x' => 'center']),
            $this->block('plaintext', ['content' => 'A short plain-text intro beneath the title.']),
            $this->block('divider'),
            $this->block('richtext', ['content' => '<p>' . $long . '</p><ul><li>One</li><li>Two</li><li>Three</li></ul>']),
            $this->block('image', ['image' => $image, 'alt_text' => 'Kitchen sink image', 'size' => 'w-1/2']),
            $this->block('code', ['content' => "<div class=\"note\">\n    <p>Some raw HTML code block.</p>\n</div>"]),
            $this->block('button', $this->buttonConfig('A call to action')),
            $this->block('button_group', [
                'position' => 'justify-start',
                'width' => 'w-auto',
                'buttons' => [
                    $this->block('button', $this->buttonConfig('First')),
                    $this->block('button', $this->buttonConfig('Second')),
                ],
            ]),
            $this->block('columns_two', [
                'left' => [$this->block('richtext', ['content' => '<p>Left side content.</p>'])],
                'right' => [$this->block('richtext', ['content' => '<p>Right side content.</p>'])],
            ]),
            $this->block('video', ['video' => $image]),
            $this->block('youtube', ['youtube_id' => 'dQw4w9WgXcQ']),
            $this->block('vimeo', ['vimeo_id' => '76979871']),
        ];
    }

    /**
     * A long vertical stack of content blocks for the long-content page.
     */
    protected function longContentBlocks(): array
    {
        $blocks = [
            $this->block('title', ['content' => 'A long stack of content', 'size' => 'h2', 'alignment_x' => 'left']),
        ];

        for ($i = 1; $i <= 15; $i++) {
            $blocks[] = $this->block('title', ['content' => "Section {$i}", 'size' => 'h3', 'alignment_x' => 'left']);
            $blocks[] = $this->block('richtext', [
                'content' => "<p>Body copy for <strong>section {$i}</strong>. "
                    . str_repeat('Some filler sentence to add length. ', 4) . '</p>',
            ]);
            $blocks[] = $this->block('divider');
        }

        return $blocks;
    }
}
