<?php
/**
 * @package     Moloch
 * @subpackage  Administrator.Table
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Moloch\Component\Moloch\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use Joomla\Utilities\IpHelper;

/**
 * Issues table class
 */
class IssueTable extends Table implements VersionableTableInterface, TaggableTableInterface
{
    use TaggableTableTrait;

    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var boolean
     */
    protected $_supportNullValue = true;

    /**
     * The table type prefix
     *
     * @var string
     */
    protected $_tbl_key = 'id';

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_moloch.issue';
        
        parent::__construct('#__moloch_issues', 'id', $db);

        // Set the alias since the column is nullable
        $this->_columnAlias = array('published' => 'published');
    }

    /**
     * Method to compute the default name of the asset.
     *
     * @return  string
     */
    protected function _getAssetName()
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Method to return the title to use for the asset table.
     *
     * @return  string
     */
    protected function _getAssetTitle()
    {
        return $this->title;
    }

    /**
     * Method to get the parent asset under which to register this one.
     *
     * @param   Table   $table  A Table object for the asset parent.
     * @param   integer $id     Id to look up
     *
     * @return  integer
     */
    protected function _getAssetParentId(Table $table = null, $id = null)
    {
        $assetId = null;

        // This is an issue under a category.
        if ($this->catid) {
            // Build the query to get the asset id for the category.
            $query = $this->_db->getQuery(true)
                ->select($this->_db->quoteName('asset_id'))
                ->from($this->_db->quoteName('#__categories'))
                ->where($this->_db->quoteName('id') . ' = ' . (int) $this->catid);

            // Get the asset id from the database.
            $this->_db->setQuery($query);

            if ($result = $this->_db->loadResult()) {
                $assetId = (int) $result;
            }
        }

        // Return the asset id.
        if ($assetId) {
            return $assetId;
        } else {
            // Fallback to the component asset.
            return parent::_getAssetParentId($table, $id);
        }
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  An optional array or space separated list of properties
     *
     * @return  null|string  null is operation was satisfactory, otherwise returns an error
     */
    public function bind($array, $ignore = '')
    {
        // Search for the {readmore} tag and split the text up accordingly.
        if (isset($array['description'])) {
            $pattern = '#<hr\s+id=(["\'])system-readmore\1\s*\/*>#i';
            $tagPos = preg_match($pattern, $array['description']);

            if ($tagPos == 0) {
                $this->introtext = $array['description'];
                $this->fulltext = '';
            } else {
                list ($this->introtext, $this->fulltext) = preg_split($pattern, $array['description'], 2);
            }
        }

        // Bind the rules for ACL where supported.
        if (isset($array['rules']) && is_array($array['rules'])) {
            $this->setRules($array['rules']);
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Overrides Table::store to set modified data and user id.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     */
    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getUser();

        $this->modified = $date;

        if ($this->id) {
            // Existing item
            $this->modified_by = $user->get('id');
        } else {
            // New item. An issue created and created_by field can be set by the user,
            // so we don't touch either of these if they are set.
            if (!(int) $this->created) {
                $this->created = $date;
            }

            if (empty($this->created_by)) {
                $this->created_by = $user->get('id');
            }

            if (!(int) $this->modified) {
                $this->modified = $this->created;
            }

            if (empty($this->modified_by)) {
                $this->modified_by = $this->created_by;
            }

            // Set publish_up to now if not set
            if (!(int) $this->publish_up) {
                $this->publish_up = $this->created;
            }

            // Set the values
            $this->votes_up = 0;
            $this->votes_down = 0;
            $this->hits = 0;
        }

        // Set alias
        $this->alias = trim($this->alias);
        if (empty($this->alias)) {
            $this->alias = $this->title;
        }
        $this->alias = ApplicationHelper::stringURLSafe($this->alias, $this->language);

        // Check for valid alias
        if (trim(str_replace('-', '', $this->alias)) == '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        // Set coordinates to null if empty
        if (empty($this->latitude)) {
            $this->latitude = null;
        }
        if (empty($this->longitude)) {
            $this->longitude = null;
        }

        // Set ordering
        if (empty($this->ordering)) {
            $this->ordering = $this->getNextOrder(
                $this->_db->quoteName('catid') . ' = ' . $this->_db->quote($this->catid) . ' AND published >= 0'
            );
        }

        // Verify that the alias is unique
        $table = new static($this->_db);

        if ($table->load(array('alias' => $this->alias, 'catid' => $this->catid)) &&
            ($this->id == 0 || $table->id != $this->id)) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_ISSUE_UNIQUE_ALIAS'));

            if ($table->published === -2) {
                $this->setError(Text::_('JLIB_DATABASE_ERROR_ISSUE_UNIQUE_ALIAS_TRASHED'));
            }

            return false;
        }

        return parent::store($updateNulls);
    }

    /**
     * Overrides Table::check to validate data.
     *
     * @return  boolean  True on success.
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // Check for valid name
        if (trim($this->title) == '') {
            $this->setError(Text::_('COM_MOLOCH_WARNING_PROVIDE_VALID_NAME'));
            return false;
        }

        // Check for valid description
        if (trim($this->description) == '') {
            $this->setError(Text::_('COM_MOLOCH_WARNING_PROVIDE_VALID_DESCRIPTION'));
            return false;
        }

        // Check for valid category
        if (empty($this->catid)) {
            $this->setError(Text::_('COM_MOLOCH_WARNING_PROVIDE_VALID_CATEGORY'));
            return false;
        }

        // Validate coordinates if provided
        if ($this->latitude !== null) {
            $lat = (float) $this->latitude;
            if ($lat < -90 || $lat > 90) {
                $this->setError(Text::_('COM_MOLOCH_WARNING_INVALID_LATITUDE'));
                return false;
            }
        }

        if ($this->longitude !== null) {
            $lng = (float) $this->longitude;
            if ($lng < -180 || $lng > 180) {
                $this->setError(Text::_('COM_MOLOCH_WARNING_INVALID_LONGITUDE'));
                return false;
            }
        }

        // Check for existing alias in same category
        if (empty($this->alias)) {
            $this->alias = $this->title;
        }

        $this->alias = ApplicationHelper::stringURLSafe($this->alias, $this->language);

        if (trim(str_replace('-', '', $this->alias)) == '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        // Clean up keywords -- eliminate extra spaces between phrases
        // and cr (\r) and lf (\n) characters from string
        if (!empty($this->metakey)) {
            // Only process if not empty
            $bad_characters = array("\n", "\r", "\"", "<", ">"); // Array of characters to remove
            $after_clean = StringHelper::str_ireplace($bad_characters, "", $this->metakey); // Remove bad characters
            $keys = explode(',', $after_clean); // Create array using commas as delimiter
            $clean_keys = array();

            foreach ($keys as $key) {
                if (trim($key)) {
                    // Ignore blank keywords
                    $clean_keys[] = trim($key);
                }
            }

            $this->metakey = implode(", ", $clean_keys); // Put array back together delimited by ", "
        }

        // Clean up description -- eliminate quotes and <> brackets
        if (!empty($this->metadesc)) {
            $bad_characters = array("\"", "<", ">");
            $this->metadesc = StringHelper::str_ireplace($bad_characters, "", $this->metadesc);
        }

        // If we don't have any access rules set at this point just use an empty Registry
        if (!$this->getRules()) {
            $this->setRules('{}');
        }

        // Set publish_down to null if not set
        if (!$this->publish_down) {
            $this->publish_down = null;
        }

        return true;
    }

    /**
     * Method to set the publishing state for a row or list of rows in the database
     * table. The method respects checked out rows by other users and will attempt
     * to check in rows that it can after adjustments are made.
     *
     * @param   mixed    $pks     An optional array of primary key values to update.
     *                            If not set the instance property value is used.
     * @param   integer  $state   The publishing state. eg. [0 = unpublished, 1 = published]
     * @param   integer  $userId  The user id of the user performing the operation.
     *
     * @return  boolean  True on success; false if $pks is empty.
     */
    public function publish($pks = null, $state = 1, $userId = 0)
    {
        // Initialize variables
        $k = $this->_tbl_key;

        // Sanitize input
        $userId = (int) $userId;
        $state = (int) $state;

        // If there are no primary keys set check to see if the instance key is set.
        if (empty($pks)) {
            if ($this->$k) {
                $pks = array($this->$k);
            } else {
                // Nothing to set publishing state on, return false.
                return false;
            }
        } elseif (!is_array($pks)) {
            // Make sure we have an array to work with.
            $pks = array($pks);
        }

        // Build the WHERE clause for the primary keys.
        $where = $k . '=' . implode(' OR ' . $k . '=', $pks);

        // Determine if there is checkin support for the table.
        if (!property_exists($this, 'checked_out') || !property_exists($this, 'checked_out_time')) {
            $checkin = '';
        } else {
            $checkin = ' AND (checked_out = 0 OR checked_out = ' . (int) $userId . ')';
        }

        // Update the publishing state for rows with the given primary keys.
        $this->_db->setQuery(
            'UPDATE `' . $this->_tbl . '`' .
            ' SET `published` = ' . (int) $state .
            ' WHERE (' . $where . ')' .
            $checkin
        );

        try {
            $this->_db->execute();
        } catch (\RuntimeException $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // If checkin is supported and all rows were adjusted, check them in.
        if ($checkin && (count($pks) == $this->_db->getAffectedRows())) {
            // Checkin each row.
            foreach ($pks as $pk) {
                $this->checkin($pk);
            }
        }

        // If the Table instance value is in the list of primary keys that were set, set the instance.
        if (in_array($this->$k, $pks)) {
            $this->published = $state;
        }

        $this->setError('');

        return true;
    }

    /**
     * Method to increment the hit counter for the issue
     *
     * @param   integer  $pk  Optional primary key of the issue to increment.
     *
     * @return  boolean  True if successful; false otherwise and internal error set.
     */
    public function hit($pk = null)
    {
        if (empty($pk)) {
            $pk = (int) $this->{$this->_tbl_key};
        }

        $pk = (int) $pk;

        if ($pk) {
            $this->_db->setQuery(
                'UPDATE #__moloch_issues SET hits = hits + 1 WHERE id = ' . $pk
            );

            if (!$this->_db->execute()) {
                $this->setError($this->_db->getErrorMsg());
                return false;
            }
        }

        return true;
    }

    /**
     * Method to set the featured setting for a row or list of rows in the database
     * table. The method respects checked out rows by other users and will attempt
     * to check in rows that it can after adjustments are made.
     *
     * @param   mixed    $pks     An optional array of primary key values to update.
     * @param   integer  $state   The featured state. [0 = unfeatured, 1 = featured]
     * @param   integer  $userId  The user id of the user performing the operation.
     *
     * @return  boolean  True on success.
     */
    public function featured($pks = null, $state = 1, $userId = 0)
    {
        // Initialize variables
        $k = $this->_tbl_key;

        // Sanitize input
        $userId = (int) $userId;
        $state = (int) $state;

        // If there are no primary keys set check to see if the instance key is set.
        if (empty($pks)) {
            if ($this->$k) {
                $pks = array($this->$k);
            } else {
                // Nothing to set featured state on, return false.
                return false;
            }
        } elseif (!is_array($pks)) {
            // Make sure we have an array to work with.
            $pks = array($pks);
        }

        // Build the WHERE clause for the primary keys.
        $where = $k . '=' . implode(' OR ' . $k . '=', $pks);

        // Determine if there is checkin support for the table.
        if (!property_exists($this, 'checked_out') || !property_exists($this, 'checked_out_time')) {
            $checkin = '';
        } else {
            $checkin = ' AND (checked_out = 0 OR checked_out = ' . (int) $userId . ')';
        }

        // Update the featured state for rows with the given primary keys.
        $this->_db->setQuery(
            'UPDATE `' . $this->_tbl . '`' .
            ' SET `featured` = ' . (int) $state .
            ' WHERE (' . $where . ')' .
            $checkin
        );

        try {
            $this->_db->execute();
        } catch (\RuntimeException $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // If checkin is supported and all rows were adjusted, check them in.
        if ($checkin && (count($pks) == $this->_db->getAffectedRows())) {
            // Checkin each row.
            foreach ($pks as $pk) {
                $this->checkin($pk);
            }
        }

        // If the Table instance value is in the list of primary keys that were set, set the instance.
        if (in_array($this->$k, $pks)) {
            $this->featured = $state;
        }

        $this->setError('');

        return true;
    }

    /**
     * Get the type alias for UCM features
     *
     * @return  string  The alias as described above
     */
    public function getTypeAlias()
    {
        return $this->typeAlias;
    }

    /**
     * Method to log an action on this issue
     *
     * @param   string  $action       The action performed
     * @param   string  $description  Action description  
     * @param   mixed   $oldValue     Old value
     * @param   mixed   $newValue     New value
     *
     * @return  boolean  True on success
     */
    public function logAction($action, $description, $oldValue = null, $newValue = null)
    {
        try {
            $user = Factory::getUser();
            $date = Factory::getDate();

            $log = (object) array(
                'issue_id' => $this->id,
                'action' => $action,
                'description' => $description,
                'old_value' => is_array($oldValue) || is_object($oldValue) ? 
                    json_encode($oldValue) : $oldValue,
                'new_value' => is_array($newValue) || is_object($newValue) ? 
                    json_encode($newValue) : $newValue,
                'created' => $date->toSql(),
                'created_by' => $user->get('id'),
                'created_by_alias' => $user->guest ? $user->get('username', 'Guest') : '',
                'user_ip' => IpHelper::getIp(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            );

            return $this->_db->insertObject('#__moloch_logs', $log);

        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Factory::getLog()->add(
                'Failed to log action: ' . $e->getMessage(),
                \JLog::WARNING,
                'com_moloch'
            );
            return false;
        }
    }
}