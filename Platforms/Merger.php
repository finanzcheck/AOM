<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Common;
use Piwik\Db;
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
     * Merges yesterday's and today's data as default.
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
     * @param Platform $platform
     */
    public function setPlatform($platform)
    {
        $this->platform = $platform;
    }

    /**
     * Returns all relevant visits
     *
     * @return array
     * @throws \Exception
     */
    protected function getVisits()
    {
        // TODO: Convert local datetime into UTC before querying visits (by iterating website for website?)
        // TODO: Example AOM::convertLocalDateTimeToUTC($this->startDate, Site::getTimezoneFor($idsite))
        // TODO: The example returns 2015-12-19 23:00:00 for startDate 2015-12-20 00:00:00 for Europe/Berlin.
        // We assume that the website's timezone matches the timezone of all advertising platforms.
        return DB::fetchAll(
            'SELECT * FROM  ' . Common::prefixTable('log_visit')
            . '  WHERE visit_first_action_time >= ? AND visit_first_action_time <= ? AND aom_platform = ?',
            [
                $this->startDate,
                $this->endDate,
                $this->platform->getName(),
            ]
        );
    }

    /**
     * Returns all relevant ad data
     *
     * @return array
     * @throws \Exception
     */
    protected function getAdData()
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
     * Updates several visits
     *
     * @param array $updateVisits contains a map with two entries: idvisit and an array for setting fields
     * @throws \Exception
     */
    protected function updateVisits(array $updateVisits)
    {
        // TODO: Use only one statement
        foreach($updateVisits as list($idvisit, $updates)) {
            $sql = 'UPDATE ' . Common::prefixTable('log_visit') . ' SET ';

            $firstUpdate = true;
            foreach($updates as $key => $val) {
                if($firstUpdate) {
                    $firstUpdate = false;
                } else {
                    $sql.= ', ';
                }
                $sql.= $key.' = \''. $val.'\'';
            }

            $sql.= ' WHERE idvisit = ' . $idvisit;

            DB::exec($sql);
        }
    }
}
