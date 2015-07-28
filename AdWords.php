<?php
/**
 * Class AdWords
 * @package Piwik\Plugins\AOM\
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 *
 */

namespace Piwik\Plugins\AOM;

use AdWordsUser;
use Piwik\Common;
use Piwik\Db;
use ReportUtils;

class AdWords {
    /** @var  Settings */
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

            // TODO: Validate currency and Timezone?!
            // TODO: qualityScore, maxCPC, avgPosition?!

            $sql = 'INSERT INTO ' . Common::prefixTable('aom_adwords') . ' (
                        date, account, campaign_id, campaign, ad_group_id, ad_group, keyword_id, keyword_placement, criteria_type,
                        network, device, impressions, clicks, cost, conversions) VALUE ("' . $row['day'] . '",
                        "' . $row['account'] . '", "' . $row['campaignID'] . '", "' . $row['campaign'] . '", "' . $row['adGroupID'] . '",
                        "' . $row['adGroup'] . '", "' . $row['keywordID'] . '", "' . $row['keywordPlacement'] . '", "' . $row['criteriaType'] . '",
                        "' . $row['network'] . '", "' . $row['device'] . '", "' . $row['impressions'] . '", "' . $row['clicks'] . '"
                        , "' . ($row['cost'] / 1000000) . '", "' . $row['conversions'] . '")';
            Db::exec($sql);
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