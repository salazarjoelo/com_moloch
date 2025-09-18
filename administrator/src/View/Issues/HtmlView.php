<?php
/**
 * @package     Moloch
 * @subpackage  Administrator.View
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Moloch\Component\Moloch\Administrator\View\Issues;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Button\DropdownButton;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * View class for a list of issues.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The search tools form
     *
     * @var    Form
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var    array
     */
    public $activeFilters = [];

    /**
     * An array of items
     *
     * @var    array
     */
    protected $items = [];

    /**
     * The pagination object
     *
     * @var    Pagination
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var    Registry
     */
    protected $state;

    /**
     * Is this view an Empty State
     *
     * @var   boolean
     */
    private $isEmptyState = false;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        /** @var IssuesModel $model */
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        if (!\count($this->items) && $this->isEmptyState = $this->get('IsEmptyState')) {
            $this->setLayout('emptystate');
        }

        // We don't need toolbar in the modal window.
        if ($this->getLayout() !== 'modal') {
            $this->addToolbar();

            if ($this->state->get('filter.published') == -2) {
                ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'issues.delete', 'JTOOLBAR_EMPTY_TRASH');
            }
        }

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     */
    protected function addToolbar(): void
    {
        $canDo = ContentHelper::getActions('com_moloch', 'category', $this->state->get('filter.category_id'));
        $user  = Factory::getUser();

        // Get the toolbar object instance
        $toolbar = Toolbar::getInstance();

        ToolbarHelper::title(Text::_('COM_MOLOCH_MANAGER_ISSUES'), 'stack issue');

        if ($canDo->get('core.create') || \count($user->getAuthorisedCategories('com_moloch', 'core.create')) > 0) {
            ToolbarHelper::addNew('issue.add');
        }

        if (!$this->isEmptyState && $canDo->get('core.edit.state')) {
            /** @var DropdownButton $dropdown */
            $dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();
            $childBar->publish('issues.publish')->listCheck(true);
            $childBar->unpublish('issues.unpublish')->listCheck(true);
            $childBar->archive('issues.archive')->listCheck(true);
            $childBar->checkin('issues.checkin')->listCheck(true);

            if ($this->state->get('filter.published') != -2) {
                $childBar->trash('issues.trash')->listCheck(true);
            }
        }

        if (!$this->isEmptyState && $canDo->get('core.edit')) {
            /** @var DropdownButton $dropdown */
            $dropdown = $toolbar->dropdownButton('featured-group', 'COM_MOLOCH_FEATURED')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();
            $childBar->standardButton('featured', 'COM_MOLOCH_FEATURED', 'issues.featured')
                ->icon('icon-color-featured')
                ->listCheck(true);
            $childBar->standardButton('unfeatured', 'COM_MOLOCH_UNFEATURED', 'issues.unfeatured')
                ->icon('icon-color-unfeatured')
                ->listCheck(true);
        }

        if (!$this->isEmptyState && $this->state->get('filter.published') == -2 && $canDo->get('core.delete')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'issues.delete', 'JTOOLBAR_EMPTY_TRASH');
        } elseif (!$this->isEmptyState && $canDo->get('core.edit.state')) {
            ToolbarHelper::trash('issues.trash');
        }

        // Add batch processing
        if (!$this->isEmptyState && $user->authorise('core.edit', 'com_moloch') && $user->authorise('core.edit.state', 'com_moloch')) {
            ToolbarHelper::custom('issues.batch', 'checkbox-partial', '', 'JTOOLBAR_BATCH', true);
        }

        // Add export functionality
        if (!$this->isEmptyState) {
            /** @var DropdownButton $dropdown */
            $dropdown = $toolbar->dropdownButton('export-group', 'COM_MOLOCH_EXPORT')
                ->toggleSplit(false)
                ->icon('icon-download')
                ->buttonClass('btn btn-info');

            $childBar = $dropdown->getChildToolbar();
            $childBar->standardButton('csv', 'CSV', 'issues.exportCsv')
                ->icon('icon-file-2')
                ->listCheck(true);
            $childBar->standardButton('json', 'JSON', 'issues.exportJson')
                ->icon('icon-file-3')
                ->listCheck(true);
        }

        if ($user->authorise('core.admin', 'com_moloch') || $user->authorise('core.options', 'com_moloch')) {
            ToolbarHelper::preferences('com_moloch');
        }

        ToolbarHelper::help('', false, 'https://docs.edugame.digital/moloch/');
    }

    /**
     * Returns an array of fields the table can be sorted by
     *
     * @return  array  Array containing the field name to sort by as the key and display text as value
     */
    protected function getSortFields(): array
    {
        return [
            'a.ordering'     => Text::_('JGRID_HEADING_ORDERING'),
            'a.published'    => Text::_('JSTATUS'),
            'a.title'        => Text::_('JGLOBAL_TITLE'),
            'category_title' => Text::_('JCATEGORY'),
            'step_title'     => Text::_('COM_MOLOCH_HEADING_STEP'),
            'a.featured'     => Text::_('JFEATURED'),
            'a.access'       => Text::_('JGRID_HEADING_ACCESS'),
            'author_name'    => Text::_('JAUTHOR'),
            'a.created'      => Text::_('JDATE'),
            'a.hits'         => Text::_('JGLOBAL_HITS'),
            'a.votes_up'     => Text::_('COM_MOLOCH_HEADING_VOTES'),
            'a.language'     => Text::_('JGRID_HEADING_LANGUAGE'),
            'a.id'           => Text::_('JGRID_HEADING_ID'),
        ];
    }

    /**
     * Check if state is empty
     *
     * @return bool
     */
    private function getIsEmptyState(): bool
    {
        $model = $this->getModel();

        return $model->getTotal() === 0 && $this->isEmptySearch();
    }

    /**
     * Check for empty search
     *
     * @return bool
     */
    private function isEmptySearch(): bool
    {
        $filters = (array) $this->activeFilters;

        unset($filters['filter_search'], $filters['filter_published'], $filters['filter_category_id']);

        return \count($filters) === 0;
    }

    /**
     * Get categories for batch processing
     *
     * @return  array
     */
    public function getCategories(): array
    {
        $model = $this->getModel();
        return $model->getCategories();
    }

    /**
     * Get workflow steps for batch processing
     *
     * @return  array
     */
    public function getSteps(): array
    {
        $model = $this->getModel();
        return $model->getSteps();
    }

    /**
     * Get authors for filters
     *
     * @return  array
     */
    public function getAuthors(): array
    {
        $model = $this->getModel();
        return $model->getAuthors();
    }

    /**
     * Get component parameters
     *
     * @return  Registry
     */
    public function getParams(): Registry
    {
        return \JComponentHelper::getParams('com_moloch');
    }

    /**
     * Check if associations are supported
     *
     * @return  bool
     */
    public function isAssociationsEnabled(): bool
    {
        return Associations::isEnabled();
    }

    /**
     * Check if multilanguage is enabled
     *
     * @return  bool
     */
    public function isMultilanguageEnabled(): bool
    {
        return Multilanguage::isEnabled();
    }

    /**
     * Get status options
     *
     * @return  array
     */
    public function getStatusOptions(): array
    {
        return [
            ''  => Text::_('JOPTION_SELECT_PUBLISHED'),
            '1' => Text::_('JPUBLISHED'),
            '0' => Text::_('JUNPUBLISHED'),
            '2' => Text::_('JARCHIVED'),
            '-2' => Text::_('JTRASHED'),
        ];
    }

    /**
     * Get featured options
     *
     * @return  array
     */
    public function getFeaturedOptions(): array
    {
        return [
            ''          => Text::_('JOPTION_SELECT_FEATURED'),
            'featured'   => Text::_('JFEATURED'),
            'unfeatured' => Text::_('JUNFEATURED'),
        ];
    }

    /**
     * Get access levels
     *
     * @return  array
     */
    public function getAccessLevels(): array
    {
        return \JHtml::_('access.assetgroups');
    }

    /**
     * Get batch form
     *
     * @return  Form|null
     */
    public function getBatchForm(): ?Form
    {
        $form = null;

        try {
            $form = $this->loadForm('com_moloch.batch', 'batch', ['control' => 'batch']);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }

        return $form;
    }
}