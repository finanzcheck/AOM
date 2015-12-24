<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Exception;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class FacebookAds extends Platform implements PlatformInterface
{
    /**
     * Enriches a specific visit with additional Facebook information when this visit came from Facebook.
     *
     * @param array &$visit
     * @param array $adData
     * @return array
     * @throws \Exception
     */
    public function enrichVisit(array &$visit, array $adData)
    {
        // TODO: This method must be refactored!
        $results = [];

        $visit['adData'] = array_merge($adData, ($results ? $results : []));

        return $visit;
    }

    /**
     * Extracts advertisement platform specific data from the query params.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdDataFromQueryParams($paramPrefix, array $queryParams)
    {
        $adData = [
            'platform' => 'FacebookAds',
        ];

        if (array_key_exists($paramPrefix . '_campaign_group_id', $queryParams)) {
            $adData['campaignGroupId'] = $queryParams[$paramPrefix . '_campaign_group_id'];
        }

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adData['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        }

        if (array_key_exists($paramPrefix . '_ad_group_id', $queryParams)) {
            $adData['adGroupId'] = $queryParams[$paramPrefix . '_ad_group_id'];
        }

        return $adData;
    }

    /**
     * Builds a string key from the ad data to reference explicit platform data.
     * This key is only built when all required ad data is available. It is being stored in piwik_log_visit.aom_ad_key.
     *
     * @param array $adData
     * @return mixed
     */
    public function getAdKeyFromAdData(array $adData)
    {
        // TODO: Implement me!

        return 'not implemented';
    }
}
