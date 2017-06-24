<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Monolog\Logger;
use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\SitesManager\API as APISitesManager;
use Psr\Log\LoggerInterface;

abstract class Merger
{
    /**
     * @var Platform
     */
    protected $platform;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The merge-period's start date.
     *
     * @var string
     */
    protected $startDate;

    /**
     * The merge-period's end date.
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
     * Sets the period that should be merged.
     *
     * @param null|string $startDate YYYY-MM-DD
     * @param null|string $endDate YYYY-MM-DD
     */
    public function setPeriod($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * @param Platform $platform
     */
    public function setPlatform($platform)
    {
        $this->platform = $platform;
    }

    /**
     * Merges platform data.
     *
     * @return mixed
     */
    abstract public function merge();

    /**
     * Returns all relevant visits.
     * During merging website and date scopes are being considered.
     *
     * @return array
     * @throws \Exception
     */
    protected function getVisits()
    {
        // We assume that the website's timezone matches the timezone of all advertising platforms.

        $visits = [];

        // We get visits per website to consider the website's individual timezones.
        foreach (APISitesManager::getInstance()->getAllSites() as $site) {
            $visits = array_merge(
                $visits,
                DB::fetchAll(
                    'SELECT * FROM  ' . Common::prefixTable('log_visit') . '
                        WHERE
                            idsite = ? AND aom_platform = ? AND
                            visit_first_action_time >= ? AND visit_first_action_time <= ?',
                    [
                        $site['idsite'],
                        $this->platform->getName(),
                        AOM::convertLocalDateTimeToUTC($this->startDate . ' 00:00:00', $site['timezone']),
                        AOM::convertLocalDateTimeToUTC($this->endDate . ' 23:59:59', $site['timezone']),
                    ]
                )
            );
        }

        $this->log(
            Logger::DEBUG,
            'Got ' . count($visits) . ' visits for all websites in period from ' . $this->startDate
                . ' 00:00:00 until ' . $this->endDate . ' 23:59:59 (in the website\'s individual timezones).'
        );

        return $visits;
    }

    /**
     * Returns all platform data within the period.
     * During merging website and date scopes are being considered.
     *
     * @return array
     * @throws \Exception
     */
    protected function getPlatformData()
    {
        $platformData = Db::fetchAll(
            'SELECT * FROM ' . $this->platform->getDataTableName() . ' WHERE date >= ? AND date <= ?',
            [
                $this->startDate,
                $this->endDate,
            ]
        );

        $this->log(
            Logger::DEBUG,
            'Got ' . count($platformData) . ' platform cost records in period from ' . $this->startDate
                . ' 00:00:00 until ' . $this->endDate . ' 23:59:59 UTC.'
        );

        return $platformData;
    }

    /**
     * Updates several visits with the given data.
     *
     * @param array $updateVisits A map with two entries: idvisit and an array for fields and values to be set
     * @throws \Exception
     */
    protected function updateVisits(array $updateVisits)
    {
        // TODO: Use only one statement
        foreach($updateVisits as list($idvisit, $updates)) {
            $sql = 'UPDATE ' . Common::prefixTable('log_visit') . ' SET ';

            $firstUpdate = true;
            foreach ($updates as $key => $val) {
                if ($firstUpdate) {
                    $firstUpdate = false;
                } else {
                    $sql .= ', ';
                }
                $sql .= $key.' = \''. $val.'\'';

                if ('aom_ad_data' === $key) {
                    Piwik::postEvent('AOM.updateVisitAdData', ['idvisit' => $idvisit, 'adData' => $val]);
                }
            }

            $sql .= ' WHERE idvisit = ' . $idvisit;

            Db::exec($sql);
        }
    }

    /**
     * This method must be implemented in child classes.
     *
     * @param array $adData
     * @return mixed
     */
    protected abstract function buildKeyFromAdData(array $adData);

    /**
     * @return array
     */
    protected function getAdData()
    {
        $platformData = $this->getPlatformData();

        $adDataMap = [];
        foreach ($platformData as $row) {
            $key = $this->buildKeyFromAdData($row);
            if (isset($adDataMap[$key])) {
                $this->log(
                    Logger::WARNING,
                    'Key "' . $key. '" is not unique!',
                    [
                        'current' => $row,
                        'existing' => $adDataMap[$key]
                    ]
                );
            }
            $adDataMap[$key] = $row;
        }
        return $adDataMap;
    }

    /**
     * Convenience function for shorter logging statements
     *
     * @param string $logLevel
     * @param string $message
     * @param array $additionalContext
     */
    private function log($logLevel, $message, $additionalContext = [])
    {
        $this->logger->log(
            $logLevel,
            $message,
            array_merge(['platform' => $this->platform->getName(), 'task' => 'merge'], $additionalContext)
        );
    }

    /**
     * Deletes all merged data (updates aom_ad_data and aom_platform_row_id to NULL) for the given combination of
     * platform account, website and date.
     * Removes all reprocessed visits for the combination of website and date.
     *
     * @param string $platformName
     * @param int $websiteId
     */
    public function deleteExistingData($platformName, $websiteId)
    {
        foreach (AOM::getPeriodAsArrayOfDates($this->startDate, $this->endDate) as $date) {

            // Updates aom_ad_data and aom_platform_row_id to NULL of all visits who lost their related platform records
            list($unsetMergedDataRecords, $timeToUnsetMergedData) =
                Platform::deleteMergedData($platformName, $websiteId, $date);

            // Removes all reprocessed visits for the combination of website and date!
            list($deletedReprocessedVisitsRecords, $timeToDeleteReprocessedVisits) =
                Platform::deleteReprocessedData($websiteId, $date);

            $this->logger->debug(
                sprintf(
                    'Deleted existing %s data for date %s '
                    . '(%fs for %d merged data records, %fs for %d reprocessed data records).',
                    $platformName,
                    $date,
                    $timeToUnsetMergedData,
                    is_int($unsetMergedDataRecords) ? $unsetMergedDataRecords : 0,
                    $timeToDeleteReprocessedVisits,
                    is_int($deletedReprocessedVisitsRecords) ? $deletedReprocessedVisitsRecords : 0
                ),
                ['platform' => $platformName, 'task' => 'merge']
            );
        }
    }
}
