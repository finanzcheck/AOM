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

    public function isActive() {
        return $this->settings->adWordsIsActive->getValue();
    }

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
     * Gets an OAuth2 credential.
     * @param string $user the user that contains the client ID and secret
     * @return array the user's OAuth 2 credentials
     */
    private function GetOAuth2Credential($user) {
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
