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
    public static function getDataTableName();

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
     * If no period has been specified, the platform detects the period to import on its own.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate   YYYY-MM-DD
     * @return mixed
     */
    public function import($startDate = null, $endDate = null);

    /**
     * Merges platform data for the specified period.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate   YYYY-MM-DD
     * @return mixed
     */
    public function merge($startDate = null, $endDate = null);

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

    /**
     * Enriches a visit with platform specific information (e.g. campaign name, creative, cpc).
     *
     * @param array &$visit The visit to enrich with platform specific information.
     * @param array $adParams Details about the ad the visitor came from.
     * @return mixed
     */
    public function enrichVisit(array &$visit, array $adParams);
}
