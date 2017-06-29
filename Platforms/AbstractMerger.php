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
use Piwik\Site;
use Psr\Log\LoggerInterface;

class AbstractMerger
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = (null === $logger ? AOM::getLogger() : $logger);
    }

    public function allocateCostOfPlatformRow($platformName, $platformRowId, $platformKey, array $platformData)
    {
        // Get cost according to platform
        $platformRow = Db::fetchRow(
            'SELECT idsite, date, cost FROM ' . AOM::getPlatformDataTableNameByPlatformName($platformName)
                . ' WHERE id = ?',
            [$platformRowId,]
        );
        list($idsite, $date, $cost) = [$platformRow['idsite'], $platformRow['date'], $platformRow['cost']];

        // When there are no cost, there is nothing to allocate.
        if (0 == $cost) {
            return;
        }

        // Get number and cost of matching visits in aom_visits
        $matchingVisits = $this->getAomVisitsByPlatformKey($idsite, $date, $platformName, $platformKey);

        // When there are both, real and artificial visits, artificial visits are not allowed to have any cost
        if ($matchingVisits['piwikVisits'] > 0 && $matchingVisits['artificialVisitsCost'] > 0) {
            Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits') . ' SET cost = 0 AND ts_last_update = NOW() '
                    . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ? '
                    . ' AND piwik_idvisit IS NULL',
                [$idsite, $date, $platformName, $platformKey,]
            );
            $this->logger->debug('Updated cost of artificial visit to 0 as also real visits where found.');
        }

        // If there are real visits, distribute cost between them
        if ($matchingVisits['piwikVisits'] > 0) {
            $costPerVisit = number_format($cost / $matchingVisits['piwikVisits'], 4);
            Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits') . ' SET cost = ? AND ts_last_update = NOW() '
                . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ? '
                . ' AND piwik_idvisit IS NOT NULL',
                [$costPerVisit, $idsite, $date, $platformName, $platformKey,]
            );
            $this->logger->debug('Updated cost of real visits to ' . $costPerVisit . '.');
            return;
        }

        // If there are no real visits and no artificial visits, create artificial visit with cost
        if (0 == $matchingVisits['totalVisits']) {
            $this->addArtificialVisit($idsite, $date, $platformName, $platformData, $platformKey);
            return;
        }

        // If there are no real visit but an artificial visit, update artificial visit if costs are not correct
        if (round($cost) != round($matchingVisits['artificialVisitsCost'])) {
            Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits') . ' SET cost = ? AND ts_last_update = NOW() '
                . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ? '
                . ' AND piwik_idvisit IS NULL',
                [$cost, $idsite, $date, $platformName, $platformKey,]
            );
            $this->logger->debug('Updated cost of artificial visit to ' . $cost . '.');
            return;
        }

        die('XXX');
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
     * @param $idsite
     * @param $date
     * @param $platformName
     * @param array $platformData
     * @param $platformKey
     */
    private function addArtificialVisit($idsite, $date, $platformName, array $platformData, $platformKey)
    {
        // We must avoid having the same record multiple times in this table, e.g. when this command is being executed
        // in parallel. Manually created visits must create consistent unique hashes from the same raw data.
        $uniqueHash = $idsite . '-' . $date . '-' . $platformName . '-' . hash('md5', json_encode($platformData));

        Db::query(
            'INSERT INTO ' . Common::prefixTable('aom_visits')
            . ' (idsite, unique_hash, first_action_time_utc, date_website_timezone, channel, platform_data, '
            . ' platform_key, ts_last_update, ts_created) '
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $idsite,
                $uniqueHash,
                AOM::convertLocalDateTimeToUTC($date . ' 00:00:00', Site::getTimezoneFor($idsite)),
                $date,
                $platformName,
                json_encode($platformData),
                $platformKey,
            ]
        );

        $this->logger->debug('Added artificial visit to aom_visit table.');
    }
}
