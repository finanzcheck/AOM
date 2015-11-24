<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface PlatformInterface
{
    /**
     * Setup a platform (e.g. add tables and indices).
     *
     * @return mixed
     */
    public function activatePlugin();

    /**
     * Cleans up platform specific stuff such as tables and indices when the plugin is being uninstalled.
     *
     * @return mixed
     */
    public function uninstallPlugin();

    /**
     * Imports platform data for the specified period.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate   YYYY-MM-DD
     * @return mixed
     */
    public function import($startDate, $endDate);

    /**
     * Builds a string key from the ad data that has been passed via URL (as URL-encoded JSON) and is used to reference
     * explicit platform data (this key is being stored in piwik_log_visit.aom_ad_key).
     *
     * @param array $adData
     * @return mixed
     */
    public function getAdKeyFromAdData(array $adData);

    /**
     * Enriches a visit with platform specific information (e.g. campaign name, creative, cpc).
     *
     * @param array &$visit The visit to enrich with platform specific information.
     * @param array $ad     Details about the ad the visitor came from.
     * @return mixed
     */
    public function enrichVisit(array &$visit, array $ad);
}
