<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class FacebookAds extends Platform implements PlatformInterface
{
    /**
     * Returns the platform's data table name.
     */
    public static function getDataTableNameStatic()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_FACEBOOK_ADS));
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
            'platform' => AOM::PLATFORM_FACEBOOK_ADS,
        ];

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adParams['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        }

        if (array_key_exists($paramPrefix . '_adset_id', $queryParams)) {
            $adParams['adsetId'] = $queryParams[$paramPrefix . '_adset_id'];
        }

        if (array_key_exists($paramPrefix . '_ad_id', $queryParams)) {
            $adParams['adId'] = $queryParams[$paramPrefix . '_ad_id'];
        }

        return $adParams;
    }

    /**
     * @inheritdoc
     */
    public function getAdDataFromAdParams($idsite, array $adParams)
    {
        //Not implemented yet
        return null;
    }
}
