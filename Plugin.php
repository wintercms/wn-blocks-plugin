<?php

namespace Winter\Blocks;

use Backend\Classes\NavigationManager;
use Backend\Classes\WidgetManager;
use Backend\Facades\Backend;
use Backend\Models\UserRole;
use Cms\Classes\AutoDatasource;
use Cms\Classes\Theme;
use System\Classes\PluginBase;
use Winter\Blocks\Classes\Block as BlockModel;
use Winter\Blocks\Classes\BlockManager;
use Winter\Blocks\Classes\BlocksDatasource;
use Winter\Blocks\FormWidgets\Block;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Support\Facades\Event;

/**
 * Blocks Plugin Information File
 *
 * @TODO:
 * - Review https://octobercms.com/plugin/reazzon-editor
 *
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'winter.blocks::lang.plugin.name',
            'description' => 'winter.blocks::lang.plugin.description',
            'author'      => 'Winter CMS',
            'icon'        => 'icon-cubes',
        ];
    }

    /**
     * Registers the custom Blocks provided by this plugin
     */
    public function registerBlocks(): array
    {
        return [
            'button' => '$/winter/blocks/blocks/button.block',
            'button_group' => '$/winter/blocks/blocks/button_group.block',
            'cards' => '$/winter/blocks/blocks/cards.block',
            'code' => '$/winter/blocks/blocks/code.block',
            'columns_two' => '$/winter/blocks/blocks/columns_two.block',
            'divider' => '$/winter/blocks/blocks/divider.block',
            'image' => '$/winter/blocks/blocks/image.block',
            'plaintext' => '$/winter/blocks/blocks/plaintext.block',
            'richtext' => '$/winter/blocks/blocks/richtext.block',
            'title' => '$/winter/blocks/blocks/title.block',
            'video' => '$/winter/blocks/blocks/video.block',
            'vimeo' => '$/winter/blocks/blocks/vimeo.block',
            'youtube' => '$/winter/blocks/blocks/youtube.block',
        ];
    }

    /**
     * Registers the custom FormWidgets provided by this plugin
     */
    public function registerFormWidgets(): array
    {
        return [
            \Winter\Blocks\FormWidgets\Blocks::class => 'blocks'
        ];
    }

    /**
     * Registers the custom twig markups provided by this plugin
     */
    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'renderBlock' => [
                    function (array $context, string|array $block, array $data = []) {
                        return BlockModel::render(
                            $block,
                            $data,
                            $context['this']['controller'] ?? null
                        );
                    },
                    'options' => ['needs_context' => true]
                ],
                'renderBlocks' => [
                    function (array $context, array $blocks) {
                        return BlockModel::renderAll(
                            $blocks,
                            $context['this']['controller'] ?? null
                        );
                    },
                    'options' => ['needs_context' => true]
                ],
            ],
        ];
    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {
        $this->registerAssets();
        $this->extendThemeDatasource();
        $this->extendControlLibraryBlocks();

        if ($this->app->runningInBackend() && in_array('Cms', Config::get('cms.loadModules'))) {
            $this->extendCms();
        }
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'winter.blocks.manage_blocks' => [
                'tab'   => 'winter.blocks::lang.plugin.name',
                'order' => 200,
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
                'label' => 'winter.blocks::lang.blocks.manage_blocks'
            ],
        ];
    }

    /**
     * Register asset bundles for compilation
     */
    protected function registerAssets(): void
    {
        \System\Classes\CombineAssets::registerCallback(function ($combiner) {
            $combiner->registerBundle('$/winter/blocks/formwidgets/blocks/assets/less/blocks.less');
        });
    }

    /**
     * Extend the theme's datasource to include the BlocksDatasource for loading blocks from
     */
    protected function extendThemeDatasource(): void
    {
        // Register the block manager instance
        BlockManager::instance();
        Event::listen('cms.theme.registerHalcyonDatasource', function (Theme $theme, $resolver) {
            $source = $theme->getDatasource();
            if ($source instanceof AutoDatasource) {
                /* @var AutoDatasource $source */
                $source->appendDatasource('blocks', new BlocksDatasource());
                return;
            } else {
                $resolver->addDatasource($theme->getDirName(), new AutoDatasource([
                    'theme' => $source,
                    'blocks' => new BlocksDatasource(),
                ], 'blocks-autodatasource'));
            }
        });
    }

    /**
     * Extend the ControlLibrary provided by Winter.Builder to register blocks as Form Controls
     */
    protected function extendControlLibraryBlocks(): void
    {
        // Register blocks as custom controls
        Event::listen('pages.builder.registerControls', function (\Winter\Builder\Classes\ControlLibrary $controlLibrary) {
            foreach (BlockManager::instance()->getConfigs('forms') as $key => $config) {
                // Map custom fields into standard properties, while ignoring irrelevant properties
                $properties = $controlLibrary->getStandardProperties([
                    'label', 'required', 'comment', 'placeholder', 'default', 'defaultFrom', 'stretch'
                ], array_combine(
                    array_map(
                        fn($field) => sprintf('data[%s]', $field),
                        array_keys($config['fields'] ?? [])
                    ),
                    array_values(
                        array_map(
                            fn ($field) => array_merge($field, [
                                'title' => $field['label'] ?? '',
                                'tab' => 'Field Options'
                            ]),
                            $config['fields'] ?? []
                        )
                    )
                ));

                // Sort custom fields to the top
                uksort($properties, fn ($a, $b) => str_contains($key, 'data[') ? 1 : $a <=> $b);

                $controlLibrary->registerControl(
                    Block::TYPE_PREFIX . $key,
                    $config['name'],
                    $config['description'],
                    Block::GROUP_BLOCKS,
                    $config['icon'],
                    $properties,
                    null
                );
            }
        }, PHP_INT_MIN);

        // Register a Winter\Blocks\FormWidgets\Block FormWidget under each block's key
        WidgetManager::instance()->registerFormWidgets(function ($manager) {
            foreach (BlockManager::instance()->getConfigs() as $key => $config) {
                $manager->registerFormWidget(Block::class, Block::TYPE_PREFIX . $key);
            }
        });
    }

    /**
     * Extend the CMS to implement the BlocksController as a child of the CMS
     */
    public function extendCms(): void
    {
        Event::listen('backend.menu.extendItems', function (NavigationManager $manager) {
            $manager->addSideMenuItem('winter.cms', 'cms', 'blocks', [
                'label'       => 'winter.blocks::lang.plugin.name',
                'icon'        => 'icon-cubes',
                'url'         => Backend::url('winter/blocks/blockscontroller'),
                // TODO: Make good
                'attributes'  => 'onclick="window.location.href = this.querySelector(\'a\').href;"',
                'permissions' => ['winter.blocks.manage_blocks']
            ]);
        });
    }
}
