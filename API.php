<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM;

use Exception;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\AOM\API\MarketingPerformanceController;
use Piwik\Plugins\AOM\API\StatusController;
use Piwik\Plugins\AOM\API\VisitsController;

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

        return VisitsController::getVisits($idSite, $period, $date);
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

        return VisitsController::getEcommerceOrdersWithVisits($idSite, $orderId);
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

        return VisitsController::getEcommerceOrdersWithVisits($idSite, $orderId, $period, $date);
    }

    /**
     * Returns various status information that can be used for monitoring:
     * ?module=API&token_auth=...&method=AOM.getStatus&idSite=1&format=json
     *
     * @param int $idSite
     * @return array
     * @throws Exception
     */
    public function getStatus($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return StatusController::getStatus($idSite);
    }

    /**
     * Returns various information about AOM visits that can be used for monitoring:
     * ?module=API&token_auth=...&method=AOM.getAomVisitsStatus&idSite=1&format=json
     *
     * @param $idSite
     * @param bool $groupByChannel
     * @return array
     * @throws Exception
     */
    public function getAomVisitsStatus($idSite, $groupByChannel = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return StatusController::getAomVisitsStatus($idSite, $groupByChannel);
    }

    /**
     * Returns costs information for each platform for the given params, e.g.:
     * ?module=API&token_auth=...&method=AOM.getMarketingPerformance&idSite=1&period=day&date=2015-05-01&format=json
     * ?module=API&token_auth=...&method=AOM.getMarketingPerformance&idSite=1&period=range&date=2015-05-01,2015-05-10&format=json
     *
     * This data is used by the marketing performance report in the Piwik front-end!
     *
     * TODO: What about segments?! aom_visits contains non-Piwik-visits which cannot be segmented!
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param bool|string $segment
     * @return DataTable
     * @throws Exception
     */
    public function getMarketingPerformance($idSite, $period, $date, $segment = false, $idSubtable = null)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return MarketingPerformanceController::getMarketingPerformance($idSite, $period, $date, $idSubtable);
    }
}
