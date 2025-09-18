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

$app = Factory::getApplication();
$user = Factory::getUser();
$isNew = $this->isNew();
$canEdit = $this->canEdit();
$canEditState = $this->canEditState();

if (!$canEdit) {
    throw new Exception(Text::_('COM_MOLOCH_ERROR_EDIT_NOT_PERMITTED'), 403);
}

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

?>

<div class="moloch-form">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <?php if ($isNew): ?>
                            <i class="fas fa-plus-circle"></i>
                            <?php echo Text::_('COM_MOLOCH_REPORT_ISSUE'); ?>
                        <?php else: ?>
                            <i class="fas fa-edit"></i>
                            <?php echo Text::_('COM_MOLOCH_EDIT_ISSUE'); ?>
                        <?php endif; ?>
                    </h1>
                    <p class="page-description">
                        <?php echo $isNew ? 
                            Text::_('COM_MOLOCH_FORM_NEW_DESCRIPTION') : 
                            Text::_('COM_MOLOCH_FORM_EDIT_DESCRIPTION'); ?>
                    </p>
                </div>
                
                <div class="col-md-4 text-right">
                    <a href="<?php echo $this->getReturnUrl(); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <?php echo Text::_('COM_MOLOCH_BACK'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            
            <!-- Main Form -->
            <div class="col-lg-8">
                
                <form action="<?php echo $this->getFormAction(); ?>" 
                      method="post" 
                      name="adminForm" 
                      id="adminForm" 
                      class="form-validate"
                      enctype="multipart/form-data"
                      novalidate>
                    
                    <!-- Basic Information Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i>
                                <?php echo Text::_('COM_MOLOCH_BASIC_INFORMATION'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Title -->
                            <div class="form-group">
                                <?php echo $this->form->getLabel('title'); ?>
                                <?php echo $this->form->getInput('title'); ?>
                                <div class="invalid-feedback">
                                    <?php echo Text::_('COM_MOLOCH_ISSUE_TITLE_REQUIRED'); ?>
                                </div>
                                <small class="form-text text-muted">
                                    <?php echo Text::_('COM_MOLOCH_ISSUE_TITLE_HELP'); ?>
                                </small>
                            </div>
                            
                            <!-- Category -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <?php echo $this->form->getLabel('catid'); ?>
                                        <?php echo $this->form->getInput('catid'); ?>
                                        <div class="invalid-feedback">
                                            <?php echo Text::_('COM_MOLOCH_CATEGORY_REQUIRED'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Status (if user can edit state) -->
                                <?php if ($canEditState && !$isNew): ?>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <?php echo $this->form->getLabel('stepid'); ?>
                                            <?php echo $this->form->getInput('stepid'); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Description -->
                            <div class="form-group">
                                <?php echo $this->form->getLabel('description'); ?>
                                <?php echo $this->form->getInput('description'); ?>
                                <div class="invalid-feedback">
                                    <?php echo Text::_('COM_MOLOCH_ISSUE_DESCRIPTION_REQUIRED'); ?>
                                </div>
                                <small class="form-text text-muted">
                                    <?php echo Text::_('COM_MOLOCH_ISSUE_DESCRIPTION_HELP'); ?>
                                </small>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Location Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo Text::_('COM_MOLOCH_LOCATION_INFORMATION'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Address -->
                            <div class="form-group">
                                <?php echo $this->form->getLabel('address'); ?>
                                <div class="input-group">
                                    <?php echo $this->form->getInput('address'); ?>
                                    <div class="input-group-append">
                                        <button type="button" 
                                                class="btn btn-outline-primary" 
                                                id="search-address-btn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-secondary" 
                                                id="detect-location-btn"
                                                title="<?php echo Text::_('COM_MOLOCH_DETECT_MY_LOCATION'); ?>">
                                            <i class="fas fa-crosshairs"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    <?php echo Text::_('COM_MOLOCH_ISSUE_ADDRESS_HELP'); ?>
                                </small>
                            </div>
                            
                            <!-- Map Container -->
                            <div class="form-group">
                                <label for="location-map">
                                    <?php echo Text::_('COM_MOLOCH_ISSUE_LOCATION'); ?>
                                </label>
                                <div id="location-map" class="location-map" style="height: 400px; border-radius: 8px;">
                                    <!-- Loading indicator -->
                                    <div class="map-loading d-flex align-items-center justify-content-center h-100">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary mb-3" role="status">
                                                <span class="sr-only"><?php echo Text::_('COM_MOLOCH_LOADING'); ?></span>
                                            </div>
                                            <p class="mb-0"><?php echo Text::_('COM_MOLOCH_MAP_LOADING'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    <?php echo Text::_('COM_MOLOCH_ISSUE_LOCATION_HELP'); ?>
                                </small>
                            </div>
                            
                            <!-- Coordinates (hidden but accessible for manual input) -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <?php echo $this->form->getLabel('latitude'); ?>
                                        <?php echo $this->form->getInput('latitude'); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <?php echo $this->form->getLabel('longitude'); ?>
                                        <?php echo $this->form->getInput('longitude'); ?>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Attachments Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-paperclip"></i>
                                <?php echo Text::_('COM_MOLOCH_ATTACHMENTS'); ?>
                                <small class="text-muted ml-2">
                                    (<?php echo Text::_('COM_MOLOCH_OPTIONAL'); ?>)
                                </small>
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- File Upload Zone -->
                            <div class="file-upload-zone">
                                <div class="file-drop-zone" id="file-drop-zone">
                                    <div class="drop-zone-content text-center p-4">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <h6><?php echo Text::_('COM_MOLOCH_DRAG_DROP_FILES'); ?></h6>
                                        <p class="text-muted mb-3">
                                            <?php echo Text::_('COM_MOLOCH_SUPPORTED_FILES'); ?>
                                        </p>
                                        <button type="button" class="btn btn-primary" id="select-files-btn">
                                            <i class="fas fa-plus"></i>
                                            <?php echo Text::_('COM_MOLOCH_SELECT_FILES'); ?>
                                        </button>
                                        <input type="file" 
                                               id="file-input" 
                                               multiple 
                                               accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.odt,.ods,.odp"
                                               style="display: none;">
                                    </div>
                                </div>
                                
                                <!-- File Info -->
                                <div class="file-info mt-3">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                <?php echo Text::_('COM_MOLOCH_MAX_FILE_SIZE'); ?>: 
                                                <?php echo $this->getMaxFileSizeFormatted(); ?>
                                            </small>
                                        </div>
                                        <div class="col-sm-6">
                                            <small class="text-muted">
                                                <i class="fas fa-file-alt"></i>
                                                <?php echo Text::_('COM_MOLOCH_ALLOWED_TYPES'); ?>: 
                                                <?php echo $this->getAllowedFileTypesString(); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Existing Attachments -->
                            <?php if (!$isNew && !empty($this->getAttachments())): ?>
                                <div class="existing-attachments mt-4">
                                    <h6><?php echo Text::_('COM_MOLOCH_EXISTING_ATTACHMENTS'); ?></h6>
                                    <div class="row">
                                        <?php foreach ($this->getAttachments() as $attachment): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="attachment-item card">
                                                    <?php if ($attachment->file_type === 'image'): ?>
                                                        <img src="<?php echo Uri::root() . ($attachment->thumbnail ?: $attachment->filepath); ?>" 
                                                             class="card-img-top" 
                                                             alt="<?php echo $this->escape($attachment->original_filename); ?>"
                                                             style="height: 150px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                                             style="height: 150px;">
                                                            <i class="fas fa-file fa-3x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="card-body p-2">
                                                        <h6 class="card-title small mb-1">
                                                            <?php echo $this->escape($attachment->original_filename); ?>
                                                        </h6>
                                                        <p class="card-text">
                                                            <small class="text-muted">
                                                                <?php echo HTMLHelper::_('number.bytes', $attachment->filesize); ?>
                                                            </small>
                                                        </p>
                                                        <div class="btn-group btn-group-sm w-100">
                                                            <a href="<?php echo Uri::root() . $attachment->filepath; ?>" 
                                                               target="_blank" 
                                                               class="btn btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <button type="button" 
                                                                    class="btn btn-outline-danger delete-attachment-btn"
                                                                    data-attachment-id="<?php echo $attachment->id; ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Upload Progress Container -->
                            <div id="upload-progress-container" class="mt-3" style="display: none;">
                                <h6><?php echo Text::_('COM_MOLOCH_UPLOADING_FILES'); ?></h6>
                                <div id="upload-progress-list"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hidden fields -->
                    <?php echo $this->form->getInput('id'); ?>
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="task" value="save" />
                    <input type="hidden" name="return" value="<?php echo base64_encode($this->getReturnUrl()); ?>" />
                    
                </form>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                
                <!-- Save Actions Card -->
                <div class="card mb-4 sticky-top">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-save"></i>
                            <?php echo Text::_('COM_MOLOCH_ACTIONS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        
                        <!-- Save Button -->
                        <button type="submit" 
                                class="btn btn-primary btn-lg btn-block mb-3" 
                                form="adminForm"
                                onclick="Joomla.submitbutton('save')">
                            <i class="fas fa-save"></i>
                            <?php echo Text::_('COM_MOLOCH_SAVE'); ?>
                        </button>
                        
                        <!-- Additional Actions -->
                        <?php if ($canEditState): ?>
                            <div class="btn-group w-100 mb-3">
                                <button type="button" 
                                        class="btn btn-outline-success"
                                        onclick="Joomla.submitbutton('save2new')">
                                    <i class="fas fa-plus"></i>
                                    <?php echo Text::_('COM_MOLOCH_SAVE_NEW'); ?>
                                </button>
                                <button type="button" 
                                        class="btn btn-outline-secondary"
                                        onclick="Joomla.submitbutton('save2close')">
                                    <i class="fas fa-check"></i>
                                    <?php echo Text::_('COM_MOLOCH_SAVE_CLOSE'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Cancel Button -->
                        <a href="<?php echo $this->getReturnUrl(); ?>" 
                           class="btn btn-outline-secondary btn-block">
                            <i class="fas fa-times"></i>
                            <?php echo Text::_('COM_MOLOCH_CANCEL'); ?>
                        </a>
                        
                    </div>
                </div>
                
                <!-- Tips Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-lightbulb"></i>
                            <?php echo Text::_('COM_MOLOCH_TIPS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                <small><?php echo Text::_('COM_MOLOCH_TIP_DESCRIPTIVE_TITLE'); ?></small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                <small><?php echo Text::_('COM_MOLOCH_TIP_DETAILED_DESCRIPTION'); ?></small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                <small><?php echo Text::_('COM_MOLOCH_TIP_ACCURATE_LOCATION'); ?></small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                <small><?php echo Text::_('COM_MOLOCH_TIP_ADD_PHOTOS'); ?></small>
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                <small><?php echo Text::_('COM_MOLOCH_TIP_CORRECT_CATEGORY'); ?></small>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Status Info (for existing issues) -->
                <?php if (!$isNew): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info"></i>
                                <?php echo Text::_('COM_MOLOCH_ISSUE_STATUS'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="issue-meta">
                                <div class="row mb-2">
                                    <div class="col-sm-5">
                                        <strong><?php echo Text::_('COM_MOLOCH_STATUS'); ?>:</strong>
                                    </div>
                                    <div class="col-sm-7">
                                        <span class="badge" style="background-color: <?php echo $this->item->step_color ?? '#6c757d'; ?>">
                                            <?php echo $this->escape($this->item->step_title ?? Text::_('COM_MOLOCH_STEP_SUBMITTED')); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-5">
                                        <strong><?php echo Text::_('COM_MOLOCH_CREATED'); ?>:</strong>
                                    </div>
                                    <div class="col-sm-7">
                                        <small><?php echo HTMLHelper::_('date', $this->item->created, Text::_('DATE_FORMAT_LC2')); ?></small>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-5">
                                        <strong><?php echo Text::_('COM_MOLOCH_VOTES'); ?>:</strong>
                                    </div>
                                    <div class="col-sm-7">
                                        <small>
                                            <span class="text-success">
                                                <i class="fas fa-thumbs-up"></i> <?php echo $this->item->votes_up ?? 0; ?>
                                            </span>
                                            <span class="text-danger ml-2">
                                                <i class="fas fa-thumbs-down"></i> <?php echo $this->item->votes_down ?? 0; ?>
                                            </span>
                                        </small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-5">
                                        <strong><?php echo Text::_('COM_MOLOCH_HITS'); ?>:</strong>
                                    </div>
                                    <div class="col-sm-7">
                                        <small><?php echo $this->item->hits ?? 0; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
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
// Initialize form functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof MolochForm !== 'undefined') {
        MolochForm.init();
    }
});

// Joomla form submission
Joomla.submitbutton = function(task) {
    var form = document.getElementById('adminForm');
    
    if (task === 'cancel') {
        window.location.href = '<?php echo $this->getReturnUrl(); ?>';
        return;
    }
    
    if (task === 'save' || task === 'save2new' || task === 'save2close') {
        if (form.checkValidity && !form.checkValidity()) {
            // Show validation errors
            form.classList.add('was-validated');
            
            // Scroll to first error
            var firstError = form.querySelector(':invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            
            // Show notification
            if (window.Moloch && window.Moloch.showNotification) {
                window.Moloch.showNotification('<?php echo Text::_('COM_MOLOCH_FORM_VALIDATION_ERROR'); ?>', 'error');
            }
            
            return false;
        }
        
        // Set the task
        form.task.value = task;
        
        // Show loading
        document.getElementById('loading-overlay').style.display = 'flex';
        
        // Submit form
        form.submit();
    }
};
</script>