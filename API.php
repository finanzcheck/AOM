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
use Piwik\Plugin\Manager;
use Piwik\Plugin\Report;
use Piwik\Common;
use Piwik\DataAccess\LogAggregator;
use Piwik\Db;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Segment;
use Piwik\Site;

class API extends \Piwik\Plugin\API
{
    /**
     * Returns all visits with marketing information within the given period.
     * ?module=API&token_auth=...&method=AOM.getVisits&idSite=1&period=day&date=2015-05-01&format=json
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

        $visits = $this->queryVisits(
            $idSite,
            $period->getDateStart()->toString('Y-m-d 00:00:00'),
            $period->getDateEnd()->toString('Y-m-d 23:59:59')
        );

        return $visits;
    }

    /**
     * Returns a specific ecommerce order by orderId with all visits with marketing information that happened before the
     * ecommerce order or false (when no ecommerce order could be found for the given orderId):
     * ?module=API&token_auth=...&method=AOM.getEcommerceOrderWithVisits&orderId=123&idSite=1&format=json
     *
     * @param int $idSite Id Site
     * @param string $orderId
     * @return array|false
     */
    public function getEcommerceOrderWithVisits($idSite, $orderId)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $order = Db::fetchRow(
            'SELECT
                    idorder AS orderId,
                    idvisitor,
					conv(hex(idvisitor), 16, 10) as visitorId,
					' . LogAggregator::getSqlRevenue('revenue') . ' AS amountOriginal,
                    log_conversion.server_time AS conversionTime
                FROM ' . Common::prefixTable('log_conversion') . ' AS log_conversion
                WHERE
                    log_conversion.idsite = ? AND
                    log_conversion.idgoal = "' . Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_ORDER . '" AND
                    log_conversion.idorder = ?
                ORDER BY server_time ASC
                LIMIT 1',
            [
                $idSite,
                $orderId
            ]
        );

        if ($order && is_array($order)) {
            $order['visits'] = $this->queryVisits($idSite, null, $order['conversionTime'], $order['idvisitor']);
            unset($order['idvisitor']);
        }

        return $order;
    }

    /**
     * Returns all ecommerce orders with all visits with marketing information that happened before the ecommerce order:
     * ?module=API&token_auth=...&method=AOM.getOrdersWithVisits&idSite=1&period=day&date=2015-05-01&format=json
     *
     * @param int $idSite Id Site
     * @param bool|string $period Period to restrict to when looking at the logs
     * @param bool|string $date Date to restrict to
     * @return array
     * @throws Exception
     */
    public function getEcommerceOrdersWithVisits($idSite, $period = false, $date = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        // disabled for multiple dates
        if (Period::isMultiplePeriod($date, $period)) {
            throw new Exception('AOM.getEcommerceOrdersWithVisits does not support multiple dates.');
        }

        // TODO: This is a period and not a range?!
        // TODO: Timezones and periods might currently not be handled correctly!!

        /** @var Range $period */
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        $orders = Db::fetchAll(
            'SELECT
                    idorder AS orderId,
                    idvisitor,
					conv(hex(idvisitor), 16, 10) as visitorId,
					' . LogAggregator::getSqlRevenue('revenue') . ' AS amountOriginal,
                    log_conversion.server_time AS conversionTime
                FROM ' . Common::prefixTable('log_conversion') . ' AS log_conversion
                WHERE
                    log_conversion.idsite = ? AND
                    log_conversion.idgoal = "' . Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_ORDER . '" AND
                    log_conversion.server_time >= ? AND
                    log_conversion.server_time <= ?
                ORDER BY server_time ASC',
            [
                $idSite,
                $period->getDateStart()->toString('Y-m-d 00:00:00'),
                $period->getDateEnd()->toString('Y-m-d 23:59:59')
            ]
        );

        foreach ($orders as &$order) {
            $order['visits'] = $this->queryVisits($idSite, null, $order['conversionTime'], $order['idvisitor']);
            unset($order['idvisitor']);
        }

        return $orders;
    }

    /**
     * Returns all visits that match the given criteria.
     * All visits are being enriched with advanced marketing information when applicable.
     *
     * @param int $idSite Id Site
     * @param string $visitFirstActionTimeMin
     * @param string $visitFirstActionTimeMax
     * @param string $idVisitor
     * @return array
     * @throws \Exception
     */
    private function queryVisits(
        $idSite,
        $visitFirstActionTimeMin = null,
        $visitFirstActionTimeMax = null,
        $idVisitor = null
    )
    {
        $sql = 'SELECT
                    ' . (null === $idVisitor ? 'conv(hex(idvisitor), 16, 10) AS visitorId, ' : '') . '
                    idvisit AS visitId,
                    visit_first_action_time AS firstActionTime,
                    CASE config_device_type
                        WHEN 0 THEN "desktop"
                        WHEN 1 THEN "smartphone"
                        WHEN 2 THEN "tablet"
                        WHEN 3 THEN "feature-phone"
                        WHEN 4 THEN "console"
                        WHEN 5 THEN "tv"
                        WHEN 6 THEN "car-browser"
                        WHEN 7 THEN "smart-display"
                        WHEN 8 THEN "camera"
                        WHEN 9 THEN "portable-media-player"
                        WHEN 10 THEN "phablet"
                        ELSE ""
                    END AS device,
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
                    ' . (in_array(
                            'AdvancedCampaignReporting',
                            Manager::getInstance()->getInstalledPluginsName())
                        ? 'campaign_name AS campaignName,
                           campaign_keyword AS campaignKeyword,
                           campaign_source AS campaignSource,
                           campaign_medium AS campaignMedium,
                           campaign_content AS campaignContent,
                           campaign_id AS campaignId,'
                        : ''
                    ) . '
                    aom_ad_data AS adData
                FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                WHERE
                    ' . (null != $visitFirstActionTimeMin ? 'visit_first_action_time >= ? AND' : '') . '
                    ' . (null != $visitFirstActionTimeMax ? 'visit_first_action_time <= ? AND' : '') . '
                    ' . (null != $idVisitor ? 'idvisitor = ? AND' : '') . '
                    idsite = ?
                ORDER BY visit_last_action_time ASC';

        $parameters = [];
        if (null != $visitFirstActionTimeMin) {
            $parameters[] = $visitFirstActionTimeMin;
        }
        if (null != $visitFirstActionTimeMax) {
            $parameters[] = $visitFirstActionTimeMax;
        }
        if (null != $idVisitor) {
            $parameters[] = $idVisitor;
        }
        $parameters[] = $idSite;

        $visits = Db::fetchAll($sql, $parameters);

        // Enrich visits with advanced marketing information
        if (is_array($visits)) {
            foreach ($visits as &$visit) {
                $this->enrichVisit($visit);
            }
        }

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
        // TODO: Use adKey instead or in addition to adData?!
        if (is_array($visit) && array_key_exists('adData', $visit) || 0 === strlen($visit['adData'])) {
            $ad = @json_decode($visit['adData'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($ad) && array_key_exists('platform', $ad)) {

                // Platform supported?
                if (in_array($ad['platform'], AOM::getPlatforms())) {

                    $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $ad['platform'] . '\\' . $ad['platform'];

                    /** @var PlatformInterface $platform */
                    $platform = new $className();

                    return $platform->enrichVisit($visit, $ad);
                }
            }
        }

        return $visit;
    }
}
