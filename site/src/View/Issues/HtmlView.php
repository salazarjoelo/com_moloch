<?php
/**
 * @package     Moloch
 * @subpackage  Site.View
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Moloch\Component\Moloch\Site\View\Issues;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;

/**
 * View class for a list of Moloch issues
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The model state
     *
     * @var \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * The list of issues
     *
     * @var array
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var \Joomla\CMS\Pagination\Pagination
     */
    protected $pagination;

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
     * Filter form
     *
     * @var \Joomla\CMS\Form\Form
     */
    public $filterForm;

    /**
     * Active filters
     *
     * @var array
     */
    public $activeFilters;

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
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        // Get component parameters
        $this->params = ComponentHelper::getParams('com_moloch');

        // Get categories and steps
        $this->categories = $this->get('Categories');
        $this->steps = $this->get('Steps');

        // Check for errors
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        // Prepare document
        $this->prepareDocument();

        // Load map assets
        $this->loadMapAssets();

        // Add component CSS and JavaScript
        $this->addAssets();

        parent::display($tpl);
    }

    /**
     * Prepare the document
     *
     * @return  void
     */
    protected function prepareDocument()
    {
        $app = Factory::getApplication();
        $menus = $app->getMenu();
        $pathway = $app->getPathway();
        $title = null;

        // Get menu item
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_MOLOCH_ISSUES_DEFAULT_PAGE_TITLE'));
        }

        $title = $this->params->get('page_title', '');

        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->document->setTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->document->setMetadata('robots', $this->params->get('robots'));
        }

        // Add Open Graph meta tags
        $this->document->setMetaData('og:title', $title);
        $this->document->setMetaData('og:type', 'website');
        $this->document->setMetaData('og:url', Route::_('index.php?option=com_moloch&view=issues', false, 0, true));
        
        if ($this->params->get('menu-meta_description')) {
            $this->document->setMetaData('og:description', $this->params->get('menu-meta_description'));
        }

        // Add structured data
        $this->addStructuredData();
    }

    /**
     * Add structured data (JSON-LD)
     *
     * @return  void
     */
    protected function addStructuredData()
    {
        $app = Factory::getApplication();
        
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => Text::_('COM_MOLOCH') . ' - ' . $app->get('sitename'),
            'description' => Text::_('COM_MOLOCH_DESCRIPTION'),
            'url' => Route::_('index.php?option=com_moloch', false, 0, true),
            'applicationCategory' => 'GovernmentApplication',
            'operatingSystem' => 'Web Browser',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD'
            ]
        ];

        $this->document->addCustomTag(
            '<script type="application/ld+json">' . json_encode($structuredData) . '</script>'
        );
    }

    /**
     * Load map assets based on configuration
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
                    'https://maps.googleapis.com/maps/api/js?key=' . $apiKey . '&libraries=geometry,places&callback=initMap',
                    ['defer' => true, 'async' => true]
                );
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_MOLOCH_WARNING_NO_GOOGLE_API_KEY'),
                    'warning'
                );
            }
        } else {
            // OpenStreetMap with Leaflet
            $this->document->addStyleSheet('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            $this->document->addScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
        }

        // Add map configuration to JavaScript
        $this->addMapConfig();
    }

    /**
     * Add map configuration to JavaScript
     *
     * @return  void
     */
    protected function addMapConfig()
    {
        $config = [
            'mapProvider' => $this->params->get('map_provider', 'googlemaps'),
            'defaultLat' => (float) $this->params->get('default_latitude', 20.6296),
            'defaultLng' => (float) $this->params->get('default_longitude', -87.0739),
            'defaultZoom' => (int) $this->params->get('default_zoom', 12),
            'issues' => $this->prepareIssuesForMap(),
            'categories' => $this->prepareCategoriesForMap(),
            'texts' => [
                'loading' => Text::_('COM_MOLOCH_LOADING'),
                'noIssues' => Text::_('COM_MOLOCH_NO_ISSUES_FOUND'),
                'viewDetails' => Text::_('COM_MOLOCH_VIEW_DETAILS'),
                'category' => Text::_('COM_MOLOCH_CATEGORY'),
                'status' => Text::_('COM_MOLOCH_STATUS'),
                'created' => Text::_('COM_MOLOCH_CREATED'),
                'votes' => Text::_('COM_MOLOCH_VOTES')
            ]
        ];

        $this->document->addScriptDeclaration(
            'window.MolochConfig = ' . json_encode($config) . ';'
        );
    }

    /**
     * Prepare issues data for map display
     *
     * @return  array
     */
    protected function prepareIssuesForMap()
    {
        $issues = [];

        foreach ($this->items as $item) {
            if ($item->latitude && $item->longitude) {
                $issues[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => HTMLHelper::_('string.truncate', strip_tags($item->description), 150),
                    'latitude' => (float) $item->latitude,
                    'longitude' => (float) $item->longitude,
                    'category' => [
                        'id' => $item->catid,
                        'title' => $item->category_title,
                        'color' => $item->category_color ?: '#3498db'
                    ],
                    'step' => [
                        'id' => $item->stepid,
                        'title' => $item->step_title,
                        'color' => $item->step_color ?: '#6c757d'
                    ],
                    'created' => HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC3')),
                    'votes' => [
                        'up' => $item->votes_up,
                        'down' => $item->votes_down,
                        'total' => $item->votes_up - $item->votes_down
                    ],
                    'url' => Route::_('index.php?option=com_moloch&view=issue&id=' . $item->id)
                ];
            }
        }

        return $issues;
    }

    /**
     * Prepare categories data for map display
     *
     * @return  array
     */
    protected function prepareCategoriesForMap()
    {
        $categories = [];

        foreach ($this->categories as $category) {
            $categories[] = [
                'id' => $category->id,
                'title' => $category->title,
                'color' => $category->color ?: '#3498db',
                'icon' => $category->icon
            ];
        }

        return $categories;
    }

    /**
     * Add component assets (CSS and JavaScript)
     *
     * @return  void
     */
    protected function addAssets()
    {
        // Add CSS
        HTMLHelper::_('stylesheet', 'com_moloch/moloch.css', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('stylesheet', 'com_moloch/issues.css', ['version' => 'auto', 'relative' => true]);

        // Add JavaScript
        HTMLHelper::_('script', 'com_moloch/moloch.js', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('script', 'com_moloch/issues.js', ['version' => 'auto', 'relative' => true]);
        HTMLHelper::_('script', 'com_moloch/map.js', ['version' => 'auto', 'relative' => true]);

        // Add Font Awesome for icons (if not already loaded)
        HTMLHelper::_('stylesheet', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');

        // Add responsive meta tag
        $this->document->setMetaData('viewport', 'width=device-width, initial-scale=1.0');

        // Add CSRF token for AJAX requests
        $this->document->addScriptDeclaration(
            'window.MolochToken = "' . Factory::getSession()->getFormToken() . '";'
        );
    }

    /**
     * Get the layout type from parameters
     *
     * @return  string
     */
    public function getLayoutType()
    {
        return $this->params->get('layout_type', 'list');
    }

    /**
     * Check if map should be shown
     *
     * @return  boolean
     */
    public function showMap()
    {
        return $this->params->get('show_map', 1);
    }

    /**
     * Get map position
     *
     * @return  string
     */
    public function getMapPosition()
    {
        return $this->params->get('map_position', 'top');
    }

    /**
     * Check if filters should be shown
     *
     * @return  boolean
     */
    public function showFilters()
    {
        return $this->params->get('show_filters', 1);
    }

    /**
     * Check if user can add new issues
     *
     * @return  boolean
     */
    public function canAdd()
    {
        $user = Factory::getUser();
        
        if ($user->authorise('core.create', 'com_moloch')) {
            return true;
        }

        // Check if guest submissions are allowed
        if ($user->guest && $this->params->get('guest_submissions', 1)) {
            return true;
        }

        return false;
    }
}