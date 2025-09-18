<?php
/**
 * @package     Moloch
 * @subpackage  Site.Template
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$mapProvider = $this->params->get('map_provider', 'googlemaps');
$mapHeight = $this->params->get('map_height', 450);

?>

<div class="moloch-map-wrapper">
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-map-marked-alt"></i>
                        <?php echo Text::_('COM_MOLOCH_ISSUES_MAP'); ?>
                    </h5>
                </div>
                <div class="col-auto">
                    <div class="map-controls">
                        <!-- Full Screen Toggle -->
                        <button type="button" 
                                class="btn btn-sm btn-outline-secondary" 
                                id="map-fullscreen-toggle"
                                title="<?php echo Text::_('COM_MOLOCH_MAP_FULLSCREEN'); ?>">
                            <i class="fas fa-expand"></i>
                        </button>
                        
                        <!-- Map Type Toggle (for Google Maps) -->
                        <?php if ($mapProvider === 'googlemaps'): ?>
                            <div class="btn-group ml-2" role="group">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary active" 
                                        data-map-type="roadmap"
                                        title="<?php echo Text::_('COM_MOLOCH_MAP_TYPE_ROADMAP'); ?>">
                                    <i class="fas fa-road"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary" 
                                        data-map-type="satellite"
                                        title="<?php echo Text::_('COM_MOLOCH_MAP_TYPE_SATELLITE'); ?>">
                                    <i class="fas fa-satellite"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary" 
                                        data-map-type="hybrid"
                                        title="<?php echo Text::_('COM_MOLOCH_MAP_TYPE_HYBRID'); ?>">
                                    <i class="fas fa-layer-group"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- My Location Button -->
                        <button type="button" 
                                class="btn btn-sm btn-outline-primary ml-2" 
                                id="map-locate-me"
                                title="<?php echo Text::_('COM_MOLOCH_MAP_LOCATE_ME'); ?>">
                            <i class="fas fa-crosshairs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            
            <!-- Map Container -->
            <div id="moloch-map" 
                 class="moloch-map" 
                 style="height: <?php echo $mapHeight; ?>px; width: 100%;">
                
                <!-- Loading State -->
                <div class="map-loading d-flex align-items-center justify-content-center h-100">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="sr-only"><?php echo Text::_('COM_MOLOCH_LOADING'); ?></span>
                        </div>
                        <p class="mb-0"><?php echo Text::_('COM_MOLOCH_MAP_LOADING'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Map Legend -->
            <div class="map-legend bg-light p-3 border-top">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="font-weight-bold mb-2">
                            <i class="fas fa-tags"></i>
                            <?php echo Text::_('COM_MOLOCH_CATEGORIES'); ?>
                        </h6>
                        <div class="legend-categories">
                            <?php foreach ($this->categories as $category): ?>
                                <label class="legend-item mr-3 mb-1">
                                    <input type="checkbox" 
                                           class="category-filter" 
                                           data-category-id="<?php echo $category->id; ?>" 
                                           checked>
                                    <span class="legend-color" 
                                          style="background-color: <?php echo $category->color; ?>"></span>
                                    <span class="legend-label"><?php echo $this->escape($category->title); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="font-weight-bold mb-2">
                            <i class="fas fa-tasks"></i>
                            <?php echo Text::_('COM_MOLOCH_STATUS'); ?>
                        </h6>
                        <div class="legend-steps">
                            <?php foreach ($this->steps as $step): ?>
                                <label class="legend-item mr-3 mb-1">
                                    <input type="checkbox" 
                                           class="step-filter" 
                                           data-step-id="<?php echo $step->id; ?>" 
                                           checked>
                                    <span class="legend-color" 
                                          style="background-color: <?php echo $step->color; ?>"></span>
                                    <span class="legend-label"><?php echo $this->escape($step->title); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Map Statistics -->
                <div class="row mt-3 pt-3 border-top">
                    <div class="col-sm-6">
                        <div class="map-stats">
                            <span id="visible-markers-count"><?php echo count($this->items); ?></span>
                            <small class="text-muted">
                                <?php echo Text::_('COM_MOLOCH_ISSUES_VISIBLE'); ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-sm-6 text-right">
                        <button type="button" 
                                class="btn btn-sm btn-outline-secondary" 
                                id="map-reset-view">
                            <i class="fas fa-home"></i>
                            <?php echo Text::_('COM_MOLOCH_MAP_RESET_VIEW'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Issue Info Popup Template (Hidden) -->
    <div id="issue-info-template" style="display: none;">
        <div class="issue-popup">
            <div class="issue-popup-header">
                <h6 class="issue-popup-title"></h6>
                <div class="issue-popup-badges"></div>
            </div>
            <div class="issue-popup-body">
                <p class="issue-popup-description"></p>
                <div class="issue-popup-meta">
                    <small class="issue-popup-address text-muted"></small>
                    <small class="issue-popup-date text-muted"></small>
                </div>
                <div class="issue-popup-votes">
                    <span class="badge badge-success">
                        <i class="fas fa-thumbs-up"></i>
                        <span class="votes-up">0</span>
                    </span>
                    <span class="badge badge-danger ml-1">
                        <i class="fas fa-thumbs-down"></i>
                        <span class="votes-down">0</span>
                    </span>
                </div>
            </div>
            <div class="issue-popup-footer">
                <a href="#" class="btn btn-sm btn-primary issue-popup-link">
                    <?php echo Text::_('COM_MOLOCH_VIEW_DETAILS'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles for Map -->
<style>
.legend-item {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.875rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
    margin-left: 5px;
    margin-right: 5px;
}

.map-stats {
    font-size: 1.1rem;
    font-weight: 600;
}

.issue-popup {
    max-width: 300px;
    font-size: 0.875rem;
}

.issue-popup-header {
    margin-bottom: 10px;
}

.issue-popup-title {
    margin: 0 0 5px 0;
    font-size: 1rem;
    font-weight: 600;
    color: #333;
}

.issue-popup-badges {
    margin-bottom: 5px;
}

.issue-popup-description {
    margin-bottom: 10px;
    line-height: 1.4;
}

.issue-popup-meta small {
    display: block;
    margin-bottom: 2px;
}

.issue-popup-votes {
    margin: 10px 0;
}

.issue-popup-footer {
    margin-top: 10px;
    text-align: center;
}

.moloch-map {
    position: relative;
    background-color: #f8f9fa;
}

.map-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
    background-color: rgba(248, 249, 250, 0.9);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .map-controls .btn-group {
        display: none;
    }
    
    .legend-categories,
    .legend-steps {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .legend-item {
        margin-right: 10px !important;
        margin-bottom: 5px !important;
    }
}

/* Fullscreen map styles */
.map-fullscreen {
    position: fixed !important;
    top: 0;
    left: 0;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 9999;
    background: white;
}

.map-fullscreen .moloch-map {
    height: calc(100vh - 120px) !important;
}

.map-fullscreen .card {
    border: none;
    border-radius: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
}

.map-fullscreen .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}
</style>