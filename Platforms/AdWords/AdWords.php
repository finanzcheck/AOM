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

    /**
     * @see https://developers.google.com/adwords/api/docs/appendix/reports/all-reports#adnetworktype2
     */
    const NETWORK_CONTENT = 'Display Network';
    const NETWORK_SEARCH = 'Google search';
    const NETWORK_SEARCH_PARTNERS = 'Search partners';
    const NETWORK_YOUTUBE_SEARCH = 'YouTube Search';
    const NETWORK_YOUTUBE_WATCH = 'YouTube Videos';
    const NETWORK_UNKNOWN = 'unknown';

    /**
     * @var array All supported networks
     */
    public static $networks = [
        self::NETWORK_CONTENT => 'd',
        self::NETWORK_SEARCH => 'g',
        self::NETWORK_SEARCH_PARTNERS => 's',
        self::NETWORK_YOUTUBE_SEARCH => null,
        self::NETWORK_YOUTUBE_WATCH => null,
        self::NETWORK_UNKNOWN => null,
    ];

    /**
     * Returns the platform's data table name.
     */
    public static function getDataTableNameStatic()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_AD_WORDS));
    }

    /**
     * Extracts advertisement platform specific data from the query params and validates it.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdParamsFromQueryParams($paramPrefix, array $queryParams)
    {
        // Validate required params
        $missingParams = array_diff(
            [
                $paramPrefix . '_campaign_id',
                $paramPrefix . '_ad_group_id',
                $paramPrefix . '_feed_item_id',
                $paramPrefix . '_target_id',
                $paramPrefix . '_creative',
                $paramPrefix . '_placement',
                $paramPrefix . '_target',
                $paramPrefix . '_network',
            ],
            array_keys($queryParams)
        );
        if (count($missingParams)) {
            $this->getLogger()->warning(
                'Visit with platform ' . AOM::PLATFORM_AD_WORDS . ' without required param/s: '
                . implode(', ', $missingParams)
            );

            return null;
        }

        $adParams = [
            'platform' => AOM::PLATFORM_AD_WORDS,
            'campaignId' => $queryParams[$paramPrefix . '_campaign_id'],
            'adGroupId' => $queryParams[$paramPrefix . '_ad_group_id'],
            'feedItemId' => $queryParams[$paramPrefix . '_feed_item_id'],
            'targetId' => $queryParams[$paramPrefix . '_target_id'],
            'creative' => $queryParams[$paramPrefix . '_creative'],
            'placement' => $queryParams[$paramPrefix . '_placement'],
            'target' => $queryParams[$paramPrefix . '_target'],
            'network' => $queryParams[$paramPrefix . '_network'],
        ];

        // Add optional params
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
        $data = $this->getAdData($idsite, date('Y-m-d'), $adParams);
        if(!$data[0]) {
            $data = [null, $this::getHistoricalAdData($idsite, $adParams['campaignId'], $adParams['adGroupId'])];
        }
        return $data;
    }

    /**
     * Searches for matching ad data
     * @param $idsite
     * @param $date
     * @param $adParams
     * @return array|null
     * @throws \Exception
     */
    public static function getAdData($idsite, $date, $adParams)
    {
        if ($adParams['network'] == 'd') {
            $result = DB::fetchAll(
                'SELECT * FROM ' . AdWords::getDataTableNameStatic() . ' WHERE idsite = ? AND date = ? and network = ? AND
                campaign_id = ? AND ad_group_id = ? AND keyword_placement = ?',
                [
                    $idsite,
                    $date,
                    'd',
                    $adParams['campaignId'],
                    $adParams['adGroupId'],
                    $adParams['placement']
                ]
            );
        } else {
            $targetId = $adParams['targetId'];
            if (strpos($adParams['targetId'], 'kwd-') !== false) {
                $targetId = substr($adParams['targetId'], strpos($adParams['targetId'], 'kwd-') + 4);
            }

            $result = DB::fetchAll(
                'SELECT * FROM ' . AdWords::getDataTableNameStatic() . ' WHERE idsite = ? AND date = ? AND
                campaign_id = ? AND ad_group_id = ? AND keyword_id = ?',
                [
                    $idsite,
                    $date,
                    $adParams['campaignId'],
                    $adParams['adGroupId'],
                    $targetId
                ]
            );
        }

        if (count($result) > 1) {
            throw new \Exception('Found more than one match for exact match.');
        } elseif(count($result) == 0) {
            return null;
        }

        return [$result[0]['id'], $result[0]];
    }


    /**
     * Searches for historical AdData
     *
     * @param $idsite
     * @param $campaignId
     * @param $adGroupId
     * @return array|null
     * @throws \Exception
     */
    public static function getHistoricalAdData($idsite, $campaignId, $adGroupId)
    {
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
