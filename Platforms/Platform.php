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
use Piwik\Plugins\AOM\SystemSettings;
use Piwik\Site;
use Psr\Log\LoggerInterface;
use Piwik\Tracker\Request;

abstract class Platform
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SystemSettings
     */
    private $settings;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->settings = new SystemSettings();

        $this->logger = (null === $logger ? AOM::getLogger() : $logger);
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
     * @return SystemSettings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Whether or not this platform has been activated in the plugin's configuration.
     *
     * @return bool
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
     * Returns true if the visit is coming from this platform. False otherwise.
     *
     * TODO: Check if we should use $action->getActionUrl() instead of or in addition to $request->getParams()['url'].
     *
     * @param Request $request
     * @return bool
     */
    public function isVisitComingFromPlatform(Request $request)
    {
        // Check current URL first before referrer URL
        $urlsToCheck = [];
        if (isset($request->getParams()['url'])) {
            $urlsToCheck[] = $request->getParams()['url'];
        }
        if (isset($request->getParams()['urlref'])) {
            $urlsToCheck[] = $request->getParams()['urlref'];
        }

        foreach ($urlsToCheck as $urlToCheck) {
            if ($this->isPlatformParamForThisPlatformInUrl($urlToCheck)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts advertisement platform specific data from the request (referrer, query params) and returns it as an
     * associative array.
     *
     * TODO: Check if we should use $action->getActionUrl() instead of or in addition to $request->getParams()['url'].
     *
     * @param Request $request
     * @return null|array
     */
    public function getAdParamsFromRequest(Request $request)
    {
        $paramPrefix = $this->getSettings()->paramPrefix->getValue();

        // Check current URL before referrer URL
        $urlsToCheck = [];
        if (isset($request->getParams()['url'])) {
            $urlsToCheck[] = $request->getParams()['url'];
        }
        if (isset($request->getParams()['urlref'])) {
            $urlsToCheck[] = $request->getParams()['urlref'];
        }

        foreach ($urlsToCheck as $urlToCheck) {

            // TODO: Should we combine the results of all the different checks instead of simpy return the first match?

            // Try to get adParams from referrer
            // TODO: Rename adParams into something which also works for data extracted from the referrer?

            // Try to get adParams from
            $queryString = parse_url($urlToCheck, PHP_URL_QUERY);
            parse_str($queryString, $queryParams);

            $platform = AOM::getPlatformInstance($this->getUnqualifiedClassName());
            $adParams = $platform->getAdParamsFromUrl($urlToCheck, $queryParams, $paramPrefix, $request);
            if (is_array($adParams) && count($adParams) > 0) {
                return $adParams;
            }
        }

        return null;
    }

    /**
     * Extracts and returns advertisement platform specific data from an URL.
     * $queryParams and $paramPrefix are only passed as params for convenience reasons.
     *
     * @param string $url
     * @param array $queryParams
     * @param string $paramPrefix
     * @param Request $request
     * @return array|null
     */
    abstract protected function getAdParamsFromUrl($url, array $queryParams, $paramPrefix, Request $request);

    /**
     * Extracts advertisement data from the ad params and stores it in log_visit.aom_ad_data.
     * At this point it is likely that there is no actual ad data available. In this case historical data is used to
     * add some basic information.
     * The implementation of this method must ensure a consistently ordered JSON.
     *
     * @param string $idSite
     * @param array $adParams
     * @param string $date
     * @return mixed
     */
    abstract public function getAdDataFromAdParams($idSite, array $adParams, $date = null);

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
     * When triggered via scheduled tasks, imported platform data is being merged automatically afterwards.
     *
     * @param bool $mergeAfterwards
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return mixed
     * @throws Exception
     */
    public function import($mergeAfterwards = false, $startDate = null, $endDate = null)
    {
        if (!$this->isActive()) {
            return;
        }

        /** @var Importer $importer */
        $importer = AOM::getPlatformInstance($this->getUnqualifiedClassName(), 'Importer');
        $importer->setPeriod($startDate, $endDate);
        $importer->import();

        if ($mergeAfterwards) {

            // We must use the importer's period, as $startDate and $endDate can be null or could have been modified
            $this->logger->debug(
                'Will merge ' .  $this->getUnqualifiedClassName() . ' for period from ' . $importer->getStartDate()
                . ' until ' . $importer->getEndDate() . ' on a daily basis now.'
            );
            // We merge on a daily basis, primarily due to performance issues
            foreach (AOM::getPeriodAsArrayOfDates($importer->getStartDate(), $importer->getEndDate()) as $date) {
                $this->merge($date, $date);
            }
        }
    }

    /**
     * Merges platform data for the specified period.
     * If no period has been specified, we'll try to merge yesterdays data only.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return mixed
     * @throws Exception
     */
    public function merge($startDate, $endDate)
    {
        if (!$this->isActive()) {
            return;
        }

        /** @var Merger $merger */
        $merger = AOM::getPlatformInstance($this->getUnqualifiedClassName(), 'Merger');
        $merger->setPeriod($startDate, $endDate);
        $merger->setPlatform($this);
        $merger->merge();
    }

    /**
     * Deletes all imported data for the given combination of platform account, website and date.
     *
     * @param string $platformName
     * @param string $accountId
     * @param int $websiteId
     * @param string $date
     * @return array
     */
    public static function deleteImportedData($platformName, $accountId, $websiteId, $date)
    {
        $timeStart = microtime(true);
        $deletedImportedDataRecords = Db::deleteAllRows(
            AOM::getPlatformDataTableNameByPlatformName($platformName),
            'WHERE id_account_internal = ? AND idsite = ? AND date = ?',
            'date',
            100000,
            [
                $accountId,
                $websiteId,
                $date,
            ]
        );
        $timeToDeleteImportedData = microtime(true) - $timeStart;

        return [$deletedImportedDataRecords, $timeToDeleteImportedData];
    }

    /**
     * Updates aom_ad_data and aom_platform_row_id to NULL of all visits that match the given combination of platform,
     * website and date.
     *
     * @param string $platformName
     * @param int $websiteId
     * @param string $date
     * @return array
     */
    public static function deleteMergedData($platformName, $websiteId, $date)
    {
        $timeStart = microtime(true);
        $unsetMergedDataRecords = Db::query(
            'UPDATE ' . Common::prefixTable('log_visit') . ' AS v
                LEFT OUTER JOIN ' . AOM::getPlatformDataTableNameByPlatformName($platformName) . ' AS p
                ON (p.id = v.aom_platform_row_id)
                SET v.aom_ad_data = NULL, v.aom_platform_row_id = NULL
                WHERE v.idsite = ? AND v.aom_platform = ? AND p.id IS NULL
                    AND visit_first_action_time >= ? AND visit_first_action_time <= ?',
            [
                $websiteId,
                $platformName,
                AOM::convertLocalDateTimeToUTC($date . ' 00:00:00', Site::getTimezoneFor($websiteId)),
                AOM::convertLocalDateTimeToUTC($date . ' 23:59:59', Site::getTimezoneFor($websiteId)),
            ]
        );
        $timeToUnsetMergedData = microtime(true) - $timeStart;

        return [$unsetMergedDataRecords, $timeToUnsetMergedData];
    }

    /**
     * Removes all reprocessed visits for the combination of website and date.
     *
     * @param int $websiteId
     * @param string $date
     * @return array
     */
    public static function deleteReprocessedData($websiteId, $date)
    {
        $timeStart = microtime(true);
        $deletedReprocessedVisitsRecords = Db::deleteAllRows(
            Common::prefixTable('aom_visits'),
            'WHERE idsite = ? AND date_website_timezone = ?',
            'date_website_timezone',
            100000,
            [
                $websiteId,
                $date,
            ]
        );
        $timeToDeleteReprocessedVisits = microtime(true) - $timeStart;

        return [$deletedReprocessedVisitsRecords, $timeToDeleteReprocessedVisits];
    }

    /**
     * Returns the platform's unqualified class name.
     *
     * @return string
     */
    protected function getUnqualifiedClassName()
    {
        return substr(strrchr(get_class($this), '\\'), 1);
    }

    /**
     * Returns the platform's name.
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

    /**
     * Returns an instance of MarketingPerformanceSubTables when drill down through Piwik UI is supported.
     * Returns false, if not.
     *
     * @return MarketingPerformanceSubTablesInterface|false
     */
    abstract public function getMarketingPerformanceSubTables();

    /**
     * Returns a platform-specific description of a specific visit optimized for being read by humans or false when no
     * platform-specific description is available.
     *
     * @param int $idVisit
     * @return false|string
     * @throws Exception
     */
    public static function getHumanReadableDescriptionForVisit($idVisit)
    {
        $platform = Db::fetchOne(
            'SELECT aom_platform FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?', [$idVisit]
        );

        if (in_array($platform, AOM::getPlatforms())) {

            $platform = AOM::getPlatformInstance($platform);

            return $platform->getHumanReadableDescriptionForVisit($idVisit);
        }

        return false;
    }

    /**
     * Returns true if the platform param exists in the given URL and if its value is the current's platform's name.
     * False otherwise.
     *
     * @param string $url
     * @return bool
     */
    protected function isPlatformParamForThisPlatformInUrl($url)
    {
        $paramPrefix = $this->getSettings()->paramPrefix->getValue();

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        return (is_array($queryParams)
            && array_key_exists($paramPrefix . '_platform', $queryParams)
            && $this->getName() === $queryParams[$paramPrefix . '_platform']
        );
    }
}
