<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\API;

use Exception;
use Piwik\Common;
use Piwik\DataAccess\LogAggregator;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Db;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Plugin\Manager;
use Piwik\Plugins\AOM\AOM;
use Piwik\Site;

class VisitsController
{
    /**
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @return mixed
     * @throws Exception
     */
    public static function getVisits($idSite, $period, $date)
    {
        // disabled for multiple dates
        if (Period::isMultiplePeriod($date, $period)) {
            throw new Exception('AOM.getVisits does not support multiple dates.');
        }

        /** @var Range $period */
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        $visits = self::queryVisits(
            $idSite,
            AOM::convertLocalDateTimeToUTC(
                $period->getDateStart()->toString('Y-m-d 00:00:00'),
                Site::getTimezoneFor($idSite)
            ),
            AOM::convertLocalDateTimeToUTC(
                $period->getDateEnd()->toString('Y-m-d 23:59:59'),
                Site::getTimezoneFor($idSite)
            )
        );

        return $visits;
    }

    /**
     * @param int $idSite
     * @param string $orderId
     * @return bool|mixed
     * @throws Exception
     */
    public static function getEcommerceOrderWithVisits($idSite, $orderId)
    {
        $orders = self::getEcommerceOrdersWithVisits($idSite, $orderId);

        if ($orders && is_array($orders) && count($orders) > 0) {
            return $orders[0];
        }

        return false;
    }

    /**
     * TODO: Do e-commerce orders really always have log_conversion.idgoal = 0?
     *
     * @param int $idSite
     * @param string|bool $orderId
     * @param string|bool $period
     * @param string|bool $date
     * @return array
     * @throws Exception
     */
    public static function getEcommerceOrdersWithVisits($idSite, $orderId = false, $period = false, $date = false)
    {
        // Return specific ecommerce orders
        $orderIds = (is_array($orderId)
            ? $orderId
            : ((is_string($orderId) && strlen($orderId) > 0) ? [$orderId] : false));
        if ($orderIds) {

            $orders = Db::fetchAll(
                'SELECT
                    idorder AS orderId,
					conv(hex(idvisitor), 16, 10) as visitorId,
					' . LogAggregator::getSqlRevenue('revenue') . ' AS amountOriginal,
                    log_conversion.server_time AS conversionTime
                FROM ' . Common::prefixTable('log_conversion') . ' AS log_conversion
                WHERE
                    log_conversion.idsite = ? AND
                    log_conversion.idgoal = 0 AND
                    log_conversion.idorder IN ("' . implode('","', $orderIds) . '")
                ORDER BY server_time ASC',
                [
                    $idSite,
                ]
            );

        // Return all ecommerce orders within a given period
        } else {

            // Disabled for multiple dates
            if (Period::isMultiplePeriod($date, $period)) {
                throw new Exception('AOM.getEcommerceOrdersWithVisits does not support multiple dates.');
            }

            /** @var Range $period */
            $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

            $orders = Db::fetchAll(
                'SELECT
                    idorder AS orderId,
					conv(hex(idvisitor), 16, 10) as visitorId,
					' . LogAggregator::getSqlRevenue('revenue') . ' AS amountOriginal,
                    log_conversion.server_time AS conversionTime
                FROM ' . Common::prefixTable('log_conversion') . ' AS log_conversion
                WHERE
                    log_conversion.idsite = ? AND
                    log_conversion.idgoal = 0 AND
                    log_conversion.server_time >= ? AND
                    log_conversion.server_time <= ?
                ORDER BY server_time ASC',
                [
                    $idSite,
                    AOM::convertLocalDateTimeToUTC(
                        $period->getDateStart()->toString('Y-m-d 00:00:00'), Site::getTimezoneFor($idSite)
                    ),
                    AOM::convertLocalDateTimeToUTC(
                        $period->getDateEnd()->toString('Y-m-d 23:59:59'), Site::getTimezoneFor($idSite)
                    ),
                ]
            );
        }

        foreach ($orders as &$order) {
            // $order['conversionTime'] is already in UTC (we want all visits before this date time)
            $order['visits'] = self::queryVisits($idSite, null, $order['conversionTime'], $order['orderId']);
        }

        return $orders;
    }

    /**
     * Returns all visits that match the given criteria.
     *
     * TODO: This method should be based on aom_visits instead!
     *
     * @param int $idSite Id Site
     * @param string $visitFirstActionTimeMinUTC
     * @param string $visitFirstActionTimeMaxUTC
     * @param string $orderId
     * @return array
     * @throws \Exception
     */
    private static function queryVisits(
        $idSite,
        $visitFirstActionTimeMinUTC = null,
        $visitFirstActionTimeMaxUTC = null,
        $orderId = null
    )
    {
        $sql = 'SELECT
                    conv(hex(log_visit.idvisitor), 16, 10) AS visitorId,
                    log_visit.idvisit AS visitId,
                    log_visit.visit_first_action_time AS firstActionTime,
                    CASE log_visit.config_device_type
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
                    CASE log_visit.referer_type
                        WHEN 1 THEN "direct"
                        WHEN 2 THEN "search_engine"
                        WHEN 3 THEN "website"
                        WHEN 6 THEN "campaign"
                        ELSE ""
                    END AS source,
                    log_visit.referer_name AS refererName,
                    log_visit.referer_keyword AS refererKeyword,
                    log_visit.referer_url AS refererUrl,
                    log_visit.aom_platform AS platform,
                    ' . (in_array(
                            'MarketingCampaignsReporting',
                            Manager::getInstance()->getInstalledPluginsName())
                        ? 'log_visit.campaign_name AS campaignName,
                                   log_visit.campaign_keyword AS campaignKeyword,
                                   log_visit.campaign_source AS campaignSource,
                                   log_visit.campaign_medium AS campaignMedium,
                                   log_visit.campaign_content AS campaignContent,
                                   log_visit.campaign_id AS campaignId,'
                        : ''
                    ) . '
                    log_visit.aom_ad_params AS rawAdParams,
                    log_action_entry_action_name.name AS entryTitle,
                    log_action_entry_action_url.name AS entryUrl
                FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                LEFT JOIN ' . Common::prefixTable('log_action') . ' AS log_action_entry_action_name
                    ON log_visit.visit_entry_idaction_name = log_action_entry_action_name.idaction
                LEFT JOIN ' . Common::prefixTable('log_action') . ' AS log_action_entry_action_url
                    ON log_visit.visit_entry_idaction_url= log_action_entry_action_url.idaction
                ' . (null != $orderId
                ? 'JOIN ' . Common::prefixTable('log_conversion') . ' AS log_conversion
                        ON log_visit.idvisitor = log_conversion.idvisitor'
                : '') . '
                WHERE
                    ' . (null != $visitFirstActionTimeMinUTC ? 'log_visit.visit_first_action_time >= ? AND' : '') . '
                    ' . (null != $visitFirstActionTimeMaxUTC ? 'log_visit.visit_first_action_time <= ? AND' : '') . '
                    ' . (null != $orderId ? 'log_conversion.idorder = ? AND' : '') . '
                    log_visit.idsite = ?
                ORDER BY log_visit.visit_last_action_time ASC';

        $parameters = [];
        foreach ([$visitFirstActionTimeMinUTC, $visitFirstActionTimeMaxUTC, $orderId] as $param) {
            if (null != $param) {
                $parameters[] = $param;
            }
        }
        $parameters[] = $idSite;

        $visits = Db::fetchAll($sql, $parameters);

        // Enrich visits with advanced marketing information
        if (is_array($visits)) {
            foreach ($visits as &$visit) {

                // TODO: This is for Piwik < 2.15.1 (remove after a while)
                $visit['refererName'] = ('' === $visit['refererName'] ? null : $visit['refererName']);
                $visit['refererKeyword'] = ('' === $visit['refererKeyword'] ? null : $visit['refererKeyword']);

                // Make ad params JSON to associative array
                $visit['adParams'] = [];
                if (is_array($visit) && array_key_exists('rawAdParams', $visit) || 0 === strlen($visit['rawAdParams'])) {

                    $adParams = @json_decode($visit['rawAdParams'], true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($adParams)) {
                        $visit['adParams'] = $adParams;
                    }
                }

                // TODO: Add platform data!

                unset($visit['rawAdParams']);
            }
        }

        return $visits;
    }
}
