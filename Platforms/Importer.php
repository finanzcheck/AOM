<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Plugins\AOM\AOM;
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
     * Imports platform data.
     *
     * @return mixed
     */
    abstract public function import();

    /**
     * Deletes all imported data for the given combination of platform account, website and date.
     * Updates aom_ad_data and aom_platform_row_id to NULL of all visits who lost their related platform cost records.
     * Removes all reprocessed visits for the combination of website and date!
     *
     * @param string $platformName
     * @param string $accountId
     * @param int $websiteId
     * @param string $date
     */
    public function deleteExistingData($platformName, $accountId, $websiteId, $date)
    {
        // Delete all imported data for the given combination of platform account, website and date
        list($deletedImportedDataRecords, $timeToDeleteImportedData) =
            Platform::deleteImportedData($platformName, $accountId, $websiteId, $date);

        // Updates aom_ad_data and aom_platform_row_id to NULL of all visits who lost their related platform records
        list($unsetMergedDataRecords, $timeToUnsetMergedData) =
            Platform::deleteMergedData($platformName, $websiteId, $date);

        // Removes all reprocessed visits for the combination of website and date!
        list($deletedReprocessedVisitsRecords, $timeToDeleteReprocessedVisits) =
            Platform::deleteReprocessedData($websiteId, $date);
 
        $this->logger->debug(
            sprintf(
                'Deleted existing %s data (%fs for %d imported data records, %fs for %d merged data records, '
                    . '%fs for %d reprocessed data records).',
                $platformName,
                $timeToDeleteImportedData,
                is_int($deletedImportedDataRecords) ? $deletedImportedDataRecords : 0,
                $timeToUnsetMergedData,
                is_int($unsetMergedDataRecords) ? $unsetMergedDataRecords : 0,
                $timeToDeleteReprocessedVisits,
                is_int($deletedReprocessedVisitsRecords) ? $deletedReprocessedVisitsRecords : 0
            ),
            ['platform' => $platformName, 'task' => 'import']
        );
    }
}
