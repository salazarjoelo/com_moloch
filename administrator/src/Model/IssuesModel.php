<?php
/**
 * @package     Moloch
 * @subpackage  Administrator.Model
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Moloch\Component\Moloch\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

/**
 * Methods supporting a list of Moloch issues.
 */
class IssuesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'title', 'a.title',
                'alias', 'a.alias',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'catid', 'a.catid', 'category_title',
                'stepid', 'a.stepid', 'step_title',
                'published', 'a.published',
                'access', 'a.access', 'access_level',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'created_by_alias', 'a.created_by_alias',
                'ordering', 'a.ordering',
                'featured', 'a.featured',
                'language', 'a.language',
                'hits', 'a.hits',
                'publish_up', 'a.publish_up',
                'publish_down', 'a.publish_down',
                'votes_up', 'a.votes_up',
                'votes_down', 'a.votes_down',
                'address', 'a.address',
                'latitude', 'a.latitude',
                'longitude', 'a.longitude',
                'tag',
                'level', 'c.level',
            );

            if (Associations::isEnabled()) {
                $config['filter_fields'][] = 'association';
            }
        }

        parent::__construct($config);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \JDatabaseQuery
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $user = Factory::getUser();

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.alias'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                    $db->quoteName('a.catid'),
                    $db->quoteName('a.stepid'),
                    $db->quoteName('a.published'),
                    $db->quoteName('a.access'),
                    $db->quoteName('a.created'),
                    $db->quoteName('a.created_by'),
                    $db->quoteName('a.created_by_alias'),
                    $db->quoteName('a.ordering'),
                    $db->quoteName('a.featured'),
                    $db->quoteName('a.language'),
                    $db->quoteName('a.hits'),
                    $db->quoteName('a.publish_up'),
                    $db->quoteName('a.publish_down'),
                    $db->quoteName('a.votes_up'),
                    $db->quoteName('a.votes_down'),
                    $db->quoteName('a.address'),
                    $db->quoteName('a.latitude'),
                    $db->quoteName('a.longitude'),
                    $db->quoteName('a.description', 'description'),
                ]
            )
        );

        $query->from($db->quoteName('#__moloch_issues', 'a'));

        // Join over the categories.
        $query->select(
            [
                $db->quoteName('c.title', 'category_title'),
                $db->quoteName('c.created_by', 'category_created_by'),
                $db->quoteName('c.color', 'category_color'),
            ]
        )
            ->join('LEFT', $db->quoteName('#__moloch_categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'));

        // Join over the workflow steps.
        $query->select(
            [
                $db->quoteName('s.title', 'step_title'),
                $db->quoteName('s.color', 'step_color'),
            ]
        )
            ->join('LEFT', $db->quoteName('#__moloch_steps', 's'), $db->quoteName('s.id') . ' = ' . $db->quoteName('a.stepid'));

        // Join over the users for the checked out user.
        $query->select($db->quoteName('uc.name', 'editor'))
            ->join('LEFT', $db->quoteName('#__users', 'uc'), $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'));

        // Join over the asset groups.
        $query->select($db->quoteName('ag.title', 'access_level'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'ag'), $db->quoteName('ag.id') . ' = ' . $db->quoteName('a.access'));

        // Join over the users for the author.
        $query->select(
            [
                $db->quoteName('ua.name', 'author_name'),
                $db->quoteName('ua.username', 'author_username'),
            ]
        )
            ->join('LEFT', $db->quoteName('#__users', 'ua'), $db->quoteName('ua.id') . ' = ' . $db->quoteName('a.created_by'));

        // Join over the associations.
        if (Associations::isEnabled()) {
            $subQuery = $db->getQuery(true)
                ->select('COUNT(' . $db->quoteName('asso1.id') . ') > 1')
                ->from($db->quoteName('#__associations', 'asso1'))
                ->join('INNER', $db->quoteName('#__associations', 'asso2'), $db->quoteName('asso1.key') . ' = ' . $db->quoteName('asso2.key'))
                ->where(
                    [
                        $db->quoteName('asso1.id') . ' = ' . $db->quoteName('a.id'),
                        $db->quoteName('asso1.context') . ' = ' . $db->quote('com_moloch.issue'),
                    ]
                );

            $query->select('(' . $subQuery . ') AS ' . $db->quoteName('association'));
        }

        // Filter by access level.
        $access = $this->getState('filter.access');

        if (is_numeric($access)) {
            $access = (int) $access;
            $query->where($db->quoteName('a.access') . ' = :access')
                ->bind(':access', $access, ParameterType::INTEGER);
        } elseif (is_array($access)) {
            $access = ArrayHelper::toInteger($access);
            $query->whereIn($db->quoteName('a.access'), $access);
        }

        // Implement View Level Access
        if (!$user->authorise('core.admin')) {
            $groups = $user->getAuthorisedViewLevels();
            $query->whereIn($db->quoteName('a.access'), $groups);
        }

        // Filter by published state
        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.published') . ' = :published')
                ->bind(':published', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->where($db->quoteName('a.published') . ' IN (0, 1)');
        }

        // Filter by category.
        $categoryId = $this->getState('filter.category_id');

        if (is_numeric($categoryId)) {
            $categoryId = (int) $categoryId;
            $query->where($db->quoteName('a.catid') . ' = :categoryId')
                ->bind(':categoryId', $categoryId, ParameterType::INTEGER);
        }

        // Filter by workflow step.
        $stepId = $this->getState('filter.step_id');

        if (is_numeric($stepId)) {
            $stepId = (int) $stepId;
            $query->where($db->quoteName('a.stepid') . ' = :stepId')
                ->bind(':stepId', $stepId, ParameterType::INTEGER);
        }

        // Filter by author
        $authorId = $this->getState('filter.author_id');

        if (is_numeric($authorId)) {
            $authorId = (int) $authorId;
            $query->where($db->quoteName('a.created_by') . ' = :authorId')
                ->bind(':authorId', $authorId, ParameterType::INTEGER);
        }

        // Filter by search in title and description.
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :search')
                    ->bind(':search', $search, ParameterType::INTEGER);
            } elseif (stripos($search, 'author:') === 0) {
                $search = '%' . substr($search, 7) . '%';
                $query->where(
                    '(' . $db->quoteName('ua.name') . ' LIKE :search1 OR ' . $db->quoteName('ua.username') . ' LIKE :search2)'
                )
                    ->bind([':search1', ':search2'], $search, ParameterType::STRING);
            } elseif (stripos($search, 'category:') === 0) {
                $search = '%' . substr($search, 9) . '%';
                $query->where($db->quoteName('c.title') . ' LIKE :search')
                    ->bind(':search', $search, ParameterType::STRING);
            } else {
                $search = '%' . trim($search) . '%';
                $query->where(
                    '(' . $db->quoteName('a.title') . ' LIKE :search1 OR ' . $db->quoteName('a.description') . ' LIKE :search2 OR ' . $db->quoteName('a.address') . ' LIKE :search3)'
                )
                    ->bind([':search1', ':search2', ':search3'], $search, ParameterType::STRING);
            }
        }

        // Filter by featured.
        $featured = $this->getState('filter.featured');

        switch ($featured) {
            case 'featured':
                $query->where($db->quoteName('a.featured') . ' = 1');
                break;

            case 'unfeatured':
                $query->where($db->quoteName('a.featured') . ' = 0');
                break;
        }

        // Filter by language
        if ($this->getState('filter.language')) {
            $language = $this->getState('filter.language');
            $query->where($db->quoteName('a.language') . ' = :language')
                ->bind(':language', $language, ParameterType::STRING);
        }

        // Filter on the level.
        if ($level = $this->getState('filter.level')) {
            $query->where($db->quoteName('c.level') . ' <= :level')
                ->bind(':level', (int) $level, ParameterType::INTEGER);
        }

        // Filter by tag.
        if ($tag = $this->getState('filter.tag')) {
            $query->where($db->quoteName('tagmap.tag_id') . ' = :tag')
                ->bind(':tag', (int) $tag, ParameterType::INTEGER);
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'a.created');
        $orderDirn = $this->state->get('list.direction', 'DESC');

        if ($orderCol == 'a.ordering' || $orderCol == 'category_title') {
            $ordering = [
                $db->quoteName('c.title') . ' ' . $db->escape($orderDirn),
                $db->quoteName('a.ordering') . ' ' . $db->escape($orderDirn),
            ];
        } else {
            $ordering = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);
        }

        $query->order($ordering);

        return $query;
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.access');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.category_id');
        $id .= ':' . $this->getState('filter.step_id');
        $id .= ':' . $this->getState('filter.author_id');
        $id .= ':' . $this->getState('filter.language');
        $id .= ':' . $this->getState('filter.tag');
        $id .= ':' . $this->getState('filter.level');
        $id .= ':' . $this->getState('filter.featured');

        return parent::getStoreId($id);
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $type    The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $options Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     */
    public function getTable($type = 'Issue', $prefix = 'MolochTable', $options = array())
    {
        return parent::getTable($type, $prefix, $options);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @note    Calling getState in this method will result in recursion.
     */
    protected function populateState($ordering = 'a.created', $direction = 'desc')
    {
        $app = Factory::getApplication();

        $forcedLanguage = $app->input->get('forcedLanguage', '', 'cmd');

        // Adjust the context to support modal layouts.
        if ($layout = $app->input->get('layout')) {
            $this->context .= '.' . $layout;
        }

        // Adjust the context to support forced languages.
        if ($forcedLanguage) {
            $this->context .= '.' . $forcedLanguage;
        }

        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $published = $this->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '');
        $this->setState('filter.published', $published);

        $level = $this->getUserStateFromRequest($this->context . '.filter.level', 'filter_level');
        $this->setState('filter.level', $level);

        $language = $this->getUserStateFromRequest($this->context . '.filter.language', 'filter_language', '');
        $this->setState('filter.language', $language);

        $formSubmited = $app->input->post->get('form_submited');

        $access     = $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access');
        $authorId   = $this->getUserStateFromRequest($this->context . '.filter.author_id', 'filter_author_id');
        $categoryId = $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id');
        $stepId     = $this->getUserStateFromRequest($this->context . '.filter.step_id', 'filter_step_id');
        $tag        = $this->getUserStateFromRequest($this->context . '.filter.tag', 'filter_tag', '');
        $featured   = $this->getUserStateFromRequest($this->context . '.filter.featured', 'filter_featured', '');

        if ($formSubmited) {
            $access = $app->input->post->get('access');
            $this->setState('filter.access', $access);

            $authorId = $app->input->post->get('author_id');
            $this->setState('filter.author_id', $authorId);

            $categoryId = $app->input->post->get('category_id');
            $this->setState('filter.category_id', $categoryId);

            $stepId = $app->input->post->get('step_id');
            $this->setState('filter.step_id', $stepId);

            $tag = $app->input->post->get('tag');
            $this->setState('filter.tag', $tag);

            $featured = $app->input->post->get('featured');
            $this->setState('filter.featured', $featured);
        }

        // List state information.
        parent::populateState($ordering, $direction);

        // Force a language.
        if (!empty($forcedLanguage)) {
            $this->setState('filter.language', $forcedLanguage);
        }
    }

    /**
     * Method to get an array of data items.
     *
     * @return  mixed  An array of data items on success, false on failure.
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items === false) {
            return false;
        }

        // Add additional data to items
        foreach ($items as &$item) {
            // Calculate vote score
            $item->vote_score = (int) $item->votes_up - (int) $item->votes_down;
            
            // Format coordinates
            if ($item->latitude && $item->longitude) {
                $item->coordinates = round($item->latitude, 6) . ', ' . round($item->longitude, 6);
            } else {
                $item->coordinates = Text::_('COM_MOLOCH_NO_COORDINATES');
            }

            // Truncate description for list view
            if (strlen($item->description) > 100) {
                $item->description_short = substr(strip_tags($item->description), 0, 100) . '...';
            } else {
                $item->description_short = strip_tags($item->description);
            }

            // Add attachment count
            $item->attachment_count = $this->getAttachmentCount($item->id);

            // Add comment count  
            $item->comment_count = $this->getCommentCount($item->id);
        }

        return $items;
    }

    /**
     * Get attachment count for an issue
     *
     * @param   integer  $issueId  Issue ID
     *
     * @return  integer  Attachment count
     */
    protected function getAttachmentCount($issueId)
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_attachments')
            ->where('issue_id = ' . (int) $issueId)
            ->where('published = 1');

        $db->setQuery($query);
        return (int) $db->loadResult();
    }

    /**
     * Get comment count for an issue
     *
     * @param   integer  $issueId  Issue ID
     *
     * @return  integer  Comment count
     */
    protected function getCommentCount($issueId)
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_comments')
            ->where('issue_id = ' . (int) $issueId)
            ->where('published = 1');

        $db->setQuery($query);
        return (int) $db->loadResult();
    }

    /**
     * Get categories for filter dropdown
     *
     * @return  array  Array of category objects
     */
    public function getCategories()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('id, title, color')
            ->from('#__moloch_categories')
            ->where('published = 1')
            ->order('title ASC');

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Get workflow steps for filter dropdown
     *
     * @return  array  Array of step objects
     */
    public function getSteps()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('id, title, color')
            ->from('#__moloch_steps')
            ->where('published = 1')
            ->order('ordering ASC');

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Get authors for filter dropdown
     *
     * @return  array  Array of user objects
     */
    public function getAuthors()
    {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Construct the query
        $query->select('u.id AS value, u.name AS text')
            ->from('#__users AS u')
            ->join('INNER', '#__moloch_issues AS c ON c.created_by = u.id')
            ->group('u.id, u.name')
            ->order('u.name');

        // Setup the query
        $db->setQuery($query);

        // Return the result
        return $db->loadObjectList();
    }

    /**
     * Get statistics for dashboard
     *
     * @return  object  Statistics object
     */
    public function getStatistics()
    {
        $db = $this->getDbo();
        $stats = new \stdClass();

        // Total issues
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_issues');
        $db->setQuery($query);
        $stats->total_issues = (int) $db->loadResult();

        // Published issues
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_issues')
            ->where('published = 1');
        $db->setQuery($query);
        $stats->published_issues = (int) $db->loadResult();

        // Unpublished issues
        $stats->unpublished_issues = $stats->total_issues - $stats->published_issues;

        // Featured issues
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_issues')
            ->where('featured = 1')
            ->where('published = 1');
        $db->setQuery($query);
        $stats->featured_issues = (int) $db->loadResult();

        // Total votes
        $query = $db->getQuery(true)
            ->select('SUM(votes_up + votes_down)')
            ->from('#__moloch_issues');
        $db->setQuery($query);
        $stats->total_votes = (int) $db->loadResult();

        // Total hits
        $query = $db->getQuery(true)
            ->select('SUM(hits)')
            ->from('#__moloch_issues');
        $db->setQuery($query);
        $stats->total_hits = (int) $db->loadResult();

        // Issues by category
        $query = $db->getQuery(true)
            ->select('c.title as category, COUNT(i.id) as count')
            ->from('#__moloch_categories AS c')
            ->leftjoin('#__moloch_issues AS i ON i.catid = c.id')
            ->where('c.published = 1')
            ->group('c.id, c.title')
            ->order('count DESC');
        $db->setQuery($query);
        $stats->issues_by_category = $db->loadObjectList();

        // Issues by step
        $query = $db->getQuery(true)
            ->select('s.title as step, COUNT(i.id) as count')
            ->from('#__moloch_steps AS s')
            ->leftjoin('#__moloch_issues AS i ON i.stepid = s.id')
            ->where('s.published = 1')
            ->group('s.id, s.title')
            ->order('s.ordering ASC');
        $db->setQuery($query);
        $stats->issues_by_step = $db->loadObjectList();

        // Recent activity (last 30 days)
        $query = $db->getQuery(true)
            ->select('DATE(created) as date, COUNT(*) as count')
            ->from('#__moloch_issues')
            ->where('created >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
            ->group('DATE(created)')
            ->order('date DESC');
        $db->setQuery($query);
        $stats->recent_activity = $db->loadObjectList();

        return $stats;
    }
}