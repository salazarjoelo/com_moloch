<?php
/**
 * @package     Moloch
 * @subpackage  Administrator.Template
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;

$app       = Factory::getApplication();
$user      = Factory::getUser();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$columns   = 10;

if (Associations::isEnabled()) {
    $columns++;
}

if (Multilanguage::isEnabled()) {
    $columns++;
}

$saveOrder = $listOrder === 'a.ordering';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_moloch&task=issues.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

?>

<div class="row">
    <div class="col-md-12">
        <div id="j-main-container" class="j-main-container">
            <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>
            
            <?php if (empty($this->items)) : ?>
                <div class="alert alert-info">
                    <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                    <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                </div>
            <?php else : ?>
                
                <form action="<?php echo Route::_('index.php?option=com_moloch&view=issues'); ?>" method="post" name="adminForm" id="adminForm">
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="issuesList">
                            <caption class="visually-hidden">
                                <?php echo Text::_('COM_MOLOCH_ISSUES_TABLE_CAPTION'); ?>,
                                <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                                <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                            </caption>
                            
                            <thead>
                                <tr>
                                    <td class="w-1 text-center">
                                        <?php echo HTMLHelper::_('grid.checkall'); ?>
                                    </td>
                                    
                                    <th scope="col" class="w-1 text-center d-none d-md-table-cell">
                                        <?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-1 text-center">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JFEATURED', 'a.featured', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-1 text-center">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-10 d-none d-md-table-cell">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JCATEGORY', 'category_title', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-10 d-none d-md-table-cell">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_MOLOCH_HEADING_STEP', 'step_title', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-5 d-none d-lg-table-cell text-center">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_MOLOCH_HEADING_VOTES', 'a.votes_up', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-10 d-none d-md-table-cell">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-10 d-none d-lg-table-cell">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JAUTHOR', 'author_name', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <?php if (Associations::isEnabled()) : ?>
                                        <th scope="col" class="w-5 d-none d-lg-table-cell text-center">
                                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_MOLOCH_HEADING_ASSOCIATION', 'association', $listDirn, $listOrder); ?>
                                        </th>
                                    <?php endif; ?>
                                    
                                    <?php if (Multilanguage::isEnabled()) : ?>
                                        <th scope="col" class="w-10 d-none d-lg-table-cell">
                                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_LANGUAGE', 'a.language', $listDirn, $listOrder); ?>
                                        </th>
                                    <?php endif; ?>
                                    
                                    <th scope="col" class="w-3 d-none d-lg-table-cell text-center">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JDATE', 'a.created', $listDirn, $listOrder); ?>
                                    </th>
                                    
                                    <th scope="col" class="w-3 d-none d-lg-table-cell text-center">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                    </th>
                                </tr>
                            </thead>
                            
                            <tbody<?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                                <?php foreach ($this->items as $i => $item) :
                                    $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || is_null($item->checked_out);
                                    $canChange  = $user->authorise('core.edit.state', 'com_moloch.issue.' . $item->id) && $canCheckin;
                                    $canEdit    = $user->authorise('core.edit', 'com_moloch.issue.' . $item->id) && $canCheckin;
                                ?>
                                    <tr class="row<?php echo $i % 2; ?>" data-draggable-group="<?php echo $item->catid; ?>" data-item-id="<?php echo $item->id; ?>" data-parents="<?php echo $item->catid; ?>" data-level="1">
                                        
                                        <td class="text-center">
                                            <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?>
                                        </td>
                                        
                                        <td class="text-center d-none d-md-table-cell">
                                            <?php
                                            $iconClass = '';
                                            if (!$canChange) {
                                                $iconClass = ' inactive';
                                            } elseif (!$saveOrder) {
                                                $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                            }
                                            ?>
                                            <span class="sortable-handler<?php echo $iconClass ?>">
                                                <span class="icon-menu" aria-hidden="true"></span>
                                            </span>
                                            <?php if ($canChange && $saveOrder) : ?>
                                                <input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden">
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="text-center">
                                            <?php echo HTMLHelper::_('molochAdministrator.featured', $item->featured, $i, $canChange); ?>
                                        </td>
                                        
                                        <td class="text-center">
                                            <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'issues.', $canChange, 'cb', $item->publish_up, $item->publish_down); ?>
                                        </td>
                                        
                                        <td class="has-context">
                                            <div class="break-word">
                                                <?php if ($item->checked_out) : ?>
                                                    <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'issues.', $canCheckin); ?>
                                                <?php endif; ?>
                                                
                                                <?php if ($canEdit) : ?>
                                                    <a href="<?php echo Route::_('index.php?option=com_moloch&task=issue.edit&id=' . $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape(addslashes($item->title)); ?>">
                                                        <?php echo $this->escape($item->title); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <span title="<?php echo Text::sprintf('JFIELD_ALIAS_LABEL', $this->escape($item->alias)); ?>"><?php echo $this->escape($item->title); ?></span>
                                                <?php endif; ?>
                                                
                                                <div class="small break-word">
                                                    <?php if (empty($item->note)) : ?>
                                                        <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?>
                                                    <?php else : ?>
                                                        <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS_NOTE', $this->escape($item->alias), $this->escape($item->note)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Issue description preview -->
                                                <?php if (!empty($item->description_short)) : ?>
                                                    <div class="small text-muted mt-1">
                                                        <?php echo $this->escape($item->description_short); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Location info -->
                                                <?php if (!empty($item->address)) : ?>
                                                    <div class="small text-muted mt-1">
                                                        <span class="icon-location" aria-hidden="true"></span>
                                                        <?php echo $this->escape($item->address); ?>
                                                        <?php if (!empty($item->coordinates)) : ?>
                                                            <span class="text-muted">
                                                                (<?php echo $item->coordinates; ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Attachments and comments count -->
                                                <div class="small text-muted mt-1">
                                                    <?php if ($item->attachment_count > 0) : ?>
                                                        <span class="badge bg-info me-1">
                                                            <span class="icon-paperclip" aria-hidden="true"></span>
                                                            <?php echo $item->attachment_count; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($item->comment_count > 0) : ?>
                                                        <span class="badge bg-secondary me-1">
                                                            <span class="icon-comments" aria-hidden="true"></span>
                                                            <?php echo $item->comment_count; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <span class="badge bg-light text-dark">
                                                        <span class="icon-eye" aria-hidden="true"></span>
                                                        <?php echo $item->hits; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="small d-none d-md-table-cell">
                                            <div class="break-word">
                                                <?php echo $this->escape($item->category_title); ?>
                                                <?php if (!empty($item->category_color)) : ?>
                                                    <span class="badge ms-1" style="background-color: <?php echo $item->category_color; ?>; width: 12px; height: 12px;"></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        
                                        <td class="small d-none d-md-table-cell">
                                            <div class="break-word">
                                                <span class="badge" style="background-color: <?php echo $item->step_color ?: '#6c757d'; ?>;">
                                                    <?php echo $this->escape($item->step_title); ?>
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <td class="text-center d-none d-lg-table-cell">
                                            <div class="vote-summary">
                                                <span class="badge bg-success">
                                                    <span class="icon-thumbs-up" aria-hidden="true"></span>
                                                    <?php echo $item->votes_up; ?>
                                                </span>
                                                <span class="badge bg-danger">
                                                    <span class="icon-thumbs-down" aria-hidden="true"></span>
                                                    <?php echo $item->votes_down; ?>
                                                </span>
                                                <div class="small text-muted">
                                                    <?php echo Text::_('COM_MOLOCH_SCORE'); ?>: 
                                                    <strong><?php echo $item->vote_score; ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="small d-none d-md-table-cell">
                                            <?php echo $this->escape($item->access_level); ?>
                                        </td>
                                        
                                        <td class="small d-none d-lg-table-cell">
                                            <?php if ((int) $item->created_by !== 0) : ?>
                                                <div class="break-word">
                                                    <?php if ($item->author_name) : ?>
                                                        <?php echo $this->escape($item->author_name); ?>
                                                    <?php else : ?>
                                                        <?php echo $this->escape($item->author_username); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else : ?>
                                                <?php if ($item->created_by_alias) : ?>
                                                    <div class="break-word">
                                                        <?php echo $this->escape($item->created_by_alias); ?>
                                                    </div>
                                                <?php else : ?>
                                                    <span class="text-muted"><?php echo Text::_('COM_MOLOCH_GUEST'); ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <?php if (Associations::isEnabled()) : ?>
                                            <td class="text-center d-none d-lg-table-cell">
                                                <?php if ($item->association) : ?>
                                                    <?php echo HTMLHelper::_('molochAdministrator.association', $item->id); ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <?php if (Multilanguage::isEnabled()) : ?>
                                            <td class="small d-none d-lg-table-cell">
                                                <?php echo LayoutHelper::render('joomla.content.language', $item); ?>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <td class="text-center d-none d-lg-table-cell">
                                            <time datetime="<?php echo HTMLHelper::_('date', $item->created, 'c'); ?>" title="<?php echo HTMLHelper::_('date', $item->created, 'l, d F Y H:i:s'); ?>">
                                                <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?>
                                            </time>
                                        </td>
                                        
                                        <td class="text-center d-none d-lg-table-cell">
                                            <?php echo (int) $item->id; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Load the batch processing form if user has edit privileges -->
                    <?php if ($user->authorise('core.edit', 'com_moloch') && $user->authorise('core.edit.state', 'com_moloch')) : ?>
                        <?php echo HTMLHelper::_(
                            'bootstrap.renderModal',
                            'collapseModal',
                            array(
                                'title'  => Text::_('COM_MOLOCH_BATCH_OPTIONS'),
                                'footer' => $this->loadTemplate('batch_footer'),
                            ),
                            $this->loadTemplate('batch_body')
                        ); ?>
                    <?php endif; ?>

                    <input type="hidden" name="task" value="">
                    <input type="hidden" name="boxchecked" value="0">
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>

                <!-- Pagination -->
                <?php echo $this->pagination->getListFooter(); ?>

            <?php endif; ?>
        </div>
    </div>
</div>