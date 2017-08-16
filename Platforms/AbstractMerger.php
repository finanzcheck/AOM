<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Plugins\AOM\Services\PiwikVisitService;
use Piwik\Site;
use Psr\Log\LoggerInterface;

abstract class AbstractMerger
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The start date of the period to merge (YYYY-MM-DD).
     *
     * @var string
     */
    protected $startDate;

    /**
     * The end date of the period to merge (YYYY-MM-DD).
     *
     * @var string
     */
    protected $endDate;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = (null === $logger ? AOM::getLogger() : $logger);
    }

    /**
     * Sets the period that should be merged.
     *
     * TODO: Consider site timezone here?!
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     */
    public function setPeriod($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
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
     * Returns all platform rows of the given date.
     *
     * @param string $platformName
     * @param string $date
     * @return array
     */
    protected function getPlatformRows($platformName, $date)
    {
        $platformRows = Db::fetchAll(
            'SELECT * FROM ' . DatabaseHelperService::getTableNameByPlatformName($platformName) . ' '
                . ' WHERE date = ? ORDER BY cost DESC',
            [$date,]
        );

        $this->logger->debug('Got ' . count($platformRows) . ' ' . $platformName . ' cost records for ' . $date .'.');

        return $platformRows;
    }

    public function allocateCostOfPlatformRowId($platformName, $platformRowId, $platformKey, array $platformData)
    {
        $this->allocateCostOfPlatformRow(
            $platformName,
            Db::fetchRow(
                'SELECT idsite, date, cost FROM ' . DatabaseHelperService::getTableNameByPlatformName($platformName)
                    . ' WHERE id = ?',
                [$platformRowId,]
            ),
            $platformKey,
            $platformData
        );
    }

    public function allocateCostOfPlatformRow($platformName, array $platformRow, $platformKey, array $platformData)
    {
        list($idsite, $date, $cost) = [$platformRow['idsite'], $platformRow['date'], $platformRow['cost']];

        // When there are no cost, there is nothing to allocate.
        if (0 == $cost) {
            return;
        }

        // Get number and cost of matching visits in aom_visits
        $matchingVisits = $this->getAomVisitsByPlatformKey($idsite, $date, $platformName, $platformKey);

        // When there are both, real and artificial visits, artificial visits are not allowed to have any cost
        if ($matchingVisits['piwikVisits'] > 0 && $matchingVisits['artificialVisitsCost'] > 0) {
            $this->updateArtificialVisit($idsite, $date, $platformName, $platformKey, 0);
            $this->logger->debug('Updated cost of artificial visit to 0 as also real visits where found.');
        }

        // If there are real visits, distribute cost between them
        if ($matchingVisits['piwikVisits'] > 0) {
            $costPerVisit = round(($cost / $matchingVisits['piwikVisits']), 4);
            $this->distributeCostBetweenRealVisits($idsite, $date, $platformName, $platformKey, $costPerVisit);
            return;
        }

        // If there are no real visits and no artificial visits, create artificial visit with cost
        if (0 == $matchingVisits['totalVisits']) {
            $this->addArtificialVisit($idsite, $date, $platformName, $platformData, $platformKey, $cost);
            return;
        }

        // If there are no real visit but an artificial visit, update artificial visit if costs are not correct
        if (round($cost, 4) != round($matchingVisits['artificialVisitsCost'], 4)) {
            $this->updateArtificialVisit($idsite, $date, $platformName, $platformKey, $cost);
            return;
        }
    }

    /**
     * @param int $idsite
     * @param string $date
     * @param string $platformName
     * @param string $platformKey
     * @return array
     */
    private function getAomVisitsByPlatformKey($idsite, $date, $platformName, $platformKey)
    {
        // TODO: Add key on aom_visits covering idsite, date and channel?
        $matchingVisits = Db::fetchRow(
            'SELECT COUNT(*) AS totalVisits, '
                . ' SUM(CASE WHEN piwik_idvisit IS NOT NULL THEN 1 ELSE 0 END) piwikVisits, '
                . ' SUM(CASE WHEN piwik_idvisit IS NULL THEN 1 ELSE 0 END) artificialVisits, '
                . ' SUM(CASE WHEN piwik_idvisit IS NULL THEN cost ELSE 0 END) artificialVisitsCost '
                . ' FROM ' . Common::prefixTable('aom_visits')
                . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ?',
            [$idsite, $date, $platformName, $platformKey,]
        );

        if ($matchingVisits['artificialVisits'] > 1) {
            $this->logger->warning(
                'Found more than one artificial visit (idsite ' . $idsite . ', date ' . $date . ', '
                    . 'channel ' . $platformName . ', platform key ' . $platformKey . ')'
            );
        }

        return $matchingVisits;
    }

    /**
     * @param int $idsite
     * @param string $date
     * @param string $platformName
     * @param array $platformData
     * @param string $platformKey
     * @param float $cost
     */
    private function addArtificialVisit($idsite, $date, $platformName, array $platformData, $platformKey, $cost)
    {
        // We must avoid having the same record multiple times in this table, e.g. when this command is being executed
        // in parallel. Manually created visits must create consistent unique hashes from the same raw data.
        $uniqueHash = $idsite . '-' . $date . '-' . $platformName . '-' . hash('md5', json_encode($platformData));

        Db::query(
            'INSERT INTO ' . Common::prefixTable('aom_visits')
                . ' (idsite, unique_hash, first_action_time_utc, date_website_timezone, channel, platform_data, '
                . ' platform_key, cost, ts_last_update, ts_created) '
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $idsite,
                $uniqueHash,
                AOM::convertLocalDateTimeToUTC($date . ' 00:00:00', Site::getTimezoneFor($idsite)),
                $date,
                $platformName,
                json_encode($platformData),
                $platformKey,
                $cost,
            ]
        );
        $aomVisitId = Db::fetchOne('SELECT LAST_INSERT_ID()');

        $this->logger->debug(
            'Added artificial visit ' . $aomVisitId . ' for platform key "' . $platformKey . '" to aom_visit table.'
        );

        PiwikVisitService::postAomVisitAddedOrUpdatedEvent($aomVisitId);
    }

    /**
     * @param int $idsite
     * @param string $date
     * @param string $platformName
     * @param string $platformKey
     * @param float $cost
     */
    private function updateArtificialVisit($idsite, $date, $platformName, $platformKey, $cost)
    {
        // This should usually be only one single artificial visit
        $affectedVisits = Db::fetchAll(
            'SELECT id FROM ' . Common::prefixTable('aom_visits')
                . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ? '
                . ' AND piwik_idvisit IS NULL AND (cost IS NULL OR cost != ?)',
            [$idsite, $date, $platformName, $platformKey, $cost,]
        );

        if (count($affectedVisits) > 0) {

            Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits') . ' SET cost = ?, ts_last_update = NOW() '
                    . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ? '
                    . ' AND piwik_idvisit IS NULL AND (cost IS NULL OR cost != ?)',
                [$cost, $idsite, $date, $platformName, $platformKey, $cost,]
            );

            $this->logger->debug('Updated cost of artificial visit to ' . $cost . '.');

            // Publish event for every single update
            foreach ($affectedVisits as $affectedVisit) {
                PiwikVisitService::postAomVisitAddedOrUpdatedEvent($affectedVisit['id']);
            }
        }
    }

    /**
     * Distributes cost between real visits and publishes event for every updated visit.
     * @param int $idsite
     * @param string $date
     * @param string $platformName
     * @param string $platformKey
     * @param float $costPerVisit
     */
    private function distributeCostBetweenRealVisits($idsite, $date, $platformName, $platformKey, $costPerVisit)
    {
        $affectedVisits = Db::fetchAll(
            'SELECT id FROM ' . Common::prefixTable('aom_visits')
                . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ? '
                . ' AND piwik_idvisit IS NOT NULL AND (cost IS NULL or cost != ?)',
                [$idsite, $date, $platformName, $platformKey, $costPerVisit,]
        );

        if (count($affectedVisits) > 0) {

            Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits') . ' SET cost = ?, ts_last_update = NOW() '
                    . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ? '
                    . ' AND piwik_idvisit IS NOT NULL AND (cost IS NULL or cost != ?)',
                [$costPerVisit, $idsite, $date, $platformName, $platformKey, $costPerVisit,]
            );

            $this->logger->debug(
                'Updated cost of ' . count($affectedVisits) . ' real visit' . (count($affectedVisits) != 1 ? 's' : '')
                    . ' to ' . number_format($costPerVisit, 4) . '.'
            );

            // Publish event for every single update
            foreach ($affectedVisits as $affectedVisit) {
                PiwikVisitService::postAomVisitAddedOrUpdatedEvent($affectedVisit['id']);
            }
        }
    }

    /**
     * Updates platform data of matching visits (including historic ones) and publishes event for every updated visit.
     *
     * @param int $idsite
     * @param string $platformKey
     * @param array $platformData
     */
    protected function updatePlatformData($idsite, $platformKey, array $platformData)
    {
        $affectedVisits = Db::fetchAll(
            'SELECT id FROM ' . Common::prefixTable('aom_visits')
                . ' WHERE idsite = ? AND platform_key = ? AND platform_data != ?',
            [$idsite, $platformKey, json_encode($platformData),]
        );

        if (count($affectedVisits) > 0) {

            Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits') . ' SET platform_data = ?, ts_last_update = NOW() '
                . ' WHERE idsite = ? AND platform_key = ? AND platform_data != ?',
                [json_encode($platformData), $idsite, $platformKey, json_encode($platformData),]
            );

            $this->logger->debug(
                'Updated platform data of ' . count($affectedVisits)
                    . ' record' . (count($affectedVisits) != 1 ? 's' : '')
                    . ' with platform key "' . $platformKey . '" in aom_visits table.'
            );

            // Publish event for every single update
            foreach ($affectedVisits as $affectedVisit) {
                PiwikVisitService::postAomVisitAddedOrUpdatedEvent($affectedVisit['id']);
            }
        }
    }

    /**
     * Validates the results of an entire merge.
     *
     * Compares the total imported cost to the merged visit's total cost.
     * Checks also the share of artificial visits.
     *
     * @param string $platformName
     * @param string $date
     */
    protected function validateMergeResults($platformName, $date)
    {
        // Compare the total imported cost to the merged visit's total cost

        $importedCost = Db::fetchOne(
            'SELECT SUM(cost) FROM ' . DatabaseHelperService::getTableNameByPlatformName($platformName)
                . ' WHERE date = ?',
            [$date,]
        );

        $mergedCost = Db::fetchOne(
            'SELECT SUM(cost) FROM ' . Common::prefixTable('aom_visits')
                . ' WHERE channel = ? AND date_website_timezone = ?',
            [$platformName, $date,]
        );

        if ($importedCost > 0) {
            $difference = ($mergedCost > 0) ? round(abs($importedCost / $mergedCost - 1) * 100, 2) : INF;
            $message = $platformName . '\'s imported cost ' . round($importedCost, 4) . ' differs from merged cost '
                . round($mergedCost, 4) . ' by ' . $difference . '% for ' . $date . '.';

            if ($difference > 1) {
                $this->logger->error($message);
            } elseif ($difference > 0.1) {
                $this->logger->warning($message);
            }
        }


        // Check the share of artificial visits

        $visits = Db::fetchRow(
            'SELECT COUNT(*) AS totalVisits, '
            . ' SUM(CASE WHEN piwik_idvisit IS NOT NULL THEN 1 ELSE 0 END) piwikVisits, '
            . ' SUM(CASE WHEN piwik_idvisit IS NULL THEN 1 ELSE 0 END) artificialVisits '
            . ' FROM ' . Common::prefixTable('aom_visits')
            . ' WHERE date_website_timezone = ? AND channel = ?',
            [$date, $platformName,]
        );

        if (0 == $visits['piwikVisits'] && $visits['artificialVisits'] > 0) {
            $this->logger->error(
                'Got ' . $visits['artificialVisits'] . ' artificial visits but no Piwik visits at ' . $date . '!'
            );
        } elseif ($visits['artificialVisits'] > 0) {
            $percentageOfArtificialVisits = ($visits['piwikVisits'] > 0)
                ? ($visits['artificialVisits'] / $visits['piwikVisits'] * 100) : INF;
            $message = 'Got ' . number_format($percentageOfArtificialVisits, 2) . '% (' . $visits['artificialVisits']
                . ') artificial visits (' . $visits['piwikVisits'] . ' Piwik visits) at ' . $date . '.';

            if ($percentageOfArtificialVisits > 10) {
                $this->logger->error($message);
            } elseif ($percentageOfArtificialVisits > 5) {
                $this->logger->warning($message);
            } else {
                $this->logger->info($message);
            }
        }
    }
}
