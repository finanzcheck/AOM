<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Settings;
use Psr\Log\LoggerInterface;

abstract class Platform
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->settings = new Settings();
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
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
     * If no period has been specified, the platform detects the period to import on its own (usually "yesterday").
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

        /** @var ImporterInterface $importer */
        $importer = AOM::getPlatformInstance($this->getUnqualifiedClassName(), 'Importer', $this->getLogger());
        $importer->setPeriod($startDate, $endDate);
        $importer->import();
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
        // TODO: Consider site timezone here?!
        if (null === $startDate || null === $endDate) {
            $startDate = date('Y-m-d', strtotime('-1 day', time()));
            $endDate = date('Y-m-d', strtotime('-1 day', time()));
        }

        /** @var MergerInterface $merger */
        $merger = AOM::getPlatformInstance($this->getUnqualifiedClassName(), 'Merger', $this->getLogger());
        $merger->setPeriod($startDate, $endDate);
        $merger->setPlatform($this);
        $merger->merge();
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

    /**
     * Returns the Name of this Platform
     *
     * @return string
     */
    public function getName()
    {
        return $this->getUnqualifiedClassName();
    }

    /**
     * Returns the platform's data table name.
     *
     * @return string
     */
    public function getDataTableName()
    {
        return Common::prefixTable('aom_' . strtolower($this->getName()));
    }

}
