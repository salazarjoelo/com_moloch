<?php
/**
 * @package     Moloch
 * @subpackage  Site
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Component\ComponentHelper;

// Import Joomla! libraries
jimport('joomla.application.component.controller');

// Check Joomla version for compatibility
$jversion = new JVersion();
$joomlaVersion = $jversion->getShortVersion();

// Load component language files
$lang = Factory::getLanguage();
$lang->load('com_moloch', JPATH_SITE);
$lang->load('com_moloch', JPATH_ADMINISTRATOR);

// Get component parameters
$params = ComponentHelper::getParams('com_moloch');

// Load component CSS and JS
$document = Factory::getDocument();
$document->addStyleSheet(JURI::root() . 'media/com_moloch/css/moloch.css');
$document->addScript(JURI::root() . 'media/com_moloch/js/moloch.js');

// Check if Bootstrap is needed (for Joomla 3)
if (version_compare(JVERSION, '4.0', '<')) {
    JHtml::_('bootstrap.framework');
    JHtml::_('behavior.jquery');
} else {
    // Load Bootstrap for Joomla 4+
    $document->getWebAssetManager()->useStyle('bootstrap.css')->useScript('bootstrap.js');
}

// Get the controller
$controller = BaseController::getInstance('Moloch');

// Perform the Request task
$input = Factory::getApplication()->input;

// Security check for CSRF tokens
if ($input->getMethod() === 'POST') {
    JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
}

// Handle different tasks
$task = $input->getCmd('task', '');
$view = $input->getCmd('view', 'issues');

// Define allowed tasks and views for security
$allowedTasks = ['display', 'add', 'edit', 'save', 'delete', 'publish', 'unpublish', 'vote', 'comment', 'upload'];
$allowedViews = ['issues', 'issue', 'form', 'category', 'map'];

if (!empty($task) && !in_array($task, $allowedTasks)) {
    $task = 'display';
}

if (!in_array($view, $allowedViews)) {
    $view = 'issues';
}

try {
    $controller->execute($task);
    $controller->redirect();
} catch (Exception $e) {
    // Log error
    Factory::getLog()->add(
        'Moloch Component Error: ' . $e->getMessage(),
        JLog::ERROR,
        'com_moloch'
    );
    
    // Show user-friendly error
    Factory::getApplication()->enqueueMessage(
        Text::_('COM_MOLOCH_ERROR_GENERAL'),
        'error'
    );
}

/**
 * Moloch Base Controller
 */
class MolochController extends BaseController
{
    /**
     * The default view for the display method
     *
     * @var string
     */
    protected $default_view = 'issues';

    /**
     * Method to display a view
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe url parameters and their variable types
     *
     * @return  BaseController  This object to support chaining
     */
    public function display($cachable = false, $urlparams = array())
    {
        $input = Factory::getApplication()->input;
        $view = $input->getCmd('view', $this->default_view);
        $layout = $input->get('layout', 'default');
        $id = $input->getInt('id');

        // Set safe URL parameters
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

        // Check view access
        if ($view === 'form') {
            $this->checkEditAccess($id);
        }

        parent::display($cachable, $safeurlparams);

        return $this;
    }

    /**
     * Method to add a new issue
     */
    public function add()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $params = ComponentHelper::getParams('com_moloch');

        // Check if guest submissions are allowed
        if ($user->guest && !$params->get('guest_submissions', 1)) {
            $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(JRoute::_('index.php?option=com_users&view=login'));
            return;
        }

        // Redirect to form view
        $app->redirect(JRoute::_('index.php?option=com_moloch&view=form&layout=edit'));
    }

    /**
     * Method to edit an issue
     */
    public function edit()
    {
        $app = Factory::getApplication();
        $input = Factory::getApplication()->input;
        $id = $input->getInt('id', 0);

        if (!$this->checkEditAccess($id)) {
            return;
        }

        $app->redirect(JRoute::_('index.php?option=com_moloch&view=form&layout=edit&id=' . $id));
    }

    /**
     * Method to save an issue
     */
    public function save()
    {
        $app = Factory::getApplication();
        $input = Factory::getApplication()->input;
        $user = Factory::getUser();
        $params = ComponentHelper::getParams('com_moloch');

        $data = $input->get('jform', array(), 'array');
        $id = (int) $data['id'];

        // Check access
        if (!$this->checkEditAccess($id)) {
            return;
        }

        // Get the model
        $model = $this->getModel('Form', 'MolochModel');

        if ($model->save($data)) {
            $app->enqueueMessage(Text::_('COM_MOLOCH_ISSUE_SAVED'));
            
            // Redirect based on task
            if ($input->get('task') === 'save2new') {
                $app->redirect(JRoute::_('index.php?option=com_moloch&view=form&layout=edit'));
            } else {
                $app->redirect(JRoute::_('index.php?option=com_moloch&view=issues'));
            }
        } else {
            $errors = $model->getErrors();
            foreach ($errors as $error) {
                $app->enqueueMessage($error, 'error');
            }
            
            // Redirect back to form
            $app->redirect(JRoute::_('index.php?option=com_moloch&view=form&layout=edit&id=' . $id));
        }
    }

    /**
     * Method to vote on an issue
     */
    public function vote()
    {
        $app = Factory::getApplication();
        $input = Factory::getApplication()->input;
        $user = Factory::getUser();
        $params = ComponentHelper::getParams('com_moloch');

        // Check if voting is enabled
        if (!$params->get('enable_voting', 1)) {
            $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_VOTING_DISABLED'), 'error');
            $this->setRedirect(JRoute::_('index.php?option=com_moloch'));
            return;
        }

        $issueId = $input->getInt('id', 0);
        $vote = $input->getInt('vote', 0); // 1 for up, -1 for down

        if (!$issueId || !in_array($vote, [1, -1])) {
            $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_INVALID_VOTE'), 'error');
            $this->setRedirect(JRoute::_('index.php?option=com_moloch&view=issue&id=' . $issueId));
            return;
        }

        $model = $this->getModel('Issue', 'MolochModel');
        
        if ($model->vote($issueId, $vote)) {
            $app->enqueueMessage(Text::_('COM_MOLOCH_VOTE_SAVED'));
        } else {
            $errors = $model->getErrors();
            foreach ($errors as $error) {
                $app->enqueueMessage($error, 'error');
            }
        }

        $this->setRedirect(JRoute::_('index.php?option=com_moloch&view=issue&id=' . $issueId));
    }

    /**
     * Method to upload files via AJAX
     */
    public function upload()
    {
        $app = Factory::getApplication();
        $input = Factory::getApplication()->input;
        $user = Factory::getUser();

        // Set JSON response
        $response = array('success' => false, 'message' => '');

        try {
            $files = $input->files->get('files', array(), 'array');
            $issueId = $input->getInt('issue_id', 0);

            if (empty($files)) {
                throw new Exception(Text::_('COM_MOLOCH_ERROR_NO_FILES'));
            }

            $model = $this->getModel('Form', 'MolochModel');
            $uploadedFiles = $model->uploadFiles($files, $issueId);

            $response['success'] = true;
            $response['files'] = $uploadedFiles;
            $response['message'] = Text::_('COM_MOLOCH_FILES_UPLOADED');

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        $app->close();
    }

    /**
     * Check edit access for an issue
     */
    protected function checkEditAccess($id = 0)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $params = ComponentHelper::getParams('com_moloch');

        // For new issues
        if (!$id) {
            if ($user->guest && !$params->get('guest_submissions', 1)) {
                $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_LOGIN_REQUIRED'), 'error');
                $app->redirect(JRoute::_('index.php?option=com_users&view=login'));
                return false;
            }
            return true;
        }

        // For existing issues
        $model = $this->getModel('Issue', 'MolochModel');
        $issue = $model->getItem($id);

        if (!$issue) {
            $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_ISSUE_NOT_FOUND'), 'error');
            $app->redirect(JRoute::_('index.php?option=com_moloch'));
            return false;
        }

        // Check if user can edit this issue
        $canEdit = false;

        if ($user->authorise('core.edit', 'com_moloch')) {
            $canEdit = true;
        } elseif ($user->authorise('core.edit.own', 'com_moloch') && $issue->created_by == $user->id) {
            $canEdit = true;
        }

        if (!$canEdit) {
            $app->enqueueMessage(Text::_('COM_MOLOCH_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $app->redirect(JRoute::_('index.php?option=com_moloch&view=issue&id=' . $id));
            return false;
        }

        return true;
    }
}