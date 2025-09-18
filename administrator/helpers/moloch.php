<?php
/**
 * @package     Moloch
 * @subpackage  Administrator.Helper
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Table\Table;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/**
 * Moloch component helper
 */
class MolochHelper extends CMSObject
{
    /**
     * Configure the Linkbar.
     *
     * @param   string  $vName  The name of the active view.
     *
     * @return  void
     */
    public static function addSubmenu($vName)
    {
        JHtmlSidebar::addEntry(
            Text::_('COM_MOLOCH_SUBMENU_DASHBOARD'),
            'index.php?option=com_moloch&view=dashboard',
            $vName == 'dashboard'
        );

        JHtmlSidebar::addEntry(
            Text::_('COM_MOLOCH_SUBMENU_ISSUES'),
            'index.php?option=com_moloch&view=issues',
            $vName == 'issues'
        );

        JHtmlSidebar::addEntry(
            Text::_('COM_MOLOCH_SUBMENU_CATEGORIES'),
            'index.php?option=com_moloch&view=categories',
            $vName == 'categories'
        );

        JHtmlSidebar::addEntry(
            Text::_('COM_MOLOCH_SUBMENU_STEPS'),
            'index.php?option=com_moloch&view=steps',
            $vName == 'steps'
        );

        JHtmlSidebar::addEntry(
            Text::_('COM_MOLOCH_SUBMENU_ATTACHMENTS'),
            'index.php?option=com_moloch&view=attachments',
            $vName == 'attachments'
        );

        JHtmlSidebar::addEntry(
            Text::_('COM_MOLOCH_SUBMENU_LOGS'),
            'index.php?option=com_moloch&view=logs',
            $vName == 'logs'
        );
    }

    /**
     * Gets a list of the actions that can be performed.
     *
     * @param   integer  $categoryId  The category ID.
     * @param   integer  $issueId     The issue ID.
     *
     * @return  CMSObject
     */
    public static function getActions($categoryId = 0, $issueId = 0)
    {
        $user   = Factory::getUser();
        $result = new CMSObject;

        if (empty($issueId) && empty($categoryId)) {
            $assetName = 'com_moloch';
        } elseif (empty($issueId)) {
            $assetName = 'com_moloch.category.' . (int) $categoryId;
        } else {
            $assetName = 'com_moloch.issue.' . (int) $issueId;
        }

        $actions = array(
            'core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.own', 'core.edit.state', 'core.delete'
        );

        foreach ($actions as $action) {
            $result->set($action, $user->authorise($action, $assetName));
        }

        return $result;
    }

    /**
     * Get categories for dropdown
     *
     * @param   boolean  $published  Only published categories
     *
     * @return  array    Array of category objects
     */
    public static function getCategories($published = true)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title, color, level')
            ->from('#__moloch_categories')
            ->order('title ASC');

        if ($published) {
            $query->where('published = 1');
        }

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Get workflow steps for dropdown
     *
     * @param   boolean  $published  Only published steps
     *
     * @return  array    Array of step objects
     */
    public static function getSteps($published = true)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title, color, ordering')
            ->from('#__moloch_steps')
            ->order('ordering ASC, title ASC');

        if ($published) {
            $query->where('published = 1');
        }

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Get authors for dropdown
     *
     * @return  array  Array of author objects
     */
    public static function getAuthors()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('DISTINCT u.id, u.name, u.username')
            ->from('#__users AS u')
            ->join('INNER', '#__moloch_issues AS i ON i.created_by = u.id')
            ->order('u.name ASC');

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Get component statistics
     *
     * @return  object  Statistics object
     */
    public static function getStatistics()
    {
        $db = Factory::getDbo();
        $stats = new stdClass();

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

        // Total categories
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_categories')
            ->where('published = 1');
        $db->setQuery($query);
        $stats->total_categories = (int) $db->loadResult();

        // Total steps
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_steps')
            ->where('published = 1');
        $db->setQuery($query);
        $stats->total_steps = (int) $db->loadResult();

        // Total votes
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_votes');
        $db->setQuery($query);
        $stats->total_votes = (int) $db->loadResult();

        // Total attachments
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_attachments')
            ->where('published = 1');
        $db->setQuery($query);
        $stats->total_attachments = (int) $db->loadResult();

        // Total comments
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__moloch_comments')
            ->where('published = 1');
        $db->setQuery($query);
        $stats->total_comments = (int) $db->loadResult();

        // Issues by category (top 5)
        $query = $db->getQuery(true)
            ->select('c.title, c.color, COUNT(i.id) AS count')
            ->from('#__moloch_categories AS c')
            ->leftjoin('#__moloch_issues AS i ON i.catid = c.id AND i.published = 1')
            ->where('c.published = 1')
            ->group('c.id, c.title, c.color')
            ->order('count DESC')
            ->setLimit(5);
        $db->setQuery($query);
        $stats->issues_by_category = $db->loadObjectList();

        // Issues by step
        $query = $db->getQuery(true)
            ->select('s.title, s.color, COUNT(i.id) AS count')
            ->from('#__moloch_steps AS s')
            ->leftjoin('#__moloch_issues AS i ON i.stepid = s.id AND i.published = 1')
            ->where('s.published = 1')
            ->group('s.id, s.title, s.color')
            ->order('s.ordering ASC');
        $db->setQuery($query);
        $stats->issues_by_step = $db->loadObjectList();

        // Recent activity (last 30 days)
        $query = $db->getQuery(true)
            ->select('DATE(created) AS date, COUNT(*) AS count')
            ->from('#__moloch_issues')
            ->where('created >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
            ->group('DATE(created)')
            ->order('date ASC');
        $db->setQuery($query);
        $stats->recent_activity = $db->loadObjectList();

        // Most active users (top 10)
        $query = $db->getQuery(true)
            ->select('u.name, u.username, COUNT(i.id) AS issue_count')
            ->from('#__users AS u')
            ->join('INNER', '#__moloch_issues AS i ON i.created_by = u.id')
            ->where('i.published = 1')
            ->group('u.id, u.name, u.username')
            ->order('issue_count DESC')
            ->setLimit(10);
        $db->setQuery($query);
        $stats->most_active_users = $db->loadObjectList();

        return $stats;
    }

    /**
     * Format file size
     *
     * @param   integer  $bytes  File size in bytes
     *
     * @return  string   Formatted file size
     */
    public static function formatFileSize($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file type icon
     *
     * @param   string  $fileType  File type
     * @param   string  $mimeType  MIME type
     *
     * @return  string  Icon class
     */
    public static function getFileTypeIcon($fileType, $mimeType = '')
    {
        switch ($fileType) {
            case 'image':
                return 'icon-image';
            case 'video':
                return 'icon-video';
            case 'audio':
                return 'icon-music';
            case 'document':
                if (strpos($mimeType, 'pdf') !== false) {
                    return 'icon-file-pdf';
                } elseif (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) {
                    return 'icon-file-word';
                } elseif (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) {
                    return 'icon-file-excel';
                } elseif (strpos($mimeType, 'powerpoint') !== false || strpos($mimeType, 'presentation') !== false) {
                    return 'icon-file-powerpoint';
                } else {
                    return 'icon-file-text';
                }
            default:
                return 'icon-file';
        }
    }

    /**
     * Get category badge HTML
     *
     * @param   object  $category  Category object
     *
     * @return  string  HTML badge
     */
    public static function getCategoryBadge($category)
    {
        if (!$category) {
            return '';
        }

        return '<span class="badge" style="background-color: ' . ($category->color ?: '#3498db') . ';">'
            . htmlspecialchars($category->title)
            . '</span>';
    }

    /**
     * Get step badge HTML
     *
     * @param   object  $step  Step object
     *
     * @return  string  HTML badge
     */
    public static function getStepBadge($step)
    {
        if (!$step) {
            return '';
        }

        return '<span class="badge" style="background-color: ' . ($step->color ?: '#6c757d') . ';">'
            . htmlspecialchars($step->title)
            . '</span>';
    }

    /**
     * Get vote summary HTML
     *
     * @param   integer  $votesUp    Up votes
     * @param   integer  $votesDown  Down votes
     *
     * @return  string   HTML vote summary
     */
    public static function getVoteSummary($votesUp, $votesDown)
    {
        $score = $votesUp - $votesDown;
        $total = $votesUp + $votesDown;

        $html = '<div class="vote-summary">';
        $html .= '<span class="badge bg-success"><i class="icon-thumbs-up"></i> ' . $votesUp . '</span> ';
        $html .= '<span class="badge bg-danger"><i class="icon-thumbs-down"></i> ' . $votesDown . '</span>';
        
        if ($total > 0) {
            $html .= '<div class="small text-muted">';
            $html .= Text::_('COM_MOLOCH_SCORE') . ': <strong>' . $score . '</strong>';
            $html .= '</div>';
        }
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Truncate text safely
     *
     * @param   string   $text    Text to truncate
     * @param   integer  $limit   Character limit
     * @param   string   $suffix  Suffix to add
     *
     * @return  string   Truncated text
     */
    public static function truncateText($text, $limit = 150, $suffix = '...')
    {
        $text = strip_tags($text);
        
        if (strlen($text) <= $limit) {
            return $text;
        }

        // Find the last space within the limit
        $lastSpace = strrpos(substr($text, 0, $limit), ' ');
        
        if ($lastSpace !== false) {
            return substr($text, 0, $lastSpace) . $suffix;
        }

        return substr($text, 0, $limit) . $suffix;
    }

    /**
     * Generate color variations
     *
     * @param   string  $color     Base color (hex)
     * @param   float   $percent   Percentage to lighten/darken
     *
     * @return  string  Modified color
     */
    public static function adjustColor($color, $percent)
    {
        $color = ltrim($color, '#');
        
        if (strlen($color) == 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        $r = hexdec($color[0] . $color[1]);
        $g = hexdec($color[2] . $color[3]);
        $b = hexdec($color[4] . $color[5]);

        $r = round($r * (1 + $percent / 100));
        $g = round($g * (1 + $percent / 100));
        $b = round($b * (1 + $percent / 100));

        $r = min(255, max(0, $r));
        $g = min(255, max(0, $g));
        $b = min(255, max(0, $b));

        return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Clean and validate coordinates
     *
     * @param   mixed  $latitude   Latitude value
     * @param   mixed  $longitude  Longitude value
     *
     * @return  array|null  Validated coordinates or null
     */
    public static function validateCoordinates($latitude, $longitude)
    {
        if (empty($latitude) || empty($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return array(
            'latitude' => $lat,
            'longitude' => $lng
        );
    }

    /**
     * Calculate distance between two coordinates
     *
     * @param   float  $lat1  Latitude 1
     * @param   float  $lng1  Longitude 1
     * @param   float  $lat2  Latitude 2
     * @param   float  $lng2  Longitude 2
     * @param   string $unit  Unit (K for kilometers, M for miles)
     *
     * @return  float  Distance
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2, $unit = 'K')
    {
        if ($lat1 == $lat2 && $lng1 == $lng2) {
            return 0;
        }

        $theta = $lng1 - $lng2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        switch ($unit) {
            case 'K':
                return $miles * 1.609344;
            case 'N':
                return $miles * 0.8684;
            default:
                return $miles;
        }
    }

    /**
     * Get recent logs for an issue
     *
     * @param   integer  $issueId  Issue ID
     * @param   integer  $limit    Number of logs to return
     *
     * @return  array    Array of log objects
     */
    public static function getRecentLogs($issueId, $limit = 10)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('l.*, u.name AS user_name, u.username')
            ->from('#__moloch_logs AS l')
            ->leftjoin('#__users AS u ON u.id = l.created_by')
            ->where('l.issue_id = ' . (int) $issueId)
            ->order('l.created DESC')
            ->setLimit((int) $limit);

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Clean cache for component
     *
     * @return  void
     */
    public static function cleanCache()
    {
        $cache = Factory::getCache('com_moloch');
        $cache->clean();

        // Also clean related caches
        $cacheGroups = ['com_moloch_categories', 'com_moloch_steps', 'com_moloch_issues'];
        
        foreach ($cacheGroups as $group) {
            $cache = Factory::getCache($group);
            $cache->clean();
        }
    }

    /**
     * Get component version
     *
     * @return  string  Component version
     */
    public static function getVersion()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('manifest_cache')
            ->from('#__extensions')
            ->where('element = ' . $db->quote('com_moloch'))
            ->where('type = ' . $db->quote('component'));

        $db->setQuery($query);
        $manifest = $db->loadResult();

        if ($manifest) {
            $manifestData = json_decode($manifest, true);
            return $manifestData['version'] ?? '1.0.0';
        }

        return '1.0.0';
    }

    /**
     * Check if user can perform action
     *
     * @param   string   $action     Action to check
     * @param   integer  $assetId    Asset ID
     * @param   integer  $userId     User ID (0 for current user)
     *
     * @return  boolean  True if user can perform action
     */
    public static function canDo($action, $assetId = null, $userId = 0)
    {
        $user = $userId ? Factory::getUser($userId) : Factory::getUser();
        
        $asset = 'com_moloch';
        if ($assetId) {
            $asset .= '.issue.' . $assetId;
        }

        return $user->authorise($action, $asset);
    }
}