<?php
/**
 * @package     Moloch
 * @subpackage  Site.Model
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Moloch\Component\Moloch\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel as BaseFormModel;
use Joomla\CMS\User\User;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Log\Log;
use Joomla\Utilities\IpHelper;
use Joomla\Registry\Registry;

/**
 * Moloch Component Form Model
 */
class FormModel extends BaseFormModel
{
    /**
     * Model context string
     *
     * @var string
     */
    public $_context = 'com_moloch.form';

    /**
     * Method to get the form
     *
     * @param   array    $data      Data for the form
     * @param   boolean  $loadData  True if the form is to load its own data
     *
     * @return  Form|boolean  A Form object on success, false on failure
     */
    public function getForm($data = array(), $loadData = true)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        // Get the form
        $form = $this->loadForm(
            'com_moloch.issue',
            'issue',
            array('control' => 'jform', 'load_data' => $loadData)
        );

        if (empty($form)) {
            return false;
        }

        // Modify form based on user permissions
        $this->preprocessForm($form, $data);

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form
     *
     * @return  mixed  The data for the form
     */
    protected function loadFormData()
    {
        $app = Factory::getApplication();
        
        // Check the session for previously entered form data
        $data = $app->getUserState('com_moloch.edit.issue.data', array());

        if (empty($data)) {
            $data = $this->getItem();

            // Pre-populate some fields for new items
            if (empty($data->id)) {
                $data->created_by = Factory::getUser()->get('id');
                $data->created = Factory::getDate()->toSql();
                $data->access = 1; // Public
                $data->published = 1; // Published by default
                $data->stepid = 1; // First step
            }
        }

        return $data;
    }

    /**
     * Method to get a single record
     *
     * @param   integer  $pk  The id of the primary key
     *
     * @return  mixed  Object on success, false on failure
     */
    public function getItem($pk = null)
    {
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('issue.id');

        if ($pk > 0) {
            try {
                $db = $this->getDbo();
                $query = $db->getQuery(true)
                    ->select('a.*')
                    ->select('c.title AS category_title, c.color AS category_color')
                    ->select('s.title AS step_title, s.color AS step_color')
                    ->from('#__moloch_issues AS a')
                    ->leftjoin('#__moloch_categories AS c ON c.id = a.catid')
                    ->leftjoin('#__moloch_steps AS s ON s.id = a.stepid')
                    ->where('a.id = ' . (int) $pk);

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    throw new \Exception(Text::_('COM_MOLOCH_ERROR_ISSUE_NOT_FOUND'), 404);
                }

                // Get attachments
                $data->attachments = $this->getAttachments($pk);

                return $data;

            } catch (\Exception $e) {
                $this->setError($e);
                return false;
            }
        }

        // Return empty object for new items
        return (object) array(
            'id' => 0,
            'title' => '',
            'description' => '',
            'catid' => '',
            'stepid' => 1,
            'address' => '',
            'latitude' => '',
            'longitude' => '',
            'attachments' => array()
        );
    }

    /**
     * Method to get attachments for an issue
     *
     * @param   integer  $issueId  The issue ID
     *
     * @return  array    Array of attachment objects
     */
    public function getAttachments($issueId)
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__moloch_attachments')
            ->where('issue_id = ' . (int) $issueId)
            ->where('published = 1')
            ->order('ordering ASC, id ASC');

        $db->setQuery($query);
        return $db->loadObjectList() ?: array();
    }

    /**
     * Method to save the form data
     *
     * @param   array  $data  The form data
     *
     * @return  boolean  True on success
     */
    public function save($data)
    {
        $user = Factory::getUser();
        $db = $this->getDbo();
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_moloch');

        // Initialize variables
        $isNew = empty($data['id']);
        $table = $this->getTable();

        try {
            // Bind data to table
            if (!$table->bind($data)) {
                $this->setError($table->getError());
                return false;
            }

            // Set default values for new issues
            if ($isNew) {
                $table->created = Factory::getDate()->toSql();
                $table->created_by = $user->get('id');
                
                // Auto-approve based on configuration
                $table->published = $params->get('auto_approve_issues', 0) ? 1 : 0;
                
                // Set default step
                if (empty($table->stepid)) {
                    $table->stepid = $this->getDefaultStep();
                }
            } else {
                $table->modified = Factory::getDate()->toSql();
                $table->modified_by = $user->get('id');
            }

            // Auto-generate alias if empty
            if (empty($table->alias)) {
                $table->alias = $table->title;
            }

            // Generate unique alias
            $table->alias = $this->generateUniqueAlias($table->alias, $table->id);

            // Prepare and sanitise the table prior to saving
            if (!$table->check()) {
                $this->setError($table->getError());
                return false;
            }

            // Store the data
            if (!$table->store()) {
                $this->setError($table->getError());
                return false;
            }

            $this->setState('issue.id', $table->id);

            // Log the action
            $this->logAction(
                $table->id,
                $isNew ? 'create' : 'update',
                $isNew ? 'Issue created' : 'Issue updated'
            );

            // Send notifications for new issues
            if ($isNew && $params->get('notification_email')) {
                $this->sendNewIssueNotification($table);
            }

            // Clean the cache
            $this->cleanCache();

            return true;

        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Method to upload files
     *
     * @param   array    $files    Files array
     * @param   integer  $issueId  Issue ID
     *
     * @return  array    Array of uploaded file information
     */
    public function uploadFiles($files, $issueId = 0)
    {
        $params = ComponentHelper::getParams('com_moloch');
        $user = Factory::getUser();
        $uploadedFiles = array();

        // Get upload settings
        $maxFileSize = $params->get('max_file_size', 1073741824); // 1GB default
        $allowedTypes = explode(',', $params->get('allowed_file_types', 'jpg,jpeg,png,gif,pdf'));
        
        // Create upload directory if it doesn't exist
        $uploadDir = JPATH_ROOT . '/media/com_moloch/uploads/' . date('Y') . '/' . date('m');
        if (!Folder::exists($uploadDir)) {
            if (!Folder::create($uploadDir)) {
                throw new \Exception(Text::_('COM_MOLOCH_ERROR_CREATE_UPLOAD_DIR'));
            }
        }

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception(Text::sprintf('COM_MOLOCH_ERROR_UPLOAD_ERROR', $file['name'], $file['error']));
            }

            // Validate file size
            if ($file['size'] > $maxFileSize) {
                throw new \Exception(Text::sprintf('COM_MOLOCH_ERROR_FILE_TOO_LARGE', $file['name']));
            }

            // Validate file type
            $fileExtension = strtolower(File::getExt($file['name']));
            if (!empty($allowedTypes) && !in_array($fileExtension, $allowedTypes)) {
                throw new \Exception(Text::sprintf('COM_MOLOCH_ERROR_FILE_TYPE', $file['name']));
            }

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file['name'], $uploadDir);
            $filepath = $uploadDir . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new \Exception(Text::sprintf('COM_MOLOCH_ERROR_MOVE_FILE', $file['name']));
            }

            // Determine file type
            $fileType = $this->getFileType($file['type'], $fileExtension);

            // Create thumbnail for images
            $thumbnail = null;
            if ($fileType === 'image') {
                $thumbnail = $this->createThumbnail($filepath, $uploadDir);
            }

            // Save file information to database
            $attachment = (object) array(
                'issue_id' => $issueId,
                'filename' => $filename,
                'original_filename' => $file['name'],
                'filepath' => str_replace(JPATH_ROOT . '/', '', $filepath),
                'filesize' => $file['size'],
                'mimetype' => $file['type'],
                'file_type' => $fileType,
                'thumbnail' => $thumbnail ? str_replace(JPATH_ROOT . '/', '', $thumbnail) : null,
                'published' => 1,
                'created' => Factory::getDate()->toSql(),
                'created_by' => $user->get('id'),
                'access' => 1,
                'ordering' => 0
            );

            $db = $this->getDbo();
            if ($db->insertObject('#__moloch_attachments', $attachment)) {
                $attachment->id = $db->insertid();
                $uploadedFiles[] = $attachment;
            }
        }

        return $uploadedFiles;
    }

    /**
     * Generate unique filename
     *
     * @param   string  $originalName  Original filename
     * @param   string  $directory     Target directory
     *
     * @return  string  Unique filename
     */
    protected function generateUniqueFilename($originalName, $directory)
    {
        $info = pathinfo($originalName);
        $baseName = $info['filename'];
        $extension = $info['extension'];

        // Sanitize filename
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        
        // Add timestamp and random string
        $timestamp = time();
        $random = substr(md5(mt_rand()), 0, 8);
        
        return $baseName . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Determine file type based on MIME type and extension
     *
     * @param   string  $mimeType   MIME type
     * @param   string  $extension  File extension
     *
     * @return  string  File type
     */
    protected function getFileType($mimeType, $extension)
    {
        // Image types
        if (strpos($mimeType, 'image/') === 0 || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            return 'image';
        }

        // Video types
        if (strpos($mimeType, 'video/') === 0 || in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])) {
            return 'video';
        }

        // Audio types
        if (strpos($mimeType, 'audio/') === 0 || in_array($extension, ['mp3', 'wav', 'ogg', 'aac', 'm4a'])) {
            return 'audio';
        }

        // Document types
        $docTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'];
        if (in_array($extension, $docTypes)) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Create thumbnail for image
     *
     * @param   string  $imagePath  Path to original image
     * @param   string  $directory  Target directory
     *
     * @return  string|null  Path to thumbnail or null on failure
     */
    protected function createThumbnail($imagePath, $directory)
    {
        try {
            $info = pathinfo($imagePath);
            $thumbnailPath = $directory . '/thumb_' . $info['basename'];

            // Use GD or ImageMagick to create thumbnail
            if (extension_loaded('gd')) {
                return $this->createThumbnailGD($imagePath, $thumbnailPath);
            }

            return null;

        } catch (\Exception $e) {
            Log::add('Error creating thumbnail: ' . $e->getMessage(), Log::WARNING, 'com_moloch');
            return null;
        }
    }

    /**
     * Create thumbnail using GD
     *
     * @param   string  $source      Source image path
     * @param   string  $destination Destination thumbnail path
     * @param   integer $maxWidth    Maximum width
     * @param   integer $maxHeight   Maximum height
     *
     * @return  string|null  Thumbnail path or null on failure
     */
    protected function createThumbnailGD($source, $destination, $maxWidth = 300, $maxHeight = 300)
    {
        $imageInfo = getimagesize($source);
        
        if (!$imageInfo) {
            return null;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];

        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        // Create image resource based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($source);
                break;
            default:
                return null;
        }

        if (!$sourceImage) {
            return null;
        }

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize image
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save thumbnail
        $success = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($thumbnail, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                $success = imagepng($thumbnail, $destination);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($thumbnail, $destination);
                break;
        }

        // Clean up memory
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return $success ? $destination : null;
    }

    /**
     * Method to get categories
     *
     * @return  array  Array of categories
     */
    public function getCategories()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__moloch_categories')
            ->where('published = 1')
            ->order('ordering ASC, title ASC');

        $db->setQuery($query);
        return $db->loadObjectList() ?: array();
    }

    /**
     * Method to get workflow steps
     *
     * @return  array  Array of steps
     */
    public function getSteps()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__moloch_steps')
            ->where('published = 1')
            ->order('ordering ASC, title ASC');

        $db->setQuery($query);
        return $db->loadObjectList() ?: array();
    }

    /**
     * Get default step ID
     *
     * @return  integer  Default step ID
     */
    protected function getDefaultStep()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__moloch_steps')
            ->where('published = 1')
            ->order('ordering ASC')
            ->setLimit(1);

        $db->setQuery($query);
        return (int) $db->loadResult() ?: 1;
    }

    /**
     * Generate unique alias
     *
     * @param   string   $alias  The alias
     * @param   integer  $id     The ID
     *
     * @return  string   The unique alias
     */
    protected function generateUniqueAlias($alias, $id = 0)
    {
        $alias = strtolower(trim($alias));
        $alias = preg_replace('/[^a-z0-9-]/', '-', $alias);
        $alias = preg_replace('/-+/', '-', $alias);
        $alias = trim($alias, '-');

        if (empty($alias)) {
            $alias = 'issue-' . time();
        }

        $db = $this->getDbo();
        $originalAlias = $alias;
        $counter = 1;

        while (true) {
            $query = $db->getQuery(true)
                ->select('id')
                ->from('#__moloch_issues')
                ->where('alias = ' . $db->quote($alias));
            
            if ($id) {
                $query->where('id != ' . (int) $id);
            }

            $db->setQuery($query);
            
            if (!$db->loadResult()) {
                break;
            }

            $alias = $originalAlias . '-' . $counter++;
        }

        return $alias;
    }

    /**
     * Log an action
     *
     * @param   integer  $issueId      Issue ID
     * @param   string   $action       Action performed
     * @param   string   $description  Action description
     * @param   mixed    $oldValue     Old value
     * @param   mixed    $newValue     New value
     *
     * @return  void
     */
    protected function logAction($issueId, $action, $description, $oldValue = null, $newValue = null)
    {
        try {
            $user = Factory::getUser();
            $db = $this->getDbo();

            $log = (object) array(
                'issue_id' => (int) $issueId,
                'action' => $action,
                'description' => $description,
                'old_value' => is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : $oldValue,
                'new_value' => is_array($newValue) || is_object($newValue) ? json_encode($newValue) : $newValue,
                'created' => Factory::getDate()->toSql(),
                'created_by' => $user->get('id', 0),
                'created_by_alias' => $user->get('guest') ? $user->get('username', 'Guest') : '',
                'user_ip' => IpHelper::getIp(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            );

            $db->insertObject('#__moloch_logs', $log);

        } catch (\Exception $e) {
            Log::add('Failed to log action: ' . $e->getMessage(), Log::WARNING, 'com_moloch');
        }
    }

    /**
     * Send new issue notification
     *
     * @param   object  $issue  Issue object
     *
     * @return  void
     */
    protected function sendNewIssueNotification($issue)
    {
        try {
            $params = ComponentHelper::getParams('com_moloch');
            $notificationEmail = $params->get('notification_email');
            
            if (empty($notificationEmail)) {
                return;
            }

            $app = Factory::getApplication();
            $mailer = Factory::getMailer();

            // Prepare email
            $subject = Text::sprintf('COM_MOLOCH_NEW_ISSUE_NOTIFICATION_SUBJECT', $issue->title);
            $body = Text::sprintf(
                'COM_MOLOCH_NEW_ISSUE_NOTIFICATION_BODY',
                $issue->title,
                $issue->description,
                $issue->address
            );

            $mailer->setSender(array($app->get('mailfrom'), $app->get('fromname')));
            $mailer->addRecipient($notificationEmail);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(false);

            if (!$mailer->Send()) {
                Log::add('Failed to send notification email', Log::WARNING, 'com_moloch');
            }

        } catch (\Exception $e) {
            Log::add('Error sending notification: ' . $e->getMessage(), Log::WARNING, 'com_moloch');
        }
    }

    /**
     * Method to get the table
     *
     * @param   string  $name     Table name
     * @param   string  $prefix   Table prefix
     * @param   array   $options  Configuration array
     *
     * @return  Table  Table object
     */
    public function getTable($name = 'Issue', $prefix = 'MolochTable', $options = array())
    {
        return Table::getInstance($name, $prefix, $options);
    }

    /**
     * Auto-populate the model state
     *
     * @return  void
     */
    protected function populateState()
    {
        $app = Factory::getApplication();

        // Load the object state
        $id = $app->input->getInt('id');
        $this->setState('issue.id', $id);
    }

    /**
     * Clean component cache
     *
     * @return  void
     */
    protected function cleanCache()
    {
        $cache = Factory::getCache('com_moloch');
        $cache->clean();
    }
}