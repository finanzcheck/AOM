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
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use ReportUtils;

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
    /**
     * @param $startDate
     * @param $endDate
     * @return mixed|void
     * @throws \Exception
     */
    public function import($startDate, $endDate)
    {
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
            $this->platform->getSettings()->adWordsDeveloperToken->getValue(),
            $this->platform->getSettings()->adWordsUserAgent->getValue(),
            $this->platform->getSettings()->adWordsClientCustomerId->getValue(),
            null,
            [
                'client_id' => $this->platform->getSettings()->adWordsClientId->getValue(),
                'client_secret' => $this->platform->getSettings()->adWordsClientSecret->getValue(),
                'refresh_token' => $this->platform->getSettings()->adWordsRefreshToken->getValue(),
            ]
        );

        $user->LogAll();

        // Download report (@see https://developers.google.com/adwords/api/docs/appendix/reports?hl=de#criteria)
        // https://developers.google.com/adwords/api/docs/appendix/reports/criteria-performance-report?hl=de
        $xmlString = ReportUtils::DownloadReportWithAwql(
            'SELECT AccountDescriptiveName, AccountCurrencyCode, AccountTimeZoneId, CampaignId, CampaignName, '
            . 'AdGroupId, AdGroupName, Id, Criteria, CriteriaType, AdNetworkType1, AveragePosition, Conversions, '
            . 'Device, QualityScore, CpcBid, Impressions, Clicks, Cost, Date '
            . 'FROM CRITERIA_PERFORMANCE_REPORT WHERE Impressions > 0 DURING '
            . str_replace('-', '', $startDate) . ','
            . str_replace('-', '', $endDate),
            null,
            $user,
            'XML',
            [
                'version' => 'v201509',
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
            if (!in_array(strtolower((string) $row['criteriaType']), AdWords::$criteriaTypes)) {
                var_dump('Criteria type "' . (string) $row['criteriaType'] . '" not supported.');
                continue;
            } else {
                $criteriaType = strtolower((string) $row['criteriaType']);
            }

            if (!in_array((string) $row['network'], array_keys(AdWords::$networks))) {
                var_dump('Network "' . (string) $row['network'] . '" not supported.');
                continue;
            } else {
                $network = AdWords::$networks[(string) $row['network']];
            }

            if (!in_array((string) $row['device'], array_keys(AdWords::$devices))) {
                var_dump('Device "' . (string) $row['device'] . '" not supported.');
                continue;
            } else {
                $device = AdWords::$devices[(string) $row['device']];
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
}
