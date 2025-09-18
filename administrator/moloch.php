<?php
/**
 * @package     Moloch
 * @subpackage  Administrator
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\HTML\HTMLHelper;

// Access check
if (!Factory::getUser()->authorise('core.manage', 'com_moloch')) {
    throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

// Load component language files
$lang = Factory::getLanguage();
$lang->load('com_moloch', JPATH_ADMINISTRATOR);
$lang->load('com_moloch', JPATH_SITE);

// Register component namespace for autoloading
JLoader::registerNamespace(
    'Moloch\\Component\\Moloch',
    JPATH_ADMINISTRATOR . '/components/com_moloch/src',
    false,
    false,
    'psr4'
);

// Load Bootstrap and other UI frameworks if needed
HTMLHelper::_('behavior.framework');
HTMLHelper::_('bootstrap.framework');

// Include helper files
JLoader::register('MolochHelper', JPATH_ADMINISTRATOR . '/components/com_moloch/helpers/moloch.php');

// Get document for adding assets
$document = Factory::getDocument();

// Add component CSS
$document->addStyleSheet('../media/com_moloch/css/moloch.css');
$document->addStyleSheet('../media/com_moloch/css/admin.css');

// Add component JavaScript
$document->addScript('../media/com_moloch/js/moloch.js');
$document->addScript('../media/com_moloch/js/admin.js');

// Get application and input
$app = Factory::getApplication();
$input = $app->input;

// Security check for CSRF tokens on POST requests
if ($input->getMethod() === 'POST') {
    JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
}

// Get controller instance
$controller = BaseController::getInstance('Moloch', array('base_path' => JPATH_ADMINISTRATOR . '/components/com_moloch'));

// Get task from input
$task = $input->getCmd('task', '');

// Define allowed tasks for security
$allowedTasks = [
    'display', 
    'add', 
    'edit', 
    'save', 
    'save2new', 
    'save2copy', 
    'save2close', 
    'cancel', 
    'delete',
    'publish', 
    'unpublish', 
    'archive', 
    'trash', 
    'featured', 
    'unfeatured',
    'checkin',
    'batch',
    'orderup',
    'orderdown',
    'saveorder',
    'export',
    'import'
];

// Validate task
if (!empty($task) && !in_array($task, $allowedTasks)) {
    $app->enqueueMessage(Text::_('JERROR_TASK_NOT_ALLOWED'), 'error');
    $task = 'display';
}

try {
    // Execute the task
    $controller->execute($task);
    
    // Redirect if required by controller
    $controller->redirect();
    
} catch (Exception $e) {
    // Log the error
    Factory::getLog()->add(
        'Moloch Administrator Error: ' . $e->getMessage(),
        JLog::ERROR,
        'com_moloch'
    );
    
    // Show user-friendly error message
    $app->enqueueMessage(
        Text::_('COM_MOLOCH_ERROR_GENERAL'),
        'error'
    );
    
    // Redirect to dashboard on error
    $app->redirect('index.php?option=com_moloch');
}

/**
 * Moloch Administrator Base Controller
 */
class MolochController extends BaseController
{
    /**
     * The default view for the display method
     *
     * @var string
     */
    protected $default_view = 'dashboard';

    /**
     * Method to display a view
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe url parameters
     *
     * @return  BaseController  This object to support chaining
     */
    public function display($cachable = false, $urlparams = array())
    {
        $input = Factory::getApplication()->input;
        $view = $input->getCmd('view', $this->default_view);
        $layout = $input->get('layout', 'default');
        $id = $input->getInt('id');

        // Set safe URL parameters for caching
        $safeurlparams = array(
            'catid' => 'INT',
            'id' => 'INT',
            'cid' => 'ARRAY',
            'year' => 'INT',
            'month' => 'INT',
            'limit' => 'UINT',
            'limitstart' => 'UINT',
            'showall' => 'INT',
            'return' => 'BASE64',
            'filter' => 'STRING',
            'filter_order' => 'CMD',
            'filter_order_Dir' => 'CMD',
            'filter-search' => 'STRING',
            'print' => 'BOOLEAN',
            'lang' => 'CMD',
            'Itemid' => 'INT'
        );

        // Check user access for specific views
        $this->checkViewAccess($view, $id);

        return parent::display($cachable, $safeurlparams);
    }

    /**
     * Check user access for specific views
     *
     * @param   string   $view  The view name
     * @param   integer  $id    The item ID
     *
     * @return  void
     */
    protected function checkViewAccess($view, $id = 0)
    {
        $user = Factory::getUser();

        // Define view permissions
        $viewPermissions = array(
            'issues' => 'core.manage',
            'issue' => $id ? 'core.edit' : 'core.create',
            'categories' => 'core.manage',
            'category' => $id ? 'core.edit' : 'core.create',
            'steps' => 'core.manage',
            'step' => $id ? 'core.edit' : 'core.create',
            'attachments' => 'core.manage',
            'attachment' => 'core.manage',
            'logs' => 'core.manage',
            'dashboard' => 'core.manage'
        );

        $requiredPermission = $viewPermissions[$view] ?? 'core.manage';

        if (!$user->authorise($requiredPermission, 'com_moloch')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Proxy for getModel
     *
     * @param   string  $name    The model name
     * @param   string  $prefix  The class prefix
     * @param   array   $config  Configuration array for model
     *
     * @return  object  The model
     */
    public function getModel($name = 'Issue', $prefix = 'MolochModel', $config = array())
    {
        return parent::getModel($name, $prefix, $config);
    }
}

/**
 * Dashboard Controller (default view)
 */
class MolochControllerDashboard extends BaseController
{
    /**
     * Display the dashboard
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe url parameters
     *
     * @return  void
     */
    public function display($cachable = false, $urlparams = array())
    {
        // Load dashboard view
        $view = $this->getView('Dashboard', 'html');
        
        // Load models for dashboard statistics
        $issuesModel = $this->getModel('Issues', 'MolochModel');
        $categoriesModel = $this->getModel('Categories', 'MolochModel');
        $logsModel = $this->getModel('Logs', 'MolochModel');
        
        // Set models to view
        $view->setModel($issuesModel, true);
        $view->setModel($categoriesModel, false);
        $view->setModel($logsModel, false);
        
        // Display the view
        $view->display();
    }
}

/**
 * Helper function to check component permissions
 *
 * @param   string  $action  The action to check
 * @param   string  $asset   The asset name
 *
 * @return  boolean  True if user has permission
 */
function molochCanDo($action, $asset = 'com_moloch')
{
    $user = Factory::getUser();
    return $user->authorise($action, $asset);
}

/**
 * Helper function to get component parameters
 *
 * @return  Registry  Component parameters
 */
function molochGetParams()
{
    return JComponentHelper::getParams('com_moloch');
}

/**
 * Helper function to log component activity
 *
 * @param   string  $message   Log message
 * @param   string  $level     Log level
 * @param   string  $category  Log category
 *
 * @return  void
 */
function molochLog($message, $level = JLog::INFO, $category = 'com_moloch')
{
    JLog::add($message, $level, $category);
}

/**
 * Initialize admin menu
 */
function initMolochAdminMenu()
{
    $app = Factory::getApplication();
    $input = $app->input;
    $view = $input->getCmd('view', 'dashboard');
    
    // Set submenu items
    JHtmlSidebar::addEntry(
        Text::_('COM_MOLOCH_DASHBOARD'),
        'index.php?option=com_moloch&view=dashboard',
        $view == 'dashboard'
    );
    
    JHtmlSidebar::addEntry(
        Text::_('COM_MOLOCH_ISSUES'),
        'index.php?option=com_moloch&view=issues',
        in_array($view, ['issues', 'issue'])
    );
    
    JHtmlSidebar::addEntry(
        Text::_('COM_MOLOCH_CATEGORIES'),
        'index.php?option=com_moloch&view=categories',
        in_array($view, ['categories', 'category'])
    );
    
    JHtmlSidebar::addEntry(
        Text::_('COM_MOLOCH_STEPS'),
        'index.php?option=com_moloch&view=steps',
        in_array($view, ['steps', 'step'])
    );
    
    JHtmlSidebar::addEntry(
        Text::_('COM_MOLOCH_ATTACHMENTS'),
        'index.php?option=com_moloch&view=attachments',
        in_array($view, ['attachments', 'attachment'])
    );
    
    JHtmlSidebar::addEntry(
        Text::_('COM_MOLOCH_LOGS'),
        'index.php?option=com_moloch&view=logs',
        $view == 'logs'
    );
}

// Initialize admin menu
initMolochAdminMenu();