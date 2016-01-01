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
    public static function getDataTableName()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_AD_WORDS));
    }

    /**
     * Enriches a specific visit with additional AdWords information when this visit came from AdWords.
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


        // Content/display network non-keyword-based (e.g. placements), e.g.:
        // adwords|171096476|8837340236|||46709955956|d|t|none|9042066|
        // adwords|171096476|8837340236|||46709955956|d|c|none|9042228|
        if (($this->networks[self::NETWORK_DISPLAY_NETWORK] === $ad['network'])
            && ('' === $ad['targetId'])
        ) {
            $where = 'network = ?';
            $arguments = [
                $ad['network'],
            ];

        // Keyword-based content/display network, e.g.:
        // adwords|165772076|9118382276||kwd-53736810072|46709947556|d|c|none|9043365|
        // TODO: This is currently not working (above ist "criteria_type" = age but keyword_id 53736810072 does not exist!)
        } else if ((false !== strrpos($ad['targetId'], 'kwd'))
            && ($this->networks[self::NETWORK_DISPLAY_NETWORK] === $ad['network'])
        ) {
            $where = 'keyword_id = ? AND network = ?';
            $arguments = [
                substr($ad['targetId'], strrpos($ad['targetId'], '-' ) + 1),
                $this->networks[self::NETWORK_DISPLAY_NETWORK],
            ];

        // Regular keyword in "Google Search" or "Search Network", e.g.:
        // adwords|184422836|9794377676||kwd-399658803|46709975396|g|m|1t3|1004363|1004412
        // adwords|165772076|9477119516||kwd-146001483|46709949356|d|t|none|9042671|
        } else if ((false !== strrpos($ad['targetId'], 'kwd'))
            && ($this->networks[self::NETWORK_DISPLAY_NETWORK] != $ad['network'])
        ) {
            $where = 'keyword_id = ? AND criteria_type = ? AND network != ?';
            $arguments = [
                substr($ad['targetId'], strrpos($ad['targetId'], '-' ) + 1),
                self::CRITERIA_TYPE_KEYWORD,
                $this->networks[self::NETWORK_DISPLAY_NETWORK],
            ];

        } else {
            return $visit;
        }

        $sql = 'SELECT
                    campaign_id AS campaignId,
                    campaign,
                    ad_group_id AS adGroupId,
                    ad_group AS adGroup,
                    keyword_id AS keywordId,
                    keyword_placement AS keywordPlacement,
                    criteria_type AS criteriaType,
                    network,
                    device,
                    (cost / clicks) AS cpc
                FROM ' . self::getDataTableName() . '
                WHERE date = ? AND campaign_id = ? AND ad_group_id = ? AND device = ? AND ' . $where;

        $results = Db::fetchAll(
            $sql,
            array_merge(
                [
                    date('Y-m-d', strtotime($visit['firstActionTime'])),
                    $ad['campaignId'],
                    $ad['adGroupId'],
                    $ad['device'],
                ],
                $arguments
            )
        );

        // TODO: We must ensure that all query results return exactly one row! This must be checked!
        // This is a fallback only...
        if (count($results) !=  1) {
            $results = Db::fetchRow(
                'SELECT
                    campaign_id AS campaignId,
                    campaign,
                    ad_group_id AS adGroupId,
                    ad_group AS adGroup,
                    device
                FROM ' . self::getDataTableName() . '
                WHERE date = ? AND campaign_id = ? AND ad_group_id = ? AND device = ?',
                [
                    date('Y-m-d', strtotime($visit['firstActionTime'])),
                    $ad['campaignId'],
                    $ad['adGroupId'],
                    $ad['device'],
                ]
            );

        } else {
            $results = $results[0];
        }

        $visit['adParams']['enriched'] = array_merge(['source' => 'AdWords'], $results);

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
}
