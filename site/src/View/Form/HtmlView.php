<?php
/**
 * @package     Moloch
 * @subpackage  Site.View
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Moloch\Component\Moloch\Site\View\Form;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * View class for Moloch issue form
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form object
     *
     * @var \Joomla\CMS\Form\Form
     */
    protected $form;

    /**
     * The model state
     *
     * @var \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * The item being edited
     *
     * @var object
     */
    protected $item;

    /**
     * The component parameters
     *
     * @var \Joomla\Registry\Registry
     */
    protected $params;

    /**
     * The categories
     *
     * @var array
     */
    protected $categories;

    /**
     * The workflow steps
     *
     * @var array
     */
    protected $steps;

    /**
     * Can edit flag
     *
     * @var boolean
     */
    protected $canEdit = false;

    /**
     * Can edit state flag
     *
     * @var boolean
     */
    protected $canEditState = false;

    /**
     * Method to display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  mixed
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        // Get data from the model
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $this->state = $this->get('State');
        $this->categories = $this->get('Categories');
        $this->steps = $this->get('Steps');

        // Get component parameters
        $this->params = ComponentHelper::getParams('com_moloch');

        // Check permissions
        $this->checkPermissions();

        // Check for errors
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        // Prepare document
        $this->prepareDocument();

        // Add assets
        $this->addAssets();

        parent::display($tpl);
    }

    /**
     * Check user permissions
     *
     * @return  void
     */
    protected function checkPermissions()
    {
        $user = Factory::getUser();
        $app = Factory::getApplication();

        // Check if this is an existing item
        $isNew = empty($this->item->id);

        if ($isNew) {
            // Check create permissions
            if ($user->guest && !$this->params->get('guest_submissions', 1)) {
                $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_LOGIN_REQUIRED'), 'error');
                $app->redirect(Route::_('index.php?option=com_users&view=login'));
                return;
            }

            $this->canEdit = $user->authorise('core.create', 'com_moloch') || 
                           ($user->guest && $this->params->get('guest_submissions', 1));
        } else {
            // Check edit permissions for existing item
            $this->canEdit = $user->authorise('core.edit', 'com_moloch') ||
                           ($user->authorise('core.edit.own', 'com_moloch') && $this->item->created_by == $user->id);

            if (!$this->canEdit) {
                $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_EDIT_NOT_PERMITTED'), 'error');
                $app->redirect(Route::_('index.php?option=com_moloch&view=issue&id=' . $this->item->id));
                return;
            }
        }

        // Check edit state permissions
        $this->canEditState = $user->authorise('core.edit.state', 'com_moloch');
    }

    /**
     * Prepare the document
     *
     * @return  void
     */
    protected function prepareDocument()
    {
        $app = Factory::getApplication();
        $isNew = empty($this->item->id);

        // Set page title
        $title = $isNew ? 
            Text::_('COM_MOLOCH_NEW_ISSUE') : 
            Text::sprintf('COM_MOLOCH_EDIT_ISSUE_TITLE', $this->item->title);

        $this->document->setTitle($title);

        // Add breadcrumbs
        $pathway = $app->getPathway();
        
        if (!$isNew && $this->item->title) {
            $pathway->addItem($this->item->title, Route::_('index.php?option=com_moloch&view=issue&id=' . $this->item->id));
        }
        
        $pathway->addItem($isNew ? Text::_('COM_MOLOCH_NEW_ISSUE') : Text::_('COM_MOLOCH_EDIT'));

        // Meta description
        if (!$isNew && $this->item->description) {
            $description = HTMLHelper::_('string.truncate', strip_tags($this->item->description), 160);
            $this->document->setDescription($description);
        }

        // Canonical URL
        if (!$isNew) {
            $this->document->addHeadLink(
                Route::_('index.php?option=com_moloch&view=issue&id=' . $this->item->id, false, 0, true),
                'canonical'
            );
        }
    }

    /**
     * Add component assets
     *
     * @return  void
     */
    protected function addAssets()
    {
        // Add CSS
        HTMLHelper::_('stylesheet', 'com_moloch/moloch.css', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('stylesheet', 'com_moloch/form.css', ['version' => 'auto', 'relative' => true]);

        // Add JavaScript
        HTMLHelper::_('script', 'com_moloch/moloch.js', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('script', 'com_moloch/form.js', ['version' => 'auto', 'relative' => true]);

        // Load map assets
        $this->loadMapAssets();

        // Form validation
        HTMLHelper::_('behavior.formvalidator');
        HTMLHelper::_('behavior.keepalive');

        // File upload
        HTMLHelper::_('script', 'com_moloch/file-upload.js', ['version' => 'auto', 'relative' => true]);

        // Add configuration to JavaScript
        $this->addJavaScriptConfig();
    }

    /**
     * Load map assets
     *
     * @return  void
     */
    protected function loadMapAssets()
    {
        $mapProvider = $this->params->get('map_provider', 'googlemaps');

        if ($mapProvider === 'googlemaps') {
            $apiKey = $this->params->get('google_maps_api_key', '');
            if (!empty($apiKey)) {
                $this->document->addScript(
                    'https://maps.googleapis.com/maps/api/js?key=' . $apiKey . '&libraries=geometry,places&callback=initFormMap',
                    ['defer' => true, 'async' => true]
                );
            }
        } else {
            // OpenStreetMap with Leaflet
            $this->document->addStyleSheet('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            $this->document->addScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
        }
    }

    /**
     * Add JavaScript configuration
     *
     * @return  void
     */
    protected function addJavaScriptConfig()
    {
        $config = [
            'mapProvider' => $this->params->get('map_provider', 'googlemaps'),
            'defaultLat' => (float) $this->params->get('default_latitude', 20.6296),
            'defaultLng' => (float) $this->params->get('default_longitude', -87.0739),
            'defaultZoom' => (int) $this->params->get('default_zoom', 12),
            'maxFileSize' => (int) $this->params->get('max_file_size', 1073741824),
            'allowedFileTypes' => explode(',', $this->params->get('allowed_file_types', 'jpg,jpeg,png,gif,pdf')),
            'item' => $this->item ? [
                'id' => $this->item->id,
                'latitude' => $this->item->latitude ? (float) $this->item->latitude : null,
                'longitude' => $this->item->longitude ? (float) $this->item->longitude : null,
                'address' => $this->item->address
            ] : null,
            'texts' => [
                'loading' => Text::_('COM_MOLOCH_LOADING'),
                'selectLocation' => Text::_('COM_MOLOCH_SELECT_LOCATION'),
                'dragMarker' => Text::_('COM_MOLOCH_DRAG_MARKER'),
                'searchAddress' => Text::_('COM_MOLOCH_SEARCH_ADDRESS'),
                'locationFound' => Text::_('COM_MOLOCH_LOCATION_FOUND'),
                'locationNotFound' => Text::_('COM_MOLOCH_LOCATION_NOT_FOUND'),
                'fileTooLarge' => Text::_('COM_MOLOCH_ERROR_FILE_TOO_LARGE'),
                'fileTypeNotAllowed' => Text::_('COM_MOLOCH_ERROR_FILE_TYPE'),
                'uploadError' => Text::_('COM_MOLOCH_ERROR_UPLOAD_FAILED'),
                'maxFiles' => Text::_('COM_MOLOCH_ERROR_MAX_FILES')
            ]
        ];

        $this->document->addScriptDeclaration(
            'window.MolochFormConfig = ' . json_encode($config) . ';'
        );

        // Add CSRF token
        $this->document->addScriptDeclaration(
            'window.MolochToken = "' . Factory::getSession()->getFormToken() . '";'
        );
    }

    /**
     * Get the form action URL
     *
     * @return  string
     */
    public function getFormAction()
    {
        $id = $this->item->id ?? 0;
        return Route::_('index.php?option=com_moloch&view=form&layout=edit&id=' . $id);
    }

    /**
     * Get the return URL
     *
     * @return  string
     */
    public function getReturnUrl()
    {
        $return = Factory::getApplication()->input->get('return', '', 'base64');
        
        if (!empty($return)) {
            return base64_decode($return);
        }

        // Default return URL
        if ($this->item->id) {
            return Route::_('index.php?option=com_moloch&view=issue&id=' . $this->item->id);
        }

        return Route::_('index.php?option=com_moloch&view=issues');
    }

    /**
     * Check if this is a new item
     *
     * @return  boolean
     */
    public function isNew()
    {
        return empty($this->item->id);
    }

    /**
     * Check if user can edit this item
     *
     * @return  boolean
     */
    public function canEdit()
    {
        return $this->canEdit;
    }

    /**
     * Check if user can edit state
     *
     * @return  boolean
     */
    public function canEditState()
    {
        return $this->canEditState;
    }

    /**
     * Get allowed file types as formatted string
     *
     * @return  string
     */
    public function getAllowedFileTypesString()
    {
        $types = explode(',', $this->params->get('allowed_file_types', ''));
        return implode(', ', array_map('strtoupper', $types));
    }

    /**
     * Get max file size formatted
     *
     * @return  string
     */
    public function getMaxFileSizeFormatted()
    {
        $bytes = $this->params->get('max_file_size', 1073741824);
        
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    /**
     * Get existing attachments
     *
     * @return  array
     */
    public function getAttachments()
    {
        if (!$this->item->id) {
            return [];
        }

        return $this->item->attachments ?? [];
    }
}