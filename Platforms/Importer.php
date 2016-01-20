<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Site;
use Psr\Log\LoggerInterface;

abstract class Importer
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The import period's start date.
     *
     * @var string
     */
    protected $startDate;

    /**
     * The import period's end date.
     *
     * @var string
     */
    protected $endDate;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = (null === $logger ? AOM::getTasksLogger() : $logger);
    }

    /**
     * Sets the period that should be imported.
     * Import yesterday's and today's data as default.
     *
     * TODO: Consider site timezone here?!
     *
     * @param null|string $startDate YYYY-MM-DD
     * @param null|string $endDate YYYY-MM-DD
     */
    public function setPeriod($startDate = null, $endDate = null)
    {
        if (null !== $startDate && null !== $endDate) {
            $this->startDate = $startDate;
            $this->endDate = $endDate;
        } else {
            $this->startDate = date('Y-m-d', strtotime('-1 day', time()));
            $this->endDate = date('Y-m-d');
        }
    }

    /**
     * @return null|string
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @return null|string
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Deletes all imported data for the given combination of platform account, website and date.
     * Updates aom_ad_data and aom_platform_row_id to NULL of all visits who lost their related platform cost records.
     * Removes all replenished visits for the combination of website and date!
     *
     * @param string $platformName
     * @param string $accountId
     * @param int $websiteId
     * @param string $date
     */
    public function deleteExistingData($platformName, $accountId, $websiteId, $date)
    {
        // Delete all imported data for the given combination of platform account, website and date
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

        // Updates aom_ad_data and aom_platform_row_id to NULL of all visits who lost their related platform records
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

        // Removes all replenished visits for the combination of website and date!
        $timeStart = microtime(true);
        $deletedReplenishedVisitsRecords = Db::deleteAllRows(
            Common::prefixTable('aom_visits'),
            'WHERE idsite = ? AND date_website_timezone = ?',
            'date_website_timezone',
            100000,
            [
                $websiteId,
                $date,
            ]
        );
        $timeToDeleteReplenishedVisits = microtime(true) - $timeStart;

        $this->logger->debug(
            sprintf(
                'Deleted existing %s data (%fs for %d imported data records, %fs for %d merged data records, '
                    . '%fs for %d replenished data records).',
                $platformName,
                $timeToDeleteImportedData,
                is_int($deletedImportedDataRecords) ? $deletedImportedDataRecords : 0,
                $timeToUnsetMergedData,
                is_int($unsetMergedDataRecords) ? $unsetMergedDataRecords : 0,
                $timeToDeleteReplenishedVisits,
                is_int($deletedReplenishedVisitsRecords) ? $deletedReplenishedVisitsRecords : 0
            )
        );
    }
}
