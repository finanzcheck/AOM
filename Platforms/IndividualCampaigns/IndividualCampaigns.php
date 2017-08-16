<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\IndividualCampaigns;

use Piwik\Common;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractPlatform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Tracker\Request;

class IndividualCampaigns extends AbstractPlatform implements PlatformInterface
{
    /**
     * Returns true if the visit is coming from this platform. False otherwise.
     *
     * TODO: Check if/how $action->getActionUrl() and $request->getParams()['url'] are different.
     *
     * @param Request $request
     * @return bool
     */
    public function isVisitComingFromPlatform(Request $request)
    {
        // For individual campaigns checking if the visit is coming from an individual campaign is not as easy as
        // checking this for platforms like AdWords or Bing, where you have specific params. (gclid, _platform, ...).
        $adParams = $this->getAdParamsFromRequest($request);

        return (is_array($adParams) && count($adParams) > 0);
    }

    /**
     * Extracts and returns advertisement platform specific data from an URL.
     * $queryParams and $paramPrefix are only passed as params for convenience reasons.
     *
     * @param string $url
     * @param array $queryParams
     * @param string $paramPrefix
     * @param Request $request
     * @return array|null
     */
    protected function getAdParamsFromUrl($url, array $queryParams, $paramPrefix, Request $request)
    {
        // TODO: Support more than simple substring matching
        $matches = Db::fetchAll(
            'SELECT campaign_id AS campaignId, campaign '
                . ' FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                . ' WHERE idsite = ? AND date = ? AND obsolete = 0 AND '
                . ' ((params_substring <> \'\' AND ? LIKE CONCAT("%",params_substring,"%")) '
                . ' OR (referrer_substring <> \'\' AND ? LIKE CONCAT("%",referrer_substring,"%")))',
                [
                    $request->getIdSite(),
                    date('Y-m-d', $request->getCurrentTimestamp()),
                    $url,
                    $url,
                ]
        );

        if (count($matches) > 1) {
            $this->getLogger()->warning('URL ' . $url . ' matched multiple individual campaigns: ');
            foreach ($matches as $match) {
                $this->getLogger()->warning(
                    'ParamsSubstring: ' . $match['params_substring'] . ' / ReferrerSubstring: '
                        . $match['referrer_substring'] . ' (ID ' . $match['id'] . ')'
                );
            }
        } elseif (count($matches) === 1) {
            return [
                true,
                [
                    'platform' => AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS,
                    'campaignId' => $matches[0]['campaignId'],
                    'campaignName' => $matches[0]['campaign'],
                ]
            ];
        }

        return [false, []];
    }

    /**
     * Activates sub tables for the marketing performance report in the Piwik UI for individual campaigns.
     *
     * @return MarketingPerformanceSubTables
     */
    public function getMarketingPerformanceSubTables()
    {
        return new MarketingPerformanceSubTables();
    }

    /**
     * Platforms can add items to the admin menu. By default, not menu items are being added.
     *
     * @return array
     */
    public function getMenuAdminItems()
    {
        return [
            [
                'menuName' => 'AOM_Menu_IndividualCampaigns',
                'params' => [
                    'method' => 'index',
                ],
                'orderId' => 27,
            ]
        ];
    }

    /**
     * Returns a platform-specific description of a specific visit optimized for being read by humans or false when no
     * platform-specific description is available.
     *
     * @param int $idVisit
     * @return string|false
     */
    public static function getHumanReadableDescriptionForVisit($idVisit)
    {
        $visit = Db::fetchRow(
            'SELECT
                idsite,
                platform_data,
                cost
             FROM ' . Common::prefixTable('aom_visits') . '
             WHERE piwik_idvisit = ?',
            [
                $idVisit,
            ]
        );

        if ($visit && $visit['platform_data'] && $visit['cost']) {

            $formatter = new Formatter();

            $platformData = json_decode($visit['platform_data'], true);

            if (is_array($platformData) && array_key_exists('campaignName', $platformData))
            {
                return Piwik::translate(
                    'AOM_Platform_VisitDescription_IndividualCampaign',
                    [
                        $formatter->getPrettyMoney($visit['cost'], $visit['idsite']),
                        $platformData['campaignName'],
                    ]
                );
            } else {
                return Piwik::translate('AOM_Platform_VisitDescription_IndividualCampaign_Incomplete');
            }
        }

        return false;
    }
}
