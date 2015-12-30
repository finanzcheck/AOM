<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Exception;
use Piwik\Db;
use Piwik\Plugins\AOM\Settings;

abstract class Platform
{
    /**
     * @var Settings
     */
    private $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    /**
     * Returns this plugin's settings.
     *
     * @return Settings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Whether or not this platform has been activated in the plugin's configuration.
     *
     * @return mixed
     */
    public function isActive()
    {
        return $this->settings->{'platform' . $this->getUnqualifiedClassName() . 'IsActive'}->getValue();
    }

    /**
     * Sets up a platform (e.g. adds tables and indices).
     *
     * @throws Exception
     */
    public function installPlugin()
    {
        $this->getInstaller()->installPlugin();
    }

    /**
     * Cleans up platform specific stuff such as tables and indices when the plugin is being uninstalled.
     *
     * @throws Exception
     */
    public function uninstallPlugin()
    {
        $this->getInstaller()->uninstallPlugin();
    }

    /**
     * Instantiates and returns the platform specific installer.
     *
     * @return InstallerInterface
     */
    private function getInstaller()
    {
        $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $this->getUnqualifiedClassName() . '\\Installer';

        /** @var InstallerInterface $installer */
        $installer = new $className($this);

        return $installer;
    }

    /**
     * Imports platform data for the specified period.
     * If no period has been specified, we'll try to important yesterdays data only.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate   YYYY-MM-DD
     * @return mixed
     */
    public function import($startDate = null, $endDate = null)
    {
        if (!$this->isActive()) {
            return;
        }

        // Validate start and end date
        if (null === $startDate || null === $endDate) {

            // TODO: Consider site timezone here?!
            $startDate = date('Y-m-d', strtotime('-1 day', time()));
            $endDate = date('Y-m-d', strtotime('-1 day', time()));
        }

        // Instantiate importer and inject platform
        $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $this->getUnqualifiedClassName() . '\\Importer';

        /** @var ImporterInterface $importer */
        $importer = new $className($this);
        $importer->import($startDate, $endDate);
    }

    /**
     * Merges platform data for the specified period.
     * If no period has been specified, we'll try to merge yesterdays data only.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate   YYYY-MM-DD
     * @return mixed
     */
    public function merge($startDate = null, $endDate = null)
    {
        if (!$this->isActive()) {
            return;
        }

        // Validate start and end date
        if (null === $startDate || null === $endDate) {

            // TODO: Consider site timezone here?!
            $startDate = date('Y-m-d', strtotime('-1 day', time()));
            $endDate = date('Y-m-d', strtotime('-1 day', time()));
        }

        // Instantiate merger and inject platform
        $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $this->getUnqualifiedClassName() . '\\Merger';

        /** @var MergerInterface $merger */
        $merger = new $className($this);
        $merger->merge($startDate, $endDate);
    }

    /**
     * Returns the platform's unqualified class name
     *
     * @return string
     */
    protected function getUnqualifiedClassName()
    {
        return substr(strrchr(get_class($this), '\\'), 1);
    }
}
