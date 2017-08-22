<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\IndividualCampaigns;

use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;

class Controller extends \Piwik\Plugins\AOM\Platforms\Controller
{
    /**
     * @return string
     */
    public function index()
    {
        Piwik::checkUserHasSomeAdminAccess();

        // Get current individual campaigns
        $campaigns = [];
        foreach (Db::fetchAll(
            'SELECT DISTINCT campaign_id AS campaignId FROM '
                . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                . ' WHERE obsolete = 0'
                . ' ORDER BY date ASC'
        ) as $row) {
            $campaigns[$row['campaignId']] = Db::fetchRow(
                'SELECT idsite AS websiteId, MIN(date) AS startDate, MAX(date) AS endDate, campaign,  
                        params_substring AS params, referrer_substring AS referrer, ROUND(SUM(cost)) AS cost
                 FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS) . ' 
                 WHERE campaign_id = ?
                 GROUP BY campaign_id',
                [$row['campaignId'],]
            );
        }

        $viewVariables = [
            'campaigns' => $campaigns,
        ];

        return $this->renderTemplate('../Platforms/IndividualCampaigns/templates/index.twig', $viewVariables);
    }

    /**
     * @param int $websiteId
     * @param string $startDate
     * @param string $endDate
     * @param string $campaign
     * @param string $params
     * @param string $referrer
     * @param float $cost
     * @return bool
     * @throws \Exception
     */
    public function addCampaign($websiteId, $startDate, $endDate, $campaign, $params, $referrer, $cost)
    {
        Piwik::checkUserHasAdminAccess($idSites = [$websiteId]);

        // TODO: Validate input data
        if (!$params && !$referrer) {
            throw new \Exception('Invalid input data.');
        }

        // We create our own campaign ID (e.g. to be used in the platform_key)
        $campaignId = uniqid('', true);

        $dates = AOM::getPeriodAsArrayOfDates($startDate, $endDate);
        foreach ($dates as $date) {
            Db::query(
                'INSERT INTO ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                    . ' (idsite, date, campaign_id, campaign, params_substring, referrer_substring, cost, obsolete, '
                    . ' synced, created_by, ts_created)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $websiteId,
                    $date,
                    $campaignId,
                    $campaign,
                    $params,
                    $referrer,
                    $cost / count($dates),
                    0,
                    0,
                    Piwik::getCurrentUserLogin(),
                ]
            );
        }

        return true;
    }

    /**
     * Deletes the specified campaign.
     *
     * TODO: What about visits that have already been assigned to this campaign and cost that has been distributed?
     *
     * @param string $campaignId
     * @return bool
     */
    public function deleteCampaign($campaignId)
    {
        Db::query(
            'UPDATE ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS) . '
                SET obsolete = 1, synced = 0 
                WHERE campaign_id = ?',
            [$campaignId,]
        );

        return true;
    }
}
