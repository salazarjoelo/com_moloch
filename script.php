<?php
/**
 * @package     Moloch
 * @subpackage  Installation Script
 * @author      Lic. Joel Salazar Ramírez <joel@edugame.digital>
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;

/**
 * Installation class to perform additional changes during install/uninstall/update
 */
class Com_MolochInstallerScript extends InstallerScript
{
    /**
     * @var string
     */
    protected $minimumJoomla = '3.9.0';
    
    /**
     * @var string
     */
    protected $minimumPhp = '8.0.0';
    
    /**
     * @var array
     */
    protected $deleteFiles = [];
    
    /**
     * @var array
     */
    protected $deleteFolders = [];

    /**
     * Function called before extension installation/update/removal procedure commences
     *
     * @param   string     $type     The type of change (install, update or discover_install, not uninstall)
     * @param   Installer  $parent   The class calling this method
     *
     * @return  boolean  True on success
     */
    public function preflight($type, $parent)
    {
        // Check for the minimum Joomla version before continuing
        if (!parent::preflight($type, $parent)) {
            return false;
        }

        // Check if PHP version is supported
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
                    'COM_MOLOCH_INSTALL_PHP_VERSION_ERROR',
                    PHP_VERSION,
                    $this->minimumPhp
                ),
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Function called after extension installation/update/removal procedure commences
     *
     * @param   string     $type     The type of change (install, update or discover_install, not uninstall)
     * @param   Installer  $parent   The class calling this method
     *
     * @return  boolean  True on success
     */
    public function postflight($type, $parent)
    {
        if ($type === 'install') {
            $this->installActions();
        }
        
        if ($type === 'update') {
            $this->updateActions();
        }

        return true;
    }

    /**
     * Actions to perform during installation
     */
    private function installActions()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        
        // Create uploads directory
        $uploadsPath = JPATH_ROOT . '/media/com_moloch/uploads';
        
        if (!Folder::exists($uploadsPath)) {
            if (!Folder::create($uploadsPath)) {
                $app->enqueueMessage(
                    Text::_('COM_MOLOCH_INSTALL_UPLOADS_DIR_ERROR'),
                    'warning'
                );
            } else {
                // Create .htaccess file for security
                $htaccessContent = "# Security rules for uploads\n" .
                    "<Files ~ \"\\.(php|phtml|php3|php4|php5|phps|cgi|pl)$\">\n" .
                    "Order allow,deny\n" .
                    "Deny from all\n" .
                    "</Files>\n";
                
                File::write($uploadsPath . '/.htaccess', $htaccessContent);
                
                // Create index.html for directory protection
                File::write($uploadsPath . '/index.html', '<!DOCTYPE html><title></title>');
            }
        }

        // Set default component parameters
        $this->setDefaultParameters();

        // Install default categories and steps
        $this->installDefaultData();

        $app->enqueueMessage(
            Text::_('COM_MOLOCH_INSTALL_SUCCESS'),
            'message'
        );
    }

    /**
     * Actions to perform during update
     */
    private function updateActions()
    {
        $app = Factory::getApplication();
        
        // Perform any update-specific actions here
        
        $app->enqueueMessage(
            Text::_('COM_MOLOCH_UPDATE_SUCCESS'),
            'message'
        );
    }

    /**
     * Set default component parameters
     */
    private function setDefaultParameters()
    {
        $db = Factory::getDbo();
        
        // Generate random API key
        $apiKey = ApplicationHelper::getHash(microtime() . mt_rand());
        $apiKey = substr($apiKey, 0, 16);
        
        $defaultParams = [
            'map_provider' => 'googlemaps',
            'google_maps_api_key' => '',
            'default_latitude' => '20.6296',
            'default_longitude' => '-87.0739',
            'default_zoom' => '12',
            'max_file_size' => '1073741824',
            'allowed_file_types' => 'jpg,jpeg,png,gif,bmp,webp,mp4,avi,mov,wmv,flv,mp3,wav,ogg,aac,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,odt,ods,odp',
            'guest_submissions' => '1',
            'auto_approve_issues' => '0',
            'enable_voting' => '1',
            'enable_comments' => '1',
            'notification_email' => '',
            'api_key' => $apiKey
        ];

        $params = json_encode($defaultParams);

        try {
            $query = $db->getQuery(true)
                ->update('#__extensions')
                ->set('params = ' . $db->quote($params))
                ->where('element = ' . $db->quote('com_moloch'))
                ->where('type = ' . $db->quote('component'));

            $db->setQuery($query);
            $db->execute();
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_MOLOCH_INSTALL_PARAMS_ERROR'),
                'warning'
            );
        }
    }

    /**
     * Install default data (categories and steps)
     */
    private function installDefaultData()
    {
        $db = Factory::getDbo();
        
        try {
            // Install default categories
            $categories = [
                ['Infraestructura Vial', 'Problemas con calles, banquetas, baches', '#FF6B6B'],
                ['Servicios Públicos', 'Alumbrado, drenaje, agua potable', '#4ECDC4'],
                ['Limpieza', 'Basura, grafiti, espacios públicos sucios', '#45B7D1'],
                ['Seguridad', 'Señalamientos, semáforos, mobiliario urbano', '#FFA07A'],
                ['Medio Ambiente', 'Áreas verdes, contaminación, ruido', '#98D8C8'],
                ['Otros', 'Problemas que no entran en las categorías anteriores', '#95A5A6']
            ];

            foreach ($categories as $cat) {
                $query = $db->getQuery(true)
                    ->insert('#__moloch_categories')
                    ->columns(['title', 'description', 'color', 'published', 'ordering'])
                    ->values(
                        $db->quote($cat[0]) . ',' .
                        $db->quote($cat[1]) . ',' .
                        $db->quote($cat[2]) . ',' .
                        '1,' .
                        '0'
                    );

                $db->setQuery($query);
                $db->execute();
            }

            // Install default workflow steps
            $steps = [
                ['Enviado', 'Reporte enviado por el ciudadano', '#FFC107', 1],
                ['En Revisión', 'El reporte está siendo revisado por las autoridades', '#17A2B8', 2],
                ['Aceptado', 'El reporte ha sido aceptado y será atendido', '#28A745', 3],
                ['En Proceso', 'Se está trabajando en la solución del problema', '#007BFF', 4],
                ['Resuelto', 'El problema ha sido resuelto', '#28A745', 5],
                ['Cerrado', 'El caso ha sido cerrado', '#6C757D', 6]
            ];

            foreach ($steps as $step) {
                $query = $db->getQuery(true)
                    ->insert('#__moloch_steps')
                    ->columns(['title', 'description', 'color', 'ordering', 'published'])
                    ->values(
                        $db->quote($step[0]) . ',' .
                        $db->quote($step[1]) . ',' .
                        $db->quote($step[2]) . ',' .
                        $step[3] . ',' .
                        '1'
                    );

                $db->setQuery($query);
                $db->execute();
            }

        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error installing default data: ' . $e->getMessage(),
                'warning'
            );
        }
    }

    /**
     * Called on uninstallation
     *
     * @param   Installer  $parent  The class calling this method
     */
    public function uninstall($parent)
    {
        $app = Factory::getApplication();
        
        // Remove uploads directory (ask user first)
        $uploadsPath = JPATH_ROOT . '/media/com_moloch/uploads';
        
        if (Folder::exists($uploadsPath)) {
            $app->enqueueMessage(
                Text::_('COM_MOLOCH_UNINSTALL_UPLOADS_WARNING'),
                'warning'
            );
        }

        $app->enqueueMessage(
            Text::_('COM_MOLOCH_UNINSTALL_SUCCESS'),
            'message'
        );
    }
}