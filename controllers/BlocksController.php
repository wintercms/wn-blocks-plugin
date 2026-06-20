<?php

namespace Winter\Blocks\Controllers;

use Backend\Classes\Controller;
use Backend\Classes\NavigationManager;
use Backend\Facades\Backend;
use Backend\Facades\BackendMenu;
use Cms\Classes\Theme;
use Cms\Controllers\Index as CmsIndexController;
use Cms\Widgets\TemplateList;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Request;
use Winter\Blocks\Classes\Block;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Support\Facades\Config;
use Winter\Storm\Support\Facades\Flash;

/**
 * Blocks Controller Backend Controller
 */
class BlocksController extends CmsIndexController
{
    protected $theme;

    /**
     * @var array Permissions required to view this page.
     */
    public $requiredPermissions = [
        'blocks.manage_blocks',
    ];

    public function __construct()
    {
        Controller::__construct();

        BackendMenu::setContext('Winter.Cms', 'cms', 'blocks');

        try {
            if (!($theme = Theme::getEditTheme())) {
                throw new ApplicationException(Lang::get('cms::lang.theme.edit.not_found'));
            }

            $this->theme = $theme;

            new TemplateList($this, 'blockList', function () use ($theme) {
                return Block::listInTheme($theme, true);
            });
        } catch (\Exception $ex) {
            $this->handleError($ex);
        }

        // Dynamically re-write the cms menu item urls to allow the user to return back to those pages
        BackendMenu::registerCallback(function (NavigationManager $navigationManager) {
            foreach ($navigationManager->getMainMenuItem('Winter.Cms', 'cms')->sideMenu as $menuItem) {
                if ($menuItem->url === 'javascript:;') {
                    $menuItem->url = Backend::url('cms#' . $menuItem->code);
                    $menuItem->attributes = 'onclick="window.location.href = this.querySelector(\'a\').href;"';
                }
            }
        });
    }

    /**
     * Index page action
     * @return void
     */
    public function index()
    {
        parent::index();
        $this->addJs('/plugins/winter/blocks/assets/dist/js/winter.cmspage.extension.js', 'core');
    }

    /**
     * Resolves a template type to its class name
     * @param string $type
     * @return string
     */
    protected function resolveTypeClassName($type)
    {
        if ($type !== 'block') {
            throw new ApplicationException(Lang::get('cms::lang.template.invalid_type'));
        }

        return Block::class;
    }

    /**
     * Returns the text for a template tab
     * @param string $type
     * @param string $template
     * @return string
     */
    protected function getTabTitle($type, $template)
    {
        if ($type !== 'block') {
            throw new ApplicationException(Lang::get('cms::lang.template.invalid_type'));
        }

        return $template->getFileName() ?? Lang::get('winter.blocks::lang.editor.new');
    }

    /**
     * Returns a form widget for a specified template type.
     * @param string $type
     * @param string $template
     * @param string $alias
     * @return Backend\Widgets\Form
     */
    protected function makeTemplateFormWidget($type, $template, $alias = null)
    {
        if ($type !== 'block') {
            throw new ApplicationException(Lang::get('cms::lang.template.not_found'));
        }

        $formConfig = '~/plugins/winter/blocks/controllers/blockscontroller/block_fields.yaml';

        $widgetConfig = $this->makeConfig($formConfig);

        $ext = pathinfo($template->fileName, PATHINFO_EXTENSION);
        if ($type === 'content') {
            switch ($ext) {
                case 'htm':
                    $type = 'richeditor';
                    break;
                case 'md':
                    $type = 'markdown';
                    break;
                default:
                    $type = 'codeeditor';
                    break;
            }
            array_set($widgetConfig->secondaryTabs, 'fields.markup.type', $type);
        }

        $lang = 'php';
        if (array_get($widgetConfig->secondaryTabs, 'fields.markup.type') === 'codeeditor') {
            switch ($ext) {
                case 'htm':
                    $lang = 'twig';
                    break;
                case 'html':
                    $lang = 'html';
                    break;
                case 'css':
                    $lang = 'css';
                    break;
                case 'js':
                case 'json':
                    $lang = 'javascript';
                    break;
            }
        }

        $widgetConfig->model = $template;
        $widgetConfig->alias = $alias ?: 'form'.studly_case($type).md5($template->exists ? $template->getFileName() : uniqid());

        return $this->makeWidget('Backend\Widgets\Form', $widgetConfig);
    }

    /**
     * Saves the template currently open
     * @return array
     */
    public function onSave()
    {
        $this->validateRequestTheme();
        $type = Request::input('templateType');
        $templatePath = trim(Request::input('templatePath'));
        $template = $templatePath ? $this->loadTemplate($type, $templatePath) : $this->createTemplate($type);
        $formWidget = $this->makeTemplateFormWidget($type, $template);

        $saveData = $formWidget->getSaveData();
        $postData = post();
        $templateData = [];

        $settings = array_get($saveData, 'settings', []) + Request::input('settings', []);
        $settings = $this->upgradeSettings($settings, $template->settings);

        if ($settings) {
            $templateData['settings'] = $settings;
        }

        $fields = ['markup', 'code', 'fileName', 'content', 'yaml'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $saveData)) {
                $templateData[$field] = $saveData[$field];
            }
            elseif (array_key_exists($field, $postData)) {
                $templateData[$field] = $postData[$field];
            }
        }

        if (!empty($templateData['markup']) && Config::get('cms.convertLineEndings', false) === true) {
            $templateData['markup'] = $this->convertLineEndings($templateData['markup']);
        }

        if (!empty($templateData['code']) && Config::get('cms.convertLineEndings', false) === true) {
            $templateData['code'] = $this->convertLineEndings($templateData['code']);
        }

        if (
            !Request::input('templateForceSave') && $template->mtime
            && Request::input('templateMtime') != $template->mtime
        ) {
            throw new ApplicationException('mtime-mismatch');
        }

        $template->attributes = [];
        $template->fill($templateData);

        $template->save();

        /**
         * @event cms.template.save
         * Fires after a CMS template (page|partial|layout|content|asset) has been saved.
         *
         * Example usage:
         *
         *     Event::listen('cms.template.save', function ((\Cms\Controllers\Index) $controller, (mixed) $templateObject, (string) $type) {
         *         \Log::info("A $type has been saved");
         *     });
         *
         * Or
         *
         *     $CmsIndexController->bindEvent('template.save', function ((mixed) $templateObject, (string) $type) {
         *         \Log::info("A $type has been saved");
         *     });
         *
         */
        $this->fireSystemEvent('cms.template.save', [$template, $type]);

        Flash::success(Lang::get('cms::lang.template.saved'));

        return $this->getUpdateResponse($template, $type);
    }
}
