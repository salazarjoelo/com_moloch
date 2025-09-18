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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\User\User;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Log\Log;
use Joomla\Utilities\IpHelper;

/**
 * Moloch Component Issue Model
 */
class IssueModel extends ItemModel
{
    /**
     * Model context string
     *
     * @var string
     */
    public $_context = 'com_moloch.issue';

    /**
     * Method to get an ojbect
     *
     * @param   integer  $id  The id of the object to retrieve
     *
     * @return  mixed    Object on success, false on failure
     */
    public function getItem($id = null)
    {
        if ($this->_item === null) {
            $this->_item = array();
        }

        if (!isset($this->_item[$id])) {
            try {
                $db = $this->getDbo();
                $query = $db->getQuery(true)
                    ->select(
                        $this->getState(
                            'item.select',
                            'a.*, ' .
                            'c.title AS category_title, c.alias AS category_alias, c.color AS category_color, ' .
                            's.title AS step_title, s.alias AS step_alias, s.color AS step_color, ' .
                            'u.name AS author, u.username AS author_username'
                        )
                    );
                $query->from('#__moloch_issues AS a')
                    ->leftjoin('#__moloch_categories AS c ON c.id = a.catid')
                    ->leftjoin('#__moloch_steps AS s ON s.id = a.stepid')
                    ->leftjoin('#__users AS u ON u.id = a.created_by')
                    ->where('a.id = ' . (int) $id);

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    throw new \Exception(Text::_('COM_MOLOCH_ERROR_ISSUE_NOT_FOUND'), 404);
                }

                // Check published state
                if (!$data->published && !$this->canEdit($data)) {
                    throw new \Exception(Text::_('COM_MOLOCH_ERROR_ISSUE_NOT_PUBLISHED'), 403);
                }

                // Convert parameter fields to objects
                $data->params = new Registry($data->params ?? '{}');
                $data->metadata = new Registry($data->metadata ?? '{}');

                // Get attachments
                $data->attachments = $this->getAttachments($id);

                // Get comments
                $data->comments = $this->getComments($id);

                // Get vote count
                $data->vote_summary = $this->getVoteSummary($id);

                // Update hit counter
                $this->hit($id);

                $this->_item[$id] = $data;

            } catch (\Exception $e) {
                $this->setError($e);
                $this->_item[$id] = false;
            }
        }

        return $this->_item[$id];
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
     * Method to get comments for an issue
     *
     * @param   integer  $issueId  The issue ID
     *
     * @return  array    Array of comment objects
     */
    public function getComments($issueId)
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select(
                'c.*, u.name AS author_name, u.username AS author_username'
            )
            ->from('#__moloch_comments AS c')
            ->leftjoin('#__users AS u ON u.id = c.created_by')
            ->where('c.issue_id = ' . (int) $issueId)
            ->where('c.published = 1')
            ->order('c.created ASC');

        $db->setQuery($query);
        $comments = $db->loadObjectList() ?: array();

        // Organize comments into threaded structure
        return $this->organizeComments($comments);
    }

    /**
     * Organize comments into threaded structure
     *
     * @param   array  $comments  Flat array of comments
     *
     * @return  array  Threaded array of comments
     */
    protected function organizeComments($comments)
    {
        $organized = array();
        $commentsByParent = array();

        // Group comments by parent_id
        foreach ($comments as $comment) {
            $parentId = $comment->parent_id ?: 0;
            if (!isset($commentsByParent[$parentId])) {
                $commentsByParent[$parentId] = array();
            }
            $commentsByParent[$parentId][] = $comment;
        }

        // Build threaded structure
        return $this->buildCommentTree(0, $commentsByParent);
    }

    /**
     * Build comment tree recursively
     *
     * @param   integer  $parentId           Parent comment ID
     * @param   array    $commentsByParent   Comments grouped by parent
     * @param   integer  $level              Current nesting level
     *
     * @return  array    Threaded comments
     */
    protected function buildCommentTree($parentId, $commentsByParent, $level = 0)
    {
        $tree = array();

        if (isset($commentsByParent[$parentId])) {
            foreach ($commentsByParent[$parentId] as $comment) {
                $comment->level = $level;
                $comment->children = $this->buildCommentTree($comment->id, $commentsByParent, $level + 1);
                $tree[] = $comment;
            }
        }

        return $tree;
    }

    /**
     * Get vote summary for an issue
     *
     * @param   integer  $issueId  The issue ID
     *
     * @return  object   Vote summary object
     */
    public function getVoteSummary($issueId)
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total_votes')
            ->select('SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as votes_up')
            ->select('SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as votes_down')
            ->from('#__moloch_votes')
            ->where('issue_id = ' . (int) $issueId);

        $db->setQuery($query);
        $summary = $db->loadObject();

        return (object) array(
            'total_votes' => (int) ($summary->total_votes ?? 0),
            'votes_up' => (int) ($summary->votes_up ?? 0),
            'votes_down' => (int) ($summary->votes_down ?? 0),
            'score' => (int) (($summary->votes_up ?? 0) - ($summary->votes_down ?? 0))
        );
    }

    /**
     * Method to vote on an issue
     *
     * @param   integer  $issueId  The issue ID
     * @param   integer  $vote     The vote (1 or -1)
     *
     * @return  boolean  True on success
     */
    public function vote($issueId, $vote)
    {
        $user = Factory::getUser();
        $params = ComponentHelper::getParams('com_moloch');

        // Check if voting is enabled
        if (!$params->get('enable_voting', 1)) {
            $this->setError(Text::_('COM_MOLOCH_ERROR_VOTING_DISABLED'));
            return false;
        }

        // Validate vote value
        if (!in_array($vote, [1, -1])) {
            $this->setError(Text::_('COM_MOLOCH_ERROR_INVALID_VOTE'));
            return false;
        }

        $db = $this->getDbo();
        $userIp = IpHelper::getIp();
        $userId = $user->get('id', 0);

        try {
            // Check if user/IP has already voted
            $query = $db->getQuery(true)
                ->select('id, vote')
                ->from('#__moloch_votes')
                ->where('issue_id = ' . (int) $issueId);

            if ($userId) {
                $query->where('user_id = ' . (int) $userId);
            } else {
                $query->where('user_ip = ' . $db->quote($userIp));
            }

            $db->setQuery($query);
            $existingVote = $db->loadObject();

            if ($existingVote) {
                // Update existing vote
                if ($existingVote->vote == $vote) {
                    $this->setError(Text::_('COM_MOLOCH_ERROR_ALREADY_VOTED'));
                    return false;
                }

                $query = $db->getQuery(true)
                    ->update('#__moloch_votes')
                    ->set('vote = ' . (int) $vote)
                    ->set('created = NOW()')
                    ->where('id = ' . (int) $existingVote->id);

                $db->setQuery($query);
                $db->execute();
            } else {
                // Insert new vote
                $query = $db->getQuery(true)
                    ->insert('#__moloch_votes')
                    ->columns(['issue_id', 'user_id', 'user_ip', 'vote', 'created'])
                    ->values(implode(',', [
                        (int) $issueId,
                        (int) $userId,
                        $db->quote($userIp),
                        (int) $vote,
                        $db->quote(Factory::getDate()->toSql())
                    ]));

                $db->setQuery($query);
                $db->execute();
            }

            // Update issue vote counters
            $this->updateIssueVoteCounters($issueId);

            // Log the action
            $this->logAction($issueId, 'vote', 'User voted ' . ($vote > 0 ? 'up' : 'down'));

            return true;

        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Update issue vote counters
     *
     * @param   integer  $issueId  The issue ID
     *
     * @return  void
     */
    protected function updateIssueVoteCounters($issueId)
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as votes_up')
            ->select('SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as votes_down')
            ->from('#__moloch_votes')
            ->where('issue_id = ' . (int) $issueId);

        $db->setQuery($query);
        $counts = $db->loadObject();

        $query = $db->getQuery(true)
            ->update('#__moloch_issues')
            ->set('votes_up = ' . (int) ($counts->votes_up ?? 0))
            ->set('votes_down = ' . (int) ($counts->votes_down ?? 0))
            ->where('id = ' . (int) $issueId);

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Method to hit an issue
     *
     * @param   integer  $pk  The id of the primary key
     *
     * @return  boolean  True if successful
     */
    public function hit($pk = 0)
    {
        $hitcount = $this->getState('item.hitcount', 1);

        if ($hitcount) {
            $pk = (!empty($pk)) ? $pk : (int) $this->getState('item.id');

            $db = $this->getDbo();
            $db->setQuery(
                'UPDATE #__moloch_issues SET hits = hits + 1 WHERE id = ' . (int) $pk
            );

            if (!$db->execute()) {
                $this->setError($db->getErrorMsg());
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user can edit an issue
     *
     * @param   object  $issue  The issue object
     *
     * @return  boolean  True if user can edit
     */
    public function canEdit($issue)
    {
        $user = Factory::getUser();

        if ($user->authorise('core.edit', 'com_moloch')) {
            return true;
        }

        if ($user->authorise('core.edit.own', 'com_moloch') && $issue->created_by == $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Log an action on an issue
     *
     * @param   integer  $issueId      The issue ID
     * @param   string   $action       The action performed
     * @param   string   $description  Action description
     * @param   mixed    $oldValue     Old value (if applicable)
     * @param   mixed    $newValue     New value (if applicable)
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
            // Log error but don't fail the main operation
            Log::add('Failed to log action: ' . $e->getMessage(), Log::WARNING, 'com_moloch');
        }
    }

    /**
     * Method to get the table
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
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
        $app = Factory::getApplication('site');

        // Load the object state
        $id = $app->input->getInt('id');
        $this->setState('item.id', $id);

        // Load the parameters
        $params = $app->getParams();
        $this->setState('params', $params);

        // Set the hit counter
        $this->setState('item.hitcount', $app->input->getUint('hitcount', 1));
    }
}