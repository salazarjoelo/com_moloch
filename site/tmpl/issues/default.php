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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers');

$app = Factory::getApplication();
$user = Factory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$itemsCount = count($this->items);
$mapPosition = $this->getMapPosition();
$layoutType = $this->getLayoutType();

?>

<div id="moloch-issues" class="moloch-issues">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <?php echo $this->escape($this->params->get('page_heading')); ?>
                    </h1>
                    <?php if ($this->params->get('show_description', 1)): ?>
                        <p class="page-description">
                            <?php echo Text::_('COM_MOLOCH_ISSUES_PAGE_DESCRIPTION'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4 text-right">
                    <?php if ($this->canAdd()): ?>
                        <a href="<?php echo Route::_('index.php?option=com_moloch&view=form&layout=edit'); ?>" 
                           class="btn btn-primary btn-lg">
                            <i class="fas fa-plus"></i>
                            <?php echo Text::_('COM_MOLOCH_REPORT_ISSUE'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        
        <!-- Filters -->
        <?php if ($this->showFilters()): ?>
            <div class="row">
                <div class="col-12">
                    <div class="moloch-filters card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-filter"></i>
                                <?php echo Text::_('COM_MOLOCH_FILTERS'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" 
                                  method="post" name="adminForm" id="adminForm">
                                
                                <div class="row">
                                    <!-- Search -->
                                    <div class="col-md-3 mb-3">
                                        <div class="input-group">
                                            <input type="text" 
                                                   name="filter[search]" 
                                                   id="filter_search"
                                                   value="<?php echo $this->escape($this->state->get('filter.search')); ?>"
                                                   class="form-control" 
                                                   placeholder="<?php echo Text::_('COM_MOLOCH_FILTER_SEARCH_PLACEHOLDER'); ?>">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="submit">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Category Filter -->
                                    <div class="col-md-3 mb-3">
                                        <select name="filter[category]" id="filter_category" class="form-control">
                                            <option value=""><?php echo Text::_('COM_MOLOCH_SELECT_CATEGORY'); ?></option>
                                            <?php foreach ($this->categories as $category): ?>
                                                <option value="<?php echo $category->id; ?>"
                                                    <?php echo $this->state->get('filter.category') == $category->id ? 'selected' : ''; ?>>
                                                    <?php echo $this->escape($category->title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Status Filter -->
                                    <div class="col-md-3 mb-3">
                                        <select name="filter[step]" id="filter_step" class="form-control">
                                            <option value=""><?php echo Text::_('COM_MOLOCH_SELECT_STATUS'); ?></option>
                                            <?php foreach ($this->steps as $step): ?>
                                                <option value="<?php echo $step->id; ?>"
                                                    <?php echo $this->state->get('filter.step') == $step->id ? 'selected' : ''; ?>>
                                                    <?php echo $this->escape($step->title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Order By -->
                                    <div class="col-md-3 mb-3">
                                        <select name="filter[ordering]" id="filter_ordering" class="form-control">
                                            <option value="a.created DESC" <?php echo $listOrder == 'a.created' && $listDirn == 'DESC' ? 'selected' : ''; ?>>
                                                <?php echo Text::_('COM_MOLOCH_ORDER_NEWEST'); ?>
                                            </option>
                                            <option value="a.created ASC" <?php echo $listOrder == 'a.created' && $listDirn == 'ASC' ? 'selected' : ''; ?>>
                                                <?php echo Text::_('COM_MOLOCH_ORDER_OLDEST'); ?>
                                            </option>
                                            <option value="a.votes_up DESC" <?php echo $listOrder == 'a.votes_up' && $listDirn == 'DESC' ? 'selected' : ''; ?>>
                                                <?php echo Text::_('COM_MOLOCH_ORDER_MOST_VOTED'); ?>
                                            </option>
                                            <option value="a.title ASC" <?php echo $listOrder == 'a.title' && $listDirn == 'ASC' ? 'selected' : ''; ?>>
                                                <?php echo Text::_('COM_MOLOCH_ORDER_ALPHABETICAL'); ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter"></i>
                                            <?php echo Text::_('COM_MOLOCH_APPLY_FILTERS'); ?>
                                        </button>
                                        <a href="<?php echo Route::_('index.php?option=com_moloch&view=issues'); ?>" 
                                           class="btn btn-outline-secondary ml-2">
                                            <i class="fas fa-times"></i>
                                            <?php echo Text::_('COM_MOLOCH_CLEAR_FILTERS'); ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="task" value="" />
                                <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
                                <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
                                <?php echo HTMLHelper::_('form.token'); ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Map (Top Position) -->
        <?php if ($this->showMap() && $mapPosition === 'top'): ?>
            <div class="row">
                <div class="col-12">
                    <div class="moloch-map-container mb-4">
                        <?php echo $this->loadTemplate('map'); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Content Row -->
        <div class="row">
            
            <!-- Map (Left Position) -->
            <?php if ($this->showMap() && $mapPosition === 'left'): ?>
                <div class="col-lg-6">
                    <div class="moloch-map-container">
                        <?php echo $this->loadTemplate('map'); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Issues List -->
            <div class="<?php echo $this->showMap() && in_array($mapPosition, ['left', 'right']) ? 'col-lg-6' : 'col-12'; ?>">
                
                <!-- Results Count -->
                <div class="results-info d-flex justify-content-between align-items-center mb-3">
                    <div class="results-count">
                        <strong><?php echo $itemsCount; ?></strong>
                        <?php echo $itemsCount === 1 ? Text::_('COM_MOLOCH_ISSUE_FOUND') : Text::_('COM_MOLOCH_ISSUES_FOUND'); ?>
                    </div>
                    
                    <div class="view-toggle">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary <?php echo $layoutType === 'list' ? 'active' : ''; ?>" 
                                    data-layout="list">
                                <i class="fas fa-list"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary <?php echo $layoutType === 'grid' ? 'active' : ''; ?>" 
                                    data-layout="grid">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Issues -->
                <?php if (empty($this->items)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                        <h4><?php echo Text::_('COM_MOLOCH_NO_ISSUES_FOUND'); ?></h4>
                        <p><?php echo Text::_('COM_MOLOCH_NO_ISSUES_FOUND_DESC'); ?></p>
                        <?php if ($this->canAdd()): ?>
                            <a href="<?php echo Route::_('index.php?option=com_moloch&view=form&layout=edit'); ?>" 
                               class="btn btn-primary mt-2">
                                <?php echo Text::_('COM_MOLOCH_REPORT_FIRST_ISSUE'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div id="issues-container" class="moloch-issues-container layout-<?php echo $layoutType; ?>">
                        <?php foreach ($this->items as $i => $item): ?>
                            <?php echo $this->loadTemplate('item', array('item' => $item, 'index' => $i)); ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($this->pagination->pagesTotal > 1): ?>
                        <div class="moloch-pagination mt-4">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Map (Right Position) -->
            <?php if ($this->showMap() && $mapPosition === 'right'): ?>
                <div class="col-lg-6">
                    <div class="moloch-map-container">
                        <?php echo $this->loadTemplate('map'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Map (Bottom Position) -->
        <?php if ($this->showMap() && $mapPosition === 'bottom'): ?>
            <div class="row">
                <div class="col-12">
                    <div class="moloch-map-container mt-4">
                        <?php echo $this->loadTemplate('map'); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Loading overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin fa-3x"></i>
        <p><?php echo Text::_('COM_MOLOCH_LOADING'); ?></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Moloch Issues functionality
    if (typeof MolochIssues !== 'undefined') {
        MolochIssues.init();
    }
    
    // Handle filter form changes
    const filterForm = document.getElementById('adminForm');
    if (filterForm) {
        const filterInputs = filterForm.querySelectorAll('select, input');
        filterInputs.forEach(function(input) {
            if (input.type !== 'submit' && input.type !== 'hidden') {
                input.addEventListener('change', function() {
                    if (input.name === 'filter[search]') {
                        // Don't auto-submit on search input
                        return;
                    }
                    filterForm.submit();
                });
            }
        });
    }
    
    // Handle view toggle
    const viewToggleButtons = document.querySelectorAll('[data-layout]');
    viewToggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const layout = button.getAttribute('data-layout');
            const container = document.getElementById('issues-container');
            
            // Update active button
            viewToggleButtons.forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            
            // Update container class
            container.className = container.className.replace(/layout-\w+/, 'layout-' + layout);
        });
    });
});
</script>