<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use AdWordsUser;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use ReportUtils;

class AdWords extends Platform implements PlatformInterface
{
    const CRITERIA_TYPE_AGE = 'age';
    const CRITERIA_TYPE_GENDER = 'gender';
    const CRITERIA_TYPE_KEYWORD = 'keyword';
    const CRITERIA_TYPE_PLACEMENT = 'placement';
    const CRITERIA_TYPE_USER_LIST = 'user list';

    /**
     * @var array All supported criteria types
     */
    public static $criteriaTypes = [
        self::CRITERIA_TYPE_AGE,
        self::CRITERIA_TYPE_GENDER,
        self::CRITERIA_TYPE_KEYWORD,
        self::CRITERIA_TYPE_PLACEMENT,
        self::CRITERIA_TYPE_USER_LIST,
    ];

    const NETWORK_DISPLAY_NETWORK = 'Display Network';
    const NETWORK_GOOGLE_SEARCH = 'Google Search';
    const NETWORK_SEARCH_NETWORK = 'Search Network';

    /**
     * @var array All supported networks
     */
    public static $networks = [
        self::NETWORK_DISPLAY_NETWORK => 'd',
        self::NETWORK_GOOGLE_SEARCH => 'g',
        self::NETWORK_SEARCH_NETWORK => 's',
    ];

    const DEVICE_COMPUTERS = 'Computers';
    const DEVICE_MOBILE_DEVICES_WITH_FULL_BROWSERS = 'Mobile devices with full browsers';
    const DEVICE_TABLETS_WITH_FULL_BROWSERS = 'Tablets with full browsers';
    const DEVICE_OTHER = 'Other';

    /**
     * @var array All supported devices
     */
    public static $devices = [
        self::DEVICE_COMPUTERS => 'c',
        self::DEVICE_MOBILE_DEVICES_WITH_FULL_BROWSERS => 'm',
        self::DEVICE_TABLETS_WITH_FULL_BROWSERS => 't',
        self::DEVICE_OTHER => 'o',  // TODO: "other" exists, but is "o" correct?!
    ];

    /**
     * Returns the platform's data table name.
     */
    public static function getDataTableNameStatic()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_AD_WORDS));
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
            'platform' => AOM::PLATFORM_AD_WORDS,
        ];

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adParams['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        }

        if (array_key_exists($paramPrefix . '_ad_group_id', $queryParams)) {
            $adParams['adGroupId'] = $queryParams[$paramPrefix . '_ad_group_id'];
        }

        if (array_key_exists($paramPrefix . '_feed_item_id', $queryParams)) {
            $adParams['feedItemId'] = $queryParams[$paramPrefix . '_feed_item_id'];
        }

        if (array_key_exists($paramPrefix . '_target_id', $queryParams)) {
            $adParams['targetId'] = $queryParams[$paramPrefix . '_target_id'];
        }

        if (array_key_exists($paramPrefix . '_creative', $queryParams)) {
            $adParams['creative'] = $queryParams[$paramPrefix . '_creative'];
        }

        if (array_key_exists($paramPrefix . '_placement', $queryParams)) {
            $adParams['placement'] = $queryParams[$paramPrefix . '_placement'];
        }

        if (array_key_exists($paramPrefix . '_target', $queryParams)) {
            $adParams['target'] = $queryParams[$paramPrefix . '_target'];
        }

        if (array_key_exists($paramPrefix . '_network', $queryParams)) {
            $adParams['network'] = $queryParams[$paramPrefix . '_network'];
        }

        if (array_key_exists($paramPrefix . '_device', $queryParams)) {
            $adParams['device'] = $queryParams[$paramPrefix . '_device'];
        }

        if (array_key_exists($paramPrefix . '_ad_position', $queryParams)) {
            $adParams['adPosition'] = $queryParams[$paramPrefix . '_ad_position'];
        }

        if (array_key_exists($paramPrefix . '_loc_physical', $queryParams)) {
            $adParams['locPhysical'] = $queryParams[$paramPrefix . '_loc_physical'];
        }

        if (array_key_exists($paramPrefix . '_loc_Interest', $queryParams)) {
            $adParams['locInterest'] = $queryParams[$paramPrefix . '_loc_Interest'];
        }

        // TODO: Shorten placement when entire data as JSON has more than 1,024 chars.

        return $adParams;
    }

    /**
     * Returns an AdWords user for a specific AdWords Account.
     *
     * @param array $account
     * @return AdWordsUser
     */
    public static function getAdWordsUser($account)
    {
        $oauth2Info = [
            'client_id' => $account['clientId'],
            'client_secret' => $account['clientSecret'],
        ];

        if (null != $account['refreshToken']) {
            $oauth2Info['refresh_token'] = $account['refreshToken'];
        }

        return new AdWordsUser(
            null,
            $account['developerToken'],
            $account['userAgent'],
            $account['clientCustomerId'],
            null,
            $oauth2Info
        );
    }

    /**
     * @inheritdoc
     */
    public function getAdDataFromAdParams($idsite, array $adParams)
    {
        //Not implemented yet
        return null;
    }

    public static function getAdData($idsite, $date, $campaignId, $adGroupId)
    {
        // Exact match
        //TODO?

        // No exact match found; search for historic data
        $result = DB::fetchAll(
            'SELECT * FROM ' . AdWords::getDataTableNameStatic() . ' WHERE idsite = ? AND campaign_id = ? AND ad_group_id = ?',
            [
                $idsite,
                $campaignId,
                $adGroupId
            ]
        );

        if (count($result) > 0) {

            // Keep generic date-independent information only
            return [
                'campaign_id' => $campaignId,
                'campaign' => $result[0]['campaign'],
                'ad_group_id' => $adGroupId,
                'ad_group' => $result[0]['ad_group'],
            ];
        }

        return null;
    }

}
