<?php
/**
 * Provides an API-method to export visits with marketing information for conversions.
 *
 * Get all conversions with all visits with marketing info that happened before the conversion:
 * ?module=API&token_auth=...&method=FinanzcheckConversionAPI.get&idSite=1&period=day&date=2015-05-01&format=json
 *
 * Get specific conversion by cafeLeadId (orderid) with all visits with marketing info that happened before conversion:
 * ?module=API&token_auth=...&method=FinanzcheckConversionAPI.getByCafeLeadId&cafeLeadId=123&idSite=1&format=json
 *
 * @see https://finanzcheck.atlassian.net/browse/DW-87 & https://finanzcheck.atlassian.net/browse/FIN-2606
 */
namespace Piwik\Plugins\FinanzcheckConversionAPI;

use Exception;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Archive;
use Piwik\DataTable\Row;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Common;
use Piwik\DataAccess\LogAggregator;
use Piwik\Db;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Segment;
use Piwik\Site;

/**
 * Class API
 * @package Piwik\Plugins\FinanzcheckConversionAPI
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Returns all conversions with all visits with marketing info that happened before the conversion.
     *
     * @param int $idSite Id Site
     * @param bool|string $period Period to restrict to when looking at the logs
     * @param bool|string $date Date to restrict to
     * @return array
     * @throws Exception
     */
    public function get($idSite, $period = false, $date = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        // disabled for multiple dates
        if (Period::isMultiplePeriod($date, $period)) {
            throw new Exception('FinanzcheckConversionAPI.get does not support multiple dates.');
        }

        // TODO: This is a period and not a range?!
        // TODO: Timezones and periods might currently not be handled correctly!!

        /** @var Range $period */
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        $conversions = $this->queryEcommerceConversionsByPeriod($idSite, $period);

        foreach ($conversions as &$conversion) {
            $conversion['visits'] = $this->queryVisits(
                $idSite,
                $conversion['idvisitor'],
                $conversion['conversionTime']
            );
            unset($conversion['idvisitor']);
        }

        return $conversions;
    }

    /**
     * Returns a specific conversion by cafeLeadId (orderid) with all visits with marketing info that happened before
     * the conversion or false (when no conversion could be found for the given cafeLeadId).
     *
     * @param int $idSite Id Site
     * @param string $cafeLeadId
     * @return array|false
     */
    public function getByCafeLeadId($idSite, $cafeLeadId)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $conversion = $this->queryEcommerceConversionsByCafeLeadId($idSite, $cafeLeadId);

        if ($conversion) {
            $conversion['visits'] = $this->queryVisits(
                $idSite,
                $conversion['idvisitor'],
                $conversion['conversionTime']
            );
            unset($conversion['idvisitor']);
        }

        return $conversion;
    }

    /**
     * Returns all visits for a given visitor that started before the date of the conversion.
     *
     * @param int $idSite Id Site
     * @param $visitorId
     * @param $conversionTime
     * @return array
     * @throws Exception
     */
    private function queryVisits($idSite, $visitorId, $conversionTime)
    {
        $sql = 'SELECT
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
                    referer_name AS refererName,
                    referer_keyword AS refererKeyword,
                    referer_url AS refererUrl,
                    campaign_name AS campaignName,
                    campaign_keyword AS campaignKeyword,
                    campaign_source AS campaignSource,
                    campaign_medium AS campaignMedium,
                    campaign_content AS campaignContent,
                    campaign_id AS campaignId
                FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                WHERE
                    idsite = ? AND
                    idvisitor = ? AND
                    visit_first_action_time <= ?
                ORDER BY visit_last_action_time ASC';

        $visits = Db::fetchAll($sql, [$idSite, $visitorId, $conversionTime]);

        return $visits;
    }

    /**
     * Returns all conversions that happened during the specified period.
     *
     * @param int $idSite Id Site
     * @param Range $period
     * @return array
     * @throws \Exception
     */
    private function queryEcommerceConversionsByPeriod($idSite, Range $period)
    {
        $sql = 'SELECT
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
                ORDER BY server_time ASC';

        $ecommerceDetails = Db::fetchAll($sql,
            [
                $idSite,
                $period->getDateStart()->toString('Y-m-d 00:00:00'),
                $period->getDateEnd()->toString('Y-m-d 23:59:59')
            ]
        );

        return $ecommerceDetails;
    }

    /**
     * Returns a specific conversion by cafeLeadId (idorder) or false (when no conversion could be found).
     * When there a multiple conversions for the cafeLeadId, only the oldest one is being returned.
     *
     * @param int $idSite Id Site
     * @param string $cafeLeadId
     * @return array|false
     * @throws \Exception
     */
    private function queryEcommerceConversionsByCafeLeadId($idSite, $cafeLeadId)
    {
        $sql = 'SELECT
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
                LIMIT 1';

        $ecommerceDetails = Db::fetchAll($sql,
            [
                $idSite,
                $cafeLeadId
            ]
        );

        return ((is_array($ecommerceDetails) && count($ecommerceDetails) > 0) ? $ecommerceDetails[0] : false);
    }
}
