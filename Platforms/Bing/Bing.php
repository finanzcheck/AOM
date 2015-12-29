<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class Bing extends Platform implements PlatformInterface
{
    const AD_CAMPAIGN_ID = 1;
    const AD_AD_GROUP_ID = 2;
    const AD_KEYWORD_ID = 3;

    /**
     * Returns the platform's data table name.
     */
    public static function getDataTableName()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_BING));
    }

    /**
     * Enriches a specific visit with additional Bing information when this visit came from Bing.
     *
     * @param array &$visit
     * @param array $ad
     * @return array
     * @throws \Exception
     */
    public function enrichVisit(array &$visit, array $ad)
    {
        // TODO: This method must be refactored!
        return $visit;

        $sql = 'SELECT
                    campaign_id AS campaignId,
                    campaign,
                    ad_group_id AS adGroupId,
                    ad_group AS adGroup,
                    keyword_id AS keywordId,
                    keyword,
                    clicks,
                    cost,
                    impressions,
                    conversions,
                    (cost / clicks) AS cpc
                FROM ' . self::getDataTableName() . '
                WHERE date = ? AND campaign_id = ? AND ad_group_id = ? AND keyword_id = ?';

        $results = Db::fetchRow(
            $sql,
            [
                date('Y-m-d', strtotime($visit['firstActionTime'])),
                $ad[self::AD_CAMPAIGN_ID],
                $ad[self::AD_AD_GROUP_ID],
                $ad[self::AD_KEYWORD_ID],
            ]
        );

        $visit['adParams']['enriched'] = array_merge(['source' => 'Bing'], $results);

        return $visit;
    }

    /**
     * Extracts advertisement platform specific data from the query params.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdParamsFromQueryParams($paramPrefix, array $queryParams)
    {
        $adParams = [
            'platform' => 'Bing',
        ];

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adParams['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        }

        if (array_key_exists($paramPrefix . '_ad_group_id', $queryParams)) {
            $adParams['adGroupId'] = $queryParams[$paramPrefix . '_ad_group_id'];
        }

        if (array_key_exists($paramPrefix . '_order_item_id', $queryParams)) {
            $adParams['orderItemId'] = $queryParams[$paramPrefix . '_order_item_id'];
        }

        if (array_key_exists($paramPrefix . '_target_id', $queryParams)) {
            $adParams['targetId'] = $queryParams[$paramPrefix . '_target_id'];
        }

        if (array_key_exists($paramPrefix . '_ad_id', $queryParams)) {
            $adParams['adId'] = $queryParams[$paramPrefix . '_ad_id'];
        }

        return $adParams;
    }

    /**
     * Builds a string key from the ad data to reference explicit platform data.
     * This key is only built when all required ad data is available. It is being stored in piwik_log_visit.aom_ad_key.
     *
     * @see http://help.bingads.microsoft.com/apex/index/3/en-us/51091
     *
     * @param array $adParams
     * @return mixed
     */
    public function getAdKeyFromAdParams(array $adParams)
    {
        // TODO: When to use {TargetId} and when to use {OrderItemId}?!

        // Regular keyword ("kwd-" in {TargetId})
        // TODO: Not sure about this implementation...
        if (array_key_exists('campaignId', $adParams)
            && array_key_exists('adGroupId', $adParams)
            && array_key_exists('orderItemId', $adParams)
            && (substr($adParams['orderItemId'], 0, strlen($adParams['orderItemId'])) === 'kwd-')
        ) {
            return $adParams['campaignId'] . '|' . $adParams['adGroupId'] . '|' . $adParams['orderItemId'];
        }

        // Remarketing list ("aud-" in {TargetId})

        // TODO: Implement me!

        return 'not implemented';
    }
}
