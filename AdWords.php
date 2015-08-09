<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use AdWordsUser;
use Piwik\Common;
use Piwik\Db;
use ReportUtils;

class AdWords
{
    const AD_CAMPAIGN_ID = 1;
    const AD_AD_GROUP_ID = 2;
    const AD_FEED_ITEM_ID = 3;
    const AD_TARGET_ID = 4;
    const AD_CREATIVE_ID = 5;
    const AD_NETWORK = 6;
    const AD_DEVICE = 7;

    const CRITERIA_TYPE_AGE = 'age';
    const CRITERIA_TYPE_GENDER = 'gender';
    const CRITERIA_TYPE_KEYWORD = 'keyword';
    const CRITERIA_TYPE_PLACEMENT = 'placement';
    const CRITERIA_TYPE_USER_LIST = 'user list';

    /**
     * @var array All supported criteria types
     */
    private $criteriaTypes = [
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
    private $networks = [
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
    private $devices = [
        self::DEVICE_COMPUTERS => 'c',
        self::DEVICE_MOBILE_DEVICES_WITH_FULL_BROWSERS => 'm',
        self::DEVICE_TABLETS_WITH_FULL_BROWSERS => 't',
        self::DEVICE_OTHER => 'o',  // TODO: "other" exists, but is "o" correct?!
    ];

    /**
     * @var  Settings
     */
    private $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    /**
     * @return mixed
     */
    public function isActive() {
        return $this->settings->adWordsIsActive->getValue();
    }

    /**
     * @param $startDate
     * @param $endDate
     * @throws \Exception
     */
    public function import($startDate, $endDate)
    {
        if(!$this->isActive()) {
            return;
        }

        // Delete existing data for the specified period
        // TODO: this might be more complicated when we already merged / assigned data to visits!?!
        // TODO: There might be more than 100000 rows?!
        Db::deleteAllRows(
            Common::prefixTable('aom_adwords'),
            'WHERE date >= ? AND date <= ?',
            'date',
            100000,
            [$startDate, $endDate]
        );

        // Add AdWords SDK to include path
        set_include_path(get_include_path() . PATH_SEPARATOR
            . getcwd() . '/plugins/AOM/vendor/googleads/googleads-php-lib/src/');

        require_once('Google/Api/Ads/AdWords/Lib/AdWordsUser.php');
        require_once('Google/Api/Ads/AdWords/Util/ReportUtils.php');

        $user = new AdWordsUser(
            null,
            $this->settings->adWordsDeveloperToken->getValue(),
            $this->settings->adWordsUserAgent->getValue(),
            $this->settings->adWordsClientCustomerId->getValue(),
            null,
            [
                'client_id' => $this->settings->adWordsClientId->getValue(),
                'client_secret' => $this->settings->adWordsClientSecret->getValue(),
                'refresh_token' => $this->settings->adWordsRefreshToken->getValue(),
            ]
        );

        $user->LogAll();

        // Run this to get a refresh token
//            $oauth2Info = $this->GetOAuth2Credential($user);
//            printf("Your refresh token is: %s\n\n", $oauth2Info['refresh_token']);
//            printf("In your auth.ini file, edit the refresh_token line to be:\n"
//                . "refresh_token = \"%s\"\n", $oauth2Info['refresh_token']);

        // Download report (@see https://developers.google.com/adwords/api/docs/appendix/reports?hl=de#criteria)
        $xmlString = ReportUtils::DownloadReportWithAwql(
            'SELECT AccountDescriptiveName, AccountCurrencyCode, AccountTimeZoneId, CampaignId, CampaignName, '
            . 'AdGroupId, AdGroupName, Id, Criteria, CriteriaType, AdNetworkType1, AveragePosition, '
            . 'ConversionsManyPerClick, Device, QualityScore, CpcBid, Impressions, Clicks, Cost, Date '
            . 'FROM CRITERIA_PERFORMANCE_REPORT WHERE Impressions > 0 DURING '
            . str_replace('-', '', $startDate) . ','
            . str_replace('-', '', $endDate),
            null,
            $user,
            'XML',
            [
                'version' => 'v201502',
                'skipReportHeader' => true,
                'skipColumnHeader' => true,
                'skipReportSummary' => true,
            ]
        );
        $xml = simplexml_load_string($xmlString);

        // TODO: Use MySQL transaction to improve performance!
        foreach ($xml->table->row as $row) {

            // TODO: Validate currency and timezone?!
            // TODO: qualityScore, maxCPC, avgPosition?!
            // TODO: Find correct place to log warning, errors, etc. and monitor them!

            // Validation
            if (!in_array(strtolower((string) $row['criteriaType']), $this->criteriaTypes)) {
                var_dump('Criteria type "' . (string) $row['criteriaType'] . '" not supported.');
                continue;
            } else {
                $criteriaType = strtolower((string) $row['criteriaType']);
            }

            if (!in_array((string) $row['network'], array_keys($this->networks))) {
                var_dump('Network "' . (string) $row['network'] . '" not supported.');
                continue;
            } else {
                $network = $this->networks[(string) $row['network']];
            }

            if (!in_array((string) $row['device'], array_keys($this->devices))) {
                var_dump('Device "' . (string) $row['device'] . '" not supported.');
                continue;
            } else {
                $device = $this->devices[(string) $row['device']];
            }

            Db::query(
                'INSERT INTO ' . Common::prefixTable('aom_adwords') . ' (date, account, campaign_id, campaign, '
                . 'ad_group_id, ad_group, keyword_id, keyword_placement, criteria_type, network, device, impressions, '
                . 'clicks, cost, conversions) VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $row['day'],
                    $row['account'],
                    $row['campaignID'],
                    $row['campaign'],
                    $row['adGroupID'],
                    $row['adGroup'],
                    $row['keywordID'],
                    $row['keywordPlacement'],
                    $criteriaType,
                    $network,
                    $device,
                    $row['impressions'],
                    $row['clicks'],
                    ($row['cost'] / 1000000),
                    $row['conversions'],
                ]
            );
        }
    }

    /**
     * Enriches a specific visit with additional AdWords information when this visit came from AdWords.
     *
     * @param array &$visit
     * @param array $ad
     * @return array
     * @throws \Exception
     */
    public function enrichVisit(&$visit, array $ad)
    {
        // Content/display network non-keyword-based (e.g. placements), e.g.:
        // adwords|171096476|8837340236|||46709955956|d|t|none|9042066|
        // adwords|171096476|8837340236|||46709955956|d|c|none|9042228|
        if (($this->networks[self::NETWORK_DISPLAY_NETWORK] === $ad[self::AD_NETWORK])
            && ('' === $ad[self::AD_TARGET_ID])
        ) {
            $where = 'network = ?';
            $arguments = [
                $ad[self::AD_NETWORK],
            ];

        // Keyword-based content/display network, e.g.:
        // adwords|165772076|9118382276||kwd-53736810072|46709947556|d|c|none|9043365|
        // TODO: This is currently not working (above ist "criteria_type" = age but keyword_id 53736810072 does not exist!)
        } else if ((false !== strrpos($ad[self::AD_TARGET_ID], 'kwd'))
            && ($this->networks[self::NETWORK_DISPLAY_NETWORK] === $ad[self::AD_NETWORK])
        ) {
            $where = 'keyword_id = ? AND network = ?';
            $arguments = [
                substr($ad[self::AD_TARGET_ID], strrpos($ad[self::AD_TARGET_ID], '-' ) + 1),
                $this->networks[self::NETWORK_DISPLAY_NETWORK],
            ];

        // Regular keyword in "Google Search" or "Search Network", e.g.:
        // adwords|184422836|9794377676||kwd-399658803|46709975396|g|m|1t3|1004363|1004412
        // adwords|165772076|9477119516||kwd-146001483|46709949356|d|t|none|9042671|
        } else if ((false !== strrpos($ad[self::AD_TARGET_ID], 'kwd'))
            && ($this->networks[self::NETWORK_DISPLAY_NETWORK] != $ad[self::AD_NETWORK])
        ) {
            $where = 'keyword_id = ? AND criteria_type = ? AND network != ?';
            $arguments = [
                substr($ad[self::AD_TARGET_ID], strrpos($ad[self::AD_TARGET_ID], '-' ) + 1),
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
                    $ad[self::AD_CAMPAIGN_ID],
                    $ad[self::AD_AD_GROUP_ID],
                    $ad[self::AD_DEVICE],
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
                    $ad[self::AD_CAMPAIGN_ID],
                    $ad[self::AD_AD_GROUP_ID],
                    $ad[self::AD_DEVICE],
                ]
            );

        } else {
            $results = $results[0];
        }

        $visit['ad'] = array_merge(['source' => 'AdWords'], $results);

        return $visit;
    }

    /**
     * Gets an OAuth2 credential.
     * @param string $user the user that contains the client ID and secret
     * @return array the user's OAuth 2 credentials
     */
    private function GetOAuth2Credential($user)
    {
        $redirectUri = NULL;
        $offline = TRUE;
        // Get the authorization URL for the OAuth2 token.
        // No redirect URL is being used since this is an installed application. A web
        // application would pass in a redirect URL back to the application,
        // ensuring it's one that has been configured in the API console.
        // Passing true for the second parameter ($offline) will provide us a refresh
        // token which can used be refresh the access token when it expires.
        $OAuth2Handler = $user->GetOAuth2Handler();
        $authorizationUrl = $OAuth2Handler->GetAuthorizationUrl(
            $user->GetOAuth2Info(), $redirectUri, $offline);
        // In a web application you would redirect the user to the authorization URL
        // and after approving the token they would be redirected back to the
        // redirect URL, with the URL parameter "code" added. For desktop
        // or server applications, spawn a browser to the URL and then have the user
        // enter the authorization code that is displayed.
        printf("Log in to your AdWords account and open the following URL:\n%s\n\n",
            $authorizationUrl);
        print "After approving the token enter the authorization code here: ";
        $stdin = fopen('php://stdin', 'r');
        $code = trim(fgets($stdin));
        fclose($stdin);
        print "\n";
        // Get the access token using the authorization code. Ensure you use the same
        // redirect URL used when requesting authorization.
        $user->SetOAuth2Info(
            $OAuth2Handler->GetAccessToken(
                $user->GetOAuth2Info(), $code, $redirectUri));
        // The access token expires but the refresh token obtained for offline use
        // doesn't, and should be stored for later use.
        return $user->GetOAuth2Info();
    }
}
