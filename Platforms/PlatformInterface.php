<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Plugins\AOM\Settings;
use Psr\Log\LoggerInterface;

interface PlatformInterface
{
    /**
     * Returns the platform's data table name.
     */
    public static function getDataTableNameStatic();

    /**
     * Returns the platform's data table name.
     */
    public function getDataTableName();

    /**
     * @return LoggerInterface
     */
    public function getLogger();

    /**
     * Returns this plugin's settings.
     *
     * @return Settings
     */
    public function getSettings();

    /**
     * Setup a platform (e.g. add tables and indices).
     *
     * @return mixed
     */
    public function installPlugin();

    /**
     * Cleans up platform specific stuff such as tables and indices when the plugin is being uninstalled.
     *
     * @return mixed
     */
    public function uninstallPlugin();

    /**
     * Whether or not this platform is active.
     *
     * @return bool
     */
    public function isActive();

    /**
     * Imports platform data for the specified period.
     * If no period has been specified, the platform detects the period to import on its own (usually "yesterday").
     * When triggered via scheduled tasks, imported platform data is being merged automatically afterwards.
     *
     * @param bool $mergeAfterwards
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return mixed
     */
    public function import($mergeAfterwards = false, $startDate = null, $endDate = null);

    /**
     * Merges platform data for the specified period.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return mixed
     */
    public function merge($startDate, $endDate);

    /**
     * Extracts advertisement platform specific data from the query params and stores it in piwik_log_visit.aom_ad_params.
     * The implementation of this method must ensure a consistently ordered JSON.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdParamsFromQueryParams($paramPrefix, array $queryParams);

    /**
     * Extracts advertisement data from the ad params and stores it in piwik_log_visit.aom_ad_data.
     * At this point it is likely that there is no actual ad data available. In this case historical data is used to
     * add some basic information.
     * The implementation of this method must ensure a consistently ordered JSON.
     *
     * @param string $idSite
     * @param array $adParams
     * @return mixed
     */
    public function getAdDataFromAdParams($idSite, array $adParams);
}
