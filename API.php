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
use Piwik\Segment;
use Piwik\Site;

class API extends \Piwik\Plugin\API
{
    /**
     * Returns all visits with marketing information within the given period, e.g.:
     * ?module=API&token_auth=...&method=AOM.getVisits&idSite=1&period=day&date=2015-05-01&format=json
     * ?module=API&token_auth=...&method=AOM.getVisits&idSite=1&period=range&date=2015-05-01,2015-05-10&format=json
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

        /** @var Range $period */
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        $visits = $this->queryVisits(
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
        $orders = $this->getEcommerceOrdersWithVisits($idSite, $orderId);

        if ($orders && is_array($orders) && count($orders) > 0) {
            return $orders[0];
        }

        return false;
    }

    /**
     * This method can either return all ecommerce orders (with all visits with marketing information that happened
     * before the respective ecommerce orders) or return only the ecommerce orders which orderIds have been provided.
     *
     * To return all ecommerce orders:
     * ?module=API&token_auth=...&method=AOM.getEcommerceOrdersWithVisits&idSite=1&period=day&date=2015-05-01&format=json
     *
     * To return _one_ specific ecommerce order:
     * ?module=API&method=AOM.getEcommerceOrdersWithVisits&idSite=1&orderId=vz3LX010cxol&format=json
     *
     * To return specific ecommerce orders:
     * ?module=API&method=AOM.getEcommerceOrdersWithVisits&idSite=1&orderId[0]=vz3LX010cxol&orderId[1]=NzxkKq3qcbVd&orderId[2]=WwL7E0A3F6o0&format=json
     *
     * @param int $idSite Id Site
     * @param bool|string|array $orderId Zero or more IDs of ecommerce orders
     * @param bool|string $period Period to restrict to when looking at the logs
     * @param bool|string $date Date to restrict to
     * @return array
     * @throws Exception
     */
    public function getEcommerceOrdersWithVisits($idSite, $orderId = false, $period = false, $date = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

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
            $order['visits'] = $this->queryVisits($idSite, null, $order['conversionTime'], $order['orderId']);
        }

        return $orders;
    }

    /**
     * Returns all visits that match the given criteria.
     *
     * @param int $idSite Id Site
     * @param string $visitFirstActionTimeMinUTC
     * @param string $visitFirstActionTimeMaxUTC
     * @param string $orderId
     * @return array
     * @throws \Exception
     */
    private function queryVisits(
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
                            'AdvancedCampaignReporting',
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
                    log_visit.aom_ad_data AS rawAdData,
                    log_action_entry_action_name.name AS entryTitle,
                    log_action_entry_action_url.name AS entryUrl
                FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                JOIN ' . Common::prefixTable('log_action') . ' AS log_action_entry_action_name
                    ON log_visit.visit_entry_idaction_name = log_action_entry_action_name.idaction
                JOIN ' . Common::prefixTable('log_action') . ' AS log_action_entry_action_url
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

                // Make ad data JSON to associative array
                $visit['adData'] = [];
                if (is_array($visit) && array_key_exists('rawAdData', $visit) || 0 === strlen($visit['rawAdData'])) {

                    $adParams = @json_decode($visit['rawAdData'], true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($adParams)) {
                        $visit['adData'] = $adParams;
                    }
                }

                unset($visit['rawAdParams']);
                unset($visit['rawAdData']);
            }
        }

        return $visits;
    }

    /**
     * Returns various status information that can be used for monitoring:
     * ?module=API&token_auth=...&method=AOM.getStatus&format=json
     *
     * TODO: Add scoping for websites?
     *
     * @return array
     * @throws Exception
     */
    public function getStatus()
    {
        $status = [
            'stats' => [],
            'platforms' => [],
        ];

        foreach (['Hour', 'Day', 'Week'] as $period) {

            $visits = intval(Db::fetchOne(
                'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                     WHERE log_visit.visit_first_action_time >= ?',
                [
                    date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                ]));

            $orders = intval(Db::fetchOne(
                'SELECT COUNT(*) FROM ' . Common::prefixTable('log_conversion') . ' AS log_conversion
                     WHERE log_conversion.server_time >= ?',
                [
                    date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                ]));

            $status['stats']['last' . $period] = [
                'visits' => $visits,
                'orders' => $orders,
                'conversionRate' => ($visits > 0 ? $orders / $visits : 0),
            ];

            foreach (AOM::getPlatforms() as $platformName) {

                $platform = AOM::getPlatformInstance($platformName);
                $tableName = AOM::getPlatformDataTableNameByPlatformName($platformName);

                $status['platforms'][$platformName] = [
                    'daysSinceLastImportWithResults' =>
                        (Db::fetchOne('SELECT COUNT(*) FROM ' . $tableName) > 0)
                            ? intval(Db::fetchOne('SELECT DATEDIFF(CURDATE(), MAX(date)) FROM ' . $tableName))
                            : null,
                ];

                foreach (['Hour', 'Day'] as $period) {

                    $visitsWithPlatform = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $visitsWithAdParams = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"
                            AND aom_ad_params IS NOT NULL AND aom_ad_params != "null"',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $visitsWithAdData = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"
                            AND aom_ad_data IS NOT NULL AND aom_ad_data != "null"',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $visitsWithPlatformRowId = intval(Db::fetchOne(
                        'SELECT COUNT(*) FROM ' . Common::prefixTable('log_visit') . ' AS log_visit
                            WHERE log_visit.visit_first_action_time >= ? AND aom_platform = "' . $platformName . '"
                            AND aom_platform_row_id IS NOT NULL',
                        [
                            date('Y-m-d H:i:s', strtotime('-1 ' . $period))
                        ]));

                    $status['platforms'][$platformName]['last' . $period] = [
                        'visitsWithPlatform' => $visitsWithPlatform,
                        'visitsWithAdParams' => $visitsWithAdParams,
                        'visitsWithAdData' => $visitsWithAdData,
                        'visitsWithPlatformRowId' => $visitsWithPlatformRowId,
                    ];

                }
            }
        }

        return $status;
    }

    /**
     * Returns costs information for each platform for the given params, e.g.:
     * ?module=API&token_auth=...&method=AOM.getPlatformData&idSite=1&period=day&date=2015-05-01&format=json
     * ?module=API&token_auth=...&method=AOM.getPlatformData&idSite=1&period=range&date=2015-05-01,2015-05-10&format=json
     *
     * @param int $idSite Id Site
     * @param bool|string $period Period to restrict to when looking at the logs
     * @param bool|string $date Date to restrict to
     * @return array
     * @throws Exception
     */
    public function getPlatformData($idSite, $period = false, $date = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        // disabled for multiple dates
        if (Period::isMultiplePeriod($date, $period)) {
            throw new Exception('AOM.getVisits does not support multiple dates.');
        }

        /** @var Range $period */
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        $startTime = $period->getDateStart()->toString('Y-m-d 00:00:00');
        $endTime = $period->getDateEnd()->toString('Y-m-d 23:59:59');

        $result = [];

        foreach (AOM::getPlatforms() as $platformName) {

            $platform = AOM::getPlatformInstance($platformName);
            $data = Db::fetchRow(
                'SELECT ROUND(sum(cost), 2) as cost, sum(clicks) as clicks, sum(impressions) as impressions FROM ' . $platform->getDataTableName() . ' AS platform
                  WHERE
                  (? OR date >= ?) AND
                  (? OR date >= ?) AND
                  idsite = ?',
                [
                    $startTime == null, $startTime,
                    $endTime == null, $endTime,
                    $idSite
                ]
            );
            $data['platform'] = $platformName;
            $result[] = $data;
        }
        return $result;
    }
}
