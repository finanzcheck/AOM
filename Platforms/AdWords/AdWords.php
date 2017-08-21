<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Piwik\Common;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractPlatform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Tracker\Request;

class AdWords extends AbstractPlatform implements PlatformInterface
{
    const CRITERIA_TYPE_AGE = 'age';
    const CRITERIA_TYPE_GENDER = 'gender';
    const CRITERIA_TYPE_KEYWORD = 'keyword';
    const CRITERIA_TYPE_PLACEMENT = 'placement';
    const CRITERIA_TYPE_USER_INTEREST = 'user interest';
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
        self::CRITERIA_TYPE_USER_INTEREST,
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
     * @see https://developers.google.com/adwords/api/docs/appendix/reports/click-performance-report#device
     */
    const DEVICE_COMPUTERS = 'Computers';
    const DEVICE_MOBILE = 'Mobile devices with full browsers';
    const DEVICE_TABLETS = 'Tablets with full browsers';

    /**
     * @var array All supported devices
     */
    public static $devices = [
        self::DEVICE_COMPUTERS => 'c',
        self::DEVICE_MOBILE => 'm',
        self::DEVICE_TABLETS => 't',
    ];

    /**
     * Returns true if the visit is coming from this platform. False otherwise.
     *
     * TODO: There should only be one Piwik visit per gclid!
     * TODO: Set source of visits to "direct" if a previous visit with same gclid exists?!
     *
     * @param Request $request
     * @return bool
     */
    public function isVisitComingFromPlatform(Request $request)
    {
        // Check current URL first before referrer URL
        if (isset($request->getParams()['url'])) {
            $queryString = parse_url($request->getParams()['url'], PHP_URL_QUERY);
            parse_str($queryString, $queryParams);

            if (is_array($queryParams) && array_key_exists('gclid', $queryParams)) {
                return true;
            }
        }

        // Only consider the referrer when it is an internal URL
        if (isset($request->getParams()['urlref']) && $request->getIdSite()
            && $this->isReferrerAnInternalUrl($request->getParams()['urlref'], $request->getIdSite())
        ) {
            $queryString = parse_url($request->getParams()['urlref'], PHP_URL_QUERY);
            parse_str($queryString, $queryParams);

            if (is_array($queryParams) && array_key_exists('gclid', $queryParams)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts and returns advertisement platform specific data from an URL.
     * $queryParams and $paramPrefix are only passed as params for convenience reasons.
     *
     * Since AOM 1.0.0 AdWords only works with gclid.
     *
     * @param string $url
     * @param array $queryParams
     * @param string $paramPrefix
     * @param Request $request
     * @return array|null
     */
    protected function getAdParamsFromUrl($url, array $queryParams, $paramPrefix, Request $request)
    {
        // No validation possible, as there either is a gclid or not (the _platform param won't be set!)
        if (array_key_exists('gclid', $queryParams)) {
            return [
                true,
                [
                    'platform' => AOM::PLATFORM_AD_WORDS,
                    'gclid' => $queryParams['gclid'],
                ]
            ];
        }

        return [false, []];
    }

    /**
     * Activates sub tables for the marketing performance report in the Piwik UI for AdWords.
     *
     * @return MarketingPerformanceSubTables
     */
    public function getMarketingPerformanceSubTables()
    {
        return new MarketingPerformanceSubTables();
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

        if ($visit) {

            $formatter = new Formatter();

            $platformData = json_decode($visit['platform_data'], true);

            if (is_array($platformData)
                && array_key_exists('account', $platformData) && array_key_exists('campaign', $platformData)
                && array_key_exists('adGroup', $platformData) && array_key_exists('keywordPlacement', $platformData))
            {
                return Piwik::translate(
                    'AOM_Platform_VisitDescription_AdWords',
                    [
                        $formatter->getPrettyMoney($visit['cost'], $visit['idsite']),
                        $platformData['account'],
                        $platformData['campaign'],
                        $platformData['adGroup'],
                        $platformData['keywordPlacement'],
                    ]
                );
            } else {
                return Piwik::translate('AOM_Platform_VisitDescription_AdWords_Incomplete');
            }
        }

        return false;
    }
}
