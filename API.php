<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Exception;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Archive;
use Piwik\DataTable\Row;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Common;
use Piwik\Db;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Segment;
use Piwik\Site;

class API extends \Piwik\Plugin\API
{
    /**
     * @var array PlatformInterface
     */
    private $platforms = [];

    public function __construct()
    {
        foreach (AOM::getPlatforms() as $platform) {
            $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $platform . '\\' . $platform;
            $this->platforms[strtolower($platform)] = new $className();
        }
    }

    /**
     * Returns all visits with enriched marketing information within the given period.
     *
     * @param int $idSite Id Site
     * @param bool|string $period Period to restrict to when looking at the logs
     * @param bool|string $date Date to restrict to
     * @return array
     * @throws Exception
     */
    public function getVisits($idSite, $period = false, $date = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        // disabled for multiple dates
        if (Period::isMultiplePeriod($date, $period)) {
            throw new Exception('AOM.getVisits does not support multiple dates.');
        }

        // TODO: This is a period and not a range?!
        // TODO: Timezones and periods might currently not be handled correctly!!

        /** @var Range $period */
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        $visits = $this->queryVisits($idSite, $period);

        // Enrich visits with advanced marketing information
        foreach ($visits as &$visit) {
            $this->enrichVisit($visit);
        }

        return $visits;
    }

    /**
     * Returns all visits that happened during the specified period.
     *
     * @param int $idSite Id Site
     * @param Range $period
     * @return array
     * @throws \Exception
     */
    private function queryVisits($idSite, Range $period)
    {
        $sql = 'SELECT
                    conv(hex(idvisitor), 16, 10) AS visitorId,
                    idvisit AS visitId,
                    IF (custom_var_k1 = "cafe_session_id", custom_var_v1, NULL) AS cafeSessionId,
                    visit_first_action_time AS firstActionTime,
                    CASE referer_type
                        WHEN 1 THEN "direct"
                        WHEN 2 THEN "search_engine"
                        WHEN 3 THEN "website"
                        WHEN 6 THEN "campaign"
                        ELSE ""
                    END AS source,
                    referer_name AS refererName,
                    referer_keyword AS refererKeyword,
                    referer_url AS refererUrl,
                    aom_ad_id AS adId
                FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                WHERE
                    idsite = ? AND
                    visit_first_action_time >= ? AND
                    visit_first_action_time <= ?
                ORDER BY visit_last_action_time ASC';

        $visits = Db::fetchAll(
            $sql,
            [
                $idSite,
                $period->getDateStart()->toString('Y-m-d 00:00:00'),
                $period->getDateEnd()->toString('Y-m-d 23:59:59'),
            ]
        );

        return $visits;
    }


    /**
     * Enriches a specific visit with advanced marketing information when applicable.
     *
     * @param array &$visit
     * @return array
     * @throws Exception
     */
    private function enrichVisit(&$visit)
    {
        if (0 === strlen($visit['adId']) || !strpos($visit['adId'], '|')) {
            return $visit;
        }

        $ad = explode('|', $visit['adId']);

        if (array_key_exists($ad[0], $this->platforms)) {
            $this->platforms[$ad[0]]->enrichVisit($visit, $ad);
        }

        return $visit;
    }
}
