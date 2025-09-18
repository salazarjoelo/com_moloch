<?php
/**
 * @package     Moloch
 * @subpackage  Site.Template
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$item = $this->item ?? $item;
$user = Factory::getUser();
$canEdit = $user->authorise('core.edit', 'com_moloch') || ($user->authorise('core.edit.own', 'com_moloch') && $item->created_by == $user->id);

?>

<div class="moloch-issue-item" data-issue-id="<?php echo $item->id; ?>">
    <div class="card h-100 shadow-sm">
        
        <!-- Issue Header -->
        <div class="card-header bg-transparent border-bottom-0">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <a href="<?php echo Route::_('index.php?option=com_moloch&view=issue&id=' . $item->id); ?>" 
                           class="text-decoration-none">
                            <?php echo $this->escape($item->title); ?>
                        </a>
                    </h5>
                </div>
                <div class="col-auto">
                    <?php if ($canEdit): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                    type="button" data-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" 
                                   href="<?php echo Route::_('index.php?option=com_moloch&view=form&layout=edit&id=' . $item->id); ?>">
                                    <i class="fas fa-edit"></i> <?php echo Text::_('COM_MOLOCH_EDIT'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            
            <!-- Category and Status Badges -->
            <div class="mb-3">
                <span class="badge badge-category" 
                      style="background-color: <?php echo $item->category_color ?: '#3498db'; ?>;">
                    <?php echo $this->escape($item->category_title); ?>
                </span>
                
                <span class="badge badge-status ml-2" 
                      style="background-color: <?php echo $item->step_color ?: '#6c757d'; ?>;">
                    <?php echo $this->escape($item->step_title); ?>
                </span>
                
                <?php if ($item->featured): ?>
                    <span class="badge badge-warning ml-2">
                        <i class="fas fa-star"></i> <?php echo Text::_('COM_MOLOCH_FEATURED'); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Description -->
            <p class="card-text text-muted mb-3">
                <?php echo HTMLHelper::_('string.truncate', strip_tags($item->description), 150); ?>
            </p>
            
            <!-- Address -->
            <?php if ($item->address): ?>
                <div class="address mb-3">
                    <small class="text-muted">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo $this->escape($item->address); ?>
                    </small>
                </div>
            <?php endif; ?>
            
            <!-- Thumbnails of attachments -->
            <?php if (!empty($item->attachments)): ?>
                <div class="attachments-preview mb-3">
                    <div class="row no-gutters">
                        <?php $imageCount = 0; ?>
                        <?php foreach ($item->attachments as $attachment): ?>
                            <?php if ($attachment->file_type === 'image' && $imageCount < 4): ?>
                                <div class="col-3 pr-1">
                                    <div class="attachment-thumb">
                                        <img src="<?php echo Uri::root() . $attachment->thumbnail ?: $attachment->filepath; ?>" 
                                             alt="<?php echo $this->escape($attachment->original_filename); ?>"
                                             class="img-fluid rounded">
                                    </div>
                                </div>
                                <?php $imageCount++; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if (count($item->attachments) > 4): ?>
                            <div class="col-3">
                                <div class="more-attachments d-flex align-items-center justify-content-center bg-light rounded h-100">
                                    <small class="text-muted">
                                        +<?php echo count($item->attachments) - 4; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Card Footer -->
        <div class="card-footer bg-transparent">
            <div class="row align-items-center">
                
                <!-- Voting -->
                <div class="col-auto">
                    <?php if ($this->params->get('enable_voting', 1)): ?>
                        <div class="voting-buttons">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-success vote-btn" 
                                    data-issue-id="<?php echo $item->id; ?>" 
                                    data-vote="1">
                                <i class="fas fa-thumbs-up"></i>
                                <span class="vote-count"><?php echo $item->votes_up; ?></span>
                            </button>
                            
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger vote-btn ml-1" 
                                    data-issue-id="<?php echo $item->id; ?>" 
                                    data-vote="-1">
                                <i class="fas fa-thumbs-down"></i>
                                <span class="vote-count"><?php echo $item->votes_down; ?></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Comments Count -->
                <?php if ($this->params->get('enable_comments', 1)): ?>
                    <div class="col-auto">
                        <small class="text-muted">
                            <i class="fas fa-comments"></i>
                            <?php echo count($item->comments ?? []); ?>
                        </small>
                    </div>
                <?php endif; ?>
                
                <!-- Views -->
                <div class="col-auto">
                    <small class="text-muted">
                        <i class="fas fa-eye"></i>
                        <?php echo $item->hits; ?>
                    </small>
                </div>
                
                <!-- Date -->
                <div class="col text-right">
                    <small class="text-muted">
                        <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC3')); ?>
                    </small>
                </div>
                
            </div>
            
            <!-- Author -->
            <div class="row mt-2">
                <div class="col">
                    <small class="text-muted">
                        <?php echo Text::_('COM_MOLOCH_REPORTED_BY'); ?>: 
                        <?php if ($item->created_by): ?>
                            <?php echo $this->escape($item->author ?: $item->author_username); ?>
                        <?php else: ?>
                            <?php echo $this->escape($item->created_by_alias ?: Text::_('COM_MOLOCH_GUEST')); ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-auto">
                    <a href="<?php echo Route::_('index.php?option=com_moloch&view=issue&id=' . $item->id); ?>" 
                       class="btn btn-primary btn-sm">
                        <?php echo Text::_('COM_MOLOCH_VIEW_DETAILS'); ?>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>