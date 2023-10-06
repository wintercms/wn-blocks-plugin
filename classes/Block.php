<?php

namespace Winter\Blocks\Classes;

use Cms\Classes\CmsCompoundObject;
use Cms\Classes\CmsException;
use Cms\Classes\CodeParser;
use Cms\Classes\ComponentManager;
use Cms\Classes\Controller;
use Cms\Classes\PartialStack;
use Lang;
use Winter\Storm\Exception\SystemException;

/**
 * The Block class.
 */
class Block extends CmsCompoundObject
{
    /**
     * The container name associated with the model, eg: pages.
     */
    protected $dirName = 'blocks';

    /**
     * @var array Allowable file extensions.
     */
    protected $allowedExtensions = ['block'];

    protected PartialStack $partialStack;

    public function __construct(array $attributes = [])
    {
        $this->partialStack = new PartialStack();
        parent::__construct($attributes);
    }

    /**
     * Renders the provided block
     */
    public static function render(string|array $block, array $data = [], ?Controller $controller = null): string
    {
        if (!$controller) {
            $controller = new Controller();
        }

        if (is_array($block)) {
            $data = $block;
            $block = $data['_group'] ?? false;
        }

        if (empty($block)) {
            throw new SystemException("The block name was not provided");
        }

        $partialData = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['_group', '_config'])) {
                continue;
            }

            $partialData[$key] = $value;
        }

        // Allow data to be accessed via "data" key, for backwards compatibility.
        $partialData['data'] = $partialData;

        if (!empty($data['_config'])) {
            $partialData['config'] = json_decode($data['_config']);
        } else {
            $partialData['config'] = static::getDefaultConfig($block);
        }

        return $controller->renderPartial($block . '.block', $partialData);
    }

    /**
     * Renders the provided blocks
     */
    public static function renderAll(array $blocks, ?Controller $controller = null): string
    {
        $content = '';
        $controller ??= (new Controller());

        foreach ($blocks as $i => $block) {
            if (!array_key_exists('_group', $block)) {
                throw new SystemException("The block definition at index $i must contain a `_group` key.");
            }

            $partialData = [];

            foreach ($block as $key => $value) {
                if (in_array($key, ['_group', '_config'])) {
                    continue;
                }

                $partialData[$key] = $value;
            }

            // Allow data to be accessed via "data" key, for backwards compatibility.
            $partialData['data'] = $partialData;

            if (!empty($block['_config'])) {
                $partialData['config'] = json_decode($block['_config']);
            } else {
                $partialData['config'] = static::getDefaultConfig($block['_group']);
            }

            $content .= $controller->renderPartial($block['_group'] . '.block', $partialData);
        }

        return $content;
    }

    /**
     * Returns name of a PHP class to us a parent for the PHP class created for the object's PHP section.
     */
    public function getCodeClassParent(): string
    {
        return BlockCode::class;
    }

    /**
     * Get a new query builder for the object
     * @return \Winter\Storm\Halcyon\Builder
     */
    public function newQuery()
    {
        $datasource = $this->getDatasource();

        $query = new BlockBuilder($datasource, new BlockProcessor());

        return $query->setModel($this);
    }

    /**
     * Execute the lifecycle of the partial manually. Usually this would only happen for cms partials (i.e. component
     * partials), but this method enables this functionality for blocks
     */
    public function executeLifecycle(Controller $controller): static
    {
        $this->partialStack->stackPartial();

        $manager = ComponentManager::instance();

        foreach ($this->components as $component => $properties) {
            // Do not inject the viewBag component to the environment.
            // Not sure if they're needed there by the requirements,
            // but there were problems with array-typed properties used by Static Pages
            // snippets and setComponentPropertiesFromParams(). --ab
            if ($component == 'viewBag') {
                continue;
            }

            list($name, $alias) = strpos($component, ' ')
                ? explode(' ', $component)
                : [$component, $component];

            if (!$componentObj = $manager->makeComponent($name, $this, $properties)) {
                throw new SystemException(Lang::get('cms::lang.component.not_found', ['name'=>$name]));
            }

            $componentObj->alias = $alias;
            $parameters[$alias] = $this->components[$alias] = $componentObj;

            $this->partialStack->addComponent($alias, $componentObj);

            $this->setComponentPropertiesFromParams($componentObj, $parameters);
            $componentObj->init();
        }

        CmsException::mask($this->page, 300);
        $parser = new CodeParser($this);
        $partialObj = $parser->source($controller->getPage(), $controller->getLayout(), $controller);
        CmsException::unmask();

        CmsException::mask($this, 300);
        $partialObj->onStart();
        $this->runComponents();
        $partialObj->onEnd();
        CmsException::unmask();

        return $this;
    }

    /**
     * Gets the default config for the provided block, if no user-defined config is available.
     */
    private static function getDefaultConfig(string $block): ?array
    {
        $config = BlockManager::instance()->getConfig($block);

        if (!array_key_exists('config', $config)) {
            return null;
        }

        $defaults = [];

        foreach ($config['config'] as $configKey => $configData) {
            $defaults[$configKey] = $configData['default'] ?? null;
        }

        return $defaults;
    }
}
