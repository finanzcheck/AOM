<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface PlatformInterface
{
    /**
     * Returns the platform's internal unqualified class name.
     *
     * @return string
     */
    public function getName();

    /**
     * Returns the platform's translated localized name.
     *
     * @return string
     */
    public function getLocalizedPlatformName();

    /**
     * Returns the platform's data table name.
     *
     * @return string
     */
    public function getDataTableName();

    /**
     * Returns whether or not the platform is active.
     *
     * @return bool
     */
    public function isActive();

    /**
     * Platforms can add additional items to the admin menu.
     *
     * @return array
     */
    public function getMenuAdminItems();

    /**
     * Platforms can load additional JS files in the admin view.
     *
     * @return array
     */
    public function getJsFiles();

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
     * If no period has been specified, we'll try to merge yesterdays data only.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return void
     */
    public function merge($startDate, $endDate);

    /**
     * Returns an instance of MarketingPerformanceSubTables when drill down through Piwik UI is supported.
     * Returns false, if not.
     *
     * @return MarketingPerformanceSubTablesInterface|false
     */
    public function getMarketingPerformanceSubTables();

    /**
     * Returns a platform-specific description of a specific visit optimized for being read by humans or false when no
     * platform-specific description is available.
     *
     * @param int $idVisit
     * @return string|false
     */
    public static function getHumanReadableDescriptionForVisit($idVisit);
}
