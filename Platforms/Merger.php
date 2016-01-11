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

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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

        $this->logger->debug('Got ' . count($visits) . ' visits.');

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
        return DB::fetchAll(
            'SELECT * FROM ' . $this->platform->getDataTableName() . ' WHERE date >= ? AND date <= ?',
            [
                $this->startDate,
                $this->endDate,
            ]
        );
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
            }

            $sql .= ' WHERE idvisit = ' . $idvisit;

            DB::exec($sql);
        }
    }
}
