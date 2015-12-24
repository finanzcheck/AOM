<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Piwik\Common;
use Piwik\Db;
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
                FROM ' . Common::prefixTable('aom_adwords') . '
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
                FROM ' . Common::prefixTable('aom_adwords') . '
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

        $visit['adData']['enriched'] = array_merge(['source' => 'AdWords'], $results);

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
            'platform' => 'AdWords',
        ];

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adData['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        }

        if (array_key_exists($paramPrefix . '_ad_group_id', $queryParams)) {
            $adData['adGroupId'] = $queryParams[$paramPrefix . '_ad_group_id'];
        }

        if (array_key_exists($paramPrefix . '_target_id', $queryParams)) {
            $adData['targetId'] = $queryParams[$paramPrefix . '_target_id'];
        }

        if (array_key_exists($paramPrefix . '_creative', $queryParams)) {
            $adData['creative'] = $queryParams[$paramPrefix . '_creative'];
        }

        if (array_key_exists($paramPrefix . '_placement', $queryParams)) {
            $adData['placement'] = $queryParams[$paramPrefix . '_placement'];
        }

        if (array_key_exists($paramPrefix . '_network', $queryParams)) {
            $adData['network'] = $queryParams[$paramPrefix . '_network'];
        }

        if (array_key_exists($paramPrefix . '_device', $queryParams)) {
            $adData['device'] = $queryParams[$paramPrefix . '_device'];
        }

        if (array_key_exists($paramPrefix . '_ad_position', $queryParams)) {
            $adData['adPosition'] = $queryParams[$paramPrefix . '_ad_position'];
        }

        if (array_key_exists($paramPrefix . '_loc_physical', $queryParams)) {
            $adData['locPhysical'] = $queryParams[$paramPrefix . '_loc_physical'];
        }

        if (array_key_exists($paramPrefix . '_loc_Interest', $queryParams)) {
            $adData['locInterest'] = $queryParams[$paramPrefix . '_loc_Interest'];
        }

        // TODO: Shorten placement when entire data as JSON has more than 1,024 chars.

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
        // TODO: Implement me correctly...

        // TODO: Add "date" to adKey?!
        $adKey = [
            'platform' => 'AdWords',
            'campaignId' => $adData['campaignId'],
            'adGroupId' => $adData['adGroupId'],
            'device' => $adData['device'],
        ];

        // Regular keyword based ad in google search or search network:
        // {"platform":"AdWords","campaignId":"184418636","adGroupId":"9794351276","targetId":"kwd-118607649","creative":"47609133356","placement":"","network":"g","device":"m","adposition":"1t2","locPhysical":"20228","locInterest":"1004074"}
        if (($this->networks[self::NETWORK_DISPLAY_NETWORK] != $adData['network'])
            && (array_key_exists('targetId', $adData) && (0 === strpos($adData['targetId'], 'kwd')))
            && (false === strpos($adData['targetId'], 'aud'))
        ) {
            $adKey['criteriaType'] = self::CRITERIA_TYPE_KEYWORD;
            $adKey['keywordId'] = substr($adData['targetId'], strpos($adData['targetId'], '-' ) + 1);
            // TODO: Network cannot be display network (how to pass such a condition?)
            return $adKey;
        }

        // Non-keyword-based (= ad group based?) placement ads:
        // {"platform":"AdWords","campaignId":"171096476","adGroupId":"8837340236","targetId":"","creative":"47609140796","placement":"suchen.mobile.de/auto-inserat","network":"d","device":"c","adposition":"none","locPhysical":"9041542","locInterest":""}
        if (($this->networks[self::NETWORK_DISPLAY_NETWORK] === $adData['network'])
            && (array_key_exists('targetId', $adData) && ('' === $adData['targetId']))
            && (array_key_exists('placement', $adData) && (strlen($adData['placement']) > 0))
        ) {
            $adKey['network'] = $this->networks[self::NETWORK_DISPLAY_NETWORK];
            $adKey['placement'] = $adData['placement']; // TODO: Ensure that the serialized adKey is not longer than 255 chars!
            return $adKey;
        }

        // Remarketing based ads:
        // {"platform":"AdWords","campaignId":"147730196","adGroupId":"7300245836","targetId":"aud-55070239676","creative":"47609140676","placement":"carfansofamerica.com","network":"d","device":"c","adposition":"none","locPhysical":"9042649","locInterest":""}
        // {"platform":"AdWords","campaignId":"147730196","adGroupId":"7300245836","targetId":"aud-55070239676","creative":"47609140676","placement":"www.hltv.org","network":"d","device":"t","adposition":"none","locPhysical":"9042582","locInterest":""}
        if (($this->networks[self::NETWORK_DISPLAY_NETWORK] === $adData['network'])
            && array_key_exists('targetId', $adData) && (false !== strpos($adData['targetId'], 'aud'))
            && (false === strpos($adData['targetId'], 'kwd'))
            && (array_key_exists('placement', $adData) && (strlen($adData['placement']) > 0))
        ) {
            $adKey['network'] = $this->networks[self::NETWORK_DISPLAY_NETWORK];
            $adKey['placement'] = $adData['placement']; // TODO: Ensure that the serialized adKey is not longer than 255 chars!
            // TODO: Where to store and map targetId?!
            return $adKey;
        }


        // TODO: Handle cases like these:
        // adwords|185040716|9820341356|aud-44922712076:kwd-1534289814|46917823556||g|m|1t2|9041646|1004269

        // TODO: Google Mail ads:
        // {"platform":"AdWords","campaignId":"","adGroupId":"","targetId":"","creative":"47609218436","placement":"mail.google.com","network":"d","device":"m","adposition":"none","locPhysical":"","locInterest":""}


        return 'not implemented';
    }
}
