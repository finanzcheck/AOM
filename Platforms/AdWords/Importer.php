<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use AdWordsUser;
use Monolog\Logger;
use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Settings;
use Piwik\Site;
use ReportUtils;

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
    /**
     * When no period is provided, AdWords (re)imports the last 3 days unless they have been (re)imported today.
     * Today's data is always being reimported.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return mixed|void
     */
    public function setPeriod($startDate = null, $endDate = null)
    {
        // Overwrite default period
        if (null === $startDate || null === $endDate) {

            $startDate = date('Y-m-d');

            // (Re)import the last 3 days unless they have been (re)imported today
            for ($i = -3; $i <= -1; $i++) {
                if (Db::fetchOne(
                        'SELECT DATE(MAX(ts_created)) FROM '
                        . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS)
                        . ' WHERE date = "' . date('Y-m-d', strtotime($i . ' day', time())) . '"'
                    ) != date('Y-m-d')
                ) {
                    $startDate = date('Y-m-d', strtotime($i . ' day', time()));
                    break;
                }
            }

            $endDate = date('Y-m-d');
            $this->log(Logger::INFO, 'Identified period from ' . $startDate . ' until ' . $endDate . ' to import.');
        }

        parent::setPeriod($startDate, $endDate);
    }

    /**
     * Imports all active accounts day by day.
     */
    public function import()
    {
        $settings = new Settings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_AD_WORDS]['accounts'] as $accountId => $account) {
            if (array_key_exists('active', $account) && true === $account['active']) {
                foreach (AOM::getPeriodAsArrayOfDates($this->startDate, $this->endDate) as $date) {
                    $this->importAccount($accountId, $account, $date);
                }
            } else {
                $this->log(Logger::INFO, 'Skipping inactive account.');
            }
        }
    }

    /**
     * @param string $accountId
     * @param array $account
     * @param string $date
     * @throws \Exception
     */
    private function importAccount($accountId, $account, $date)
    {
        $this->log(Logger::INFO, 'Starting import of AdWords account ' . $accountId . ' for date ' . $date . ' now.');

        // Delete data from "aom_adwords" and "aom_adwords_gclid"
        $this->deleteExistingData(AOM::PLATFORM_AD_WORDS, $accountId, $account, $date);

        $user = AdWords::getAdWordsUser($account);

        $user->LogAll();

        $reportUtils = new ReportUtils();

        $this->importCriteriaPerformanceReport($reportUtils, $user, $accountId, $account, $date);
        $this->importClickPerformanceReport($reportUtils, $user, $accountId, $account, $date);
    }

    /**
     * @param ReportUtils $reportUtils
     * @param AdWordsUser $user
     * @param $accountId
     * @param $account
     * @param $date
     */
    private function importCriteriaPerformanceReport(ReportUtils $reportUtils, AdWordsUser $user, $accountId, $account, $date)
    {
        $xmlString = $reportUtils->DownloadReportWithAwql(
            'SELECT AccountDescriptiveName, AccountCurrencyCode, AccountTimeZoneId, CampaignId, CampaignName, '
            . 'AdGroupId, AdGroupName, Id, Criteria, CriteriaType, AdNetworkType1, AdNetworkType2, AveragePosition, '
            . 'Conversions, QualityScore, CpcBid, Impressions, Clicks, GmailSecondaryClicks, Cost, Date '
            . 'FROM CRITERIA_PERFORMANCE_REPORT WHERE Impressions > 0 DURING '
            . str_replace('-', '', $date) . ','
            . str_replace('-', '', $date),
            null,
            $user,
            'XML',
            [
                'version' => 'v201607',
                'skipReportHeader' => true,
                'skipColumnHeader' => true,
                'skipReportSummary' => true,
            ]
        );
        $xml = simplexml_load_string($xmlString);


        // Matching placements based on the string in the value track param {placement} did not work successfully.
        // This is why we aggregate up all placements of an ad group and merge on that level.
        $consolidatedData = [];
        foreach ($xml->table->row as $row) {


            // Clicks of Google Sponsored Promotions (GSP) are more like more engaged ad views than real visits,
            // i.e. we have to reassign clicks (and therewith recalculate CpC)
            // (see http://marketingland.com/gmail-sponsored-promotions-everything-need-know-succeed-direct-response-gsp-part-1-120938)
            if ($row['gmailClicksToWebsite'] > 0) {
                $this->log(Logger::DEBUG, 'Mapping GSP "' . $row['adGroup'] . '" "gmailClicksToWebsite" to clicks.');
                $row['clicks'] = $row['gmailClicksToWebsite'];
            }

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

            if (!in_array((string) $row['networkWithSearchPartners'], array_keys(AdWords::$networks))) {
                var_dump('Network "' . (string) $row['networkWithSearchPartners'] . '" not supported.');
                continue;
            } else {
                $network = AdWords::$networks[(string) $row['networkWithSearchPartners']];
            }

            // Construct the key for aggregation (see AdWords/Merger->buildKeyFromAdData())
            $key = ('d' === $network)
                ? implode('-', [$network, $row['campaignID'], $row['adGroupID']])
                : implode('-', [$network, $row['campaignID'], $row['adGroupID'], $row['keywordID']]);

            if (!array_key_exists($key, $consolidatedData)) {
                $consolidatedData[$key] = [
                    'date' => $row['day'],
                    'account' => $row['account'],
                    'campaignId' => $row['campaignID'],
                    'campaign' => $row['campaign'],
                    'adGroupId' => $row['adGroupID'],
                    'adGroup' => $row['adGroup'],
                    'keywordId' => $row['keywordID'],
                    'keywordPlacement' => $row['keywordPlacement'],
                    'criteriaType' => $criteriaType,
                    'network' => $network,
                    'impressions' => $row['impressions'],
                    'clicks' => $row['clicks'],
                    'cost' => ($row['cost'] / 1000000),
                    'conversions' => $row['conversions'],
                ];
            } else {

                // We must aggregate up all placements of an ad group and merge on that level.

                // These values might be no longer unique.
                if ($consolidatedData[$key]['keywordId'] != $row['keywordID']) {
                    $consolidatedData[$key]['keywordId'] = null;
                }
                if ($consolidatedData[$key]['keywordPlacement'] != $row['keywordPlacement']) {
                    $consolidatedData[$key]['keywordPlacement'] = null;
                }
                if ($consolidatedData[$key]['criteriaType'] != $criteriaType) {
                    $consolidatedData[$key]['criteriaType'] = null;
                }

                // Aggregate
                $consolidatedData[$key]['impressions'] = $consolidatedData[$key]['impressions'] + $row['impressions'];
                $consolidatedData[$key]['clicks'] = $consolidatedData[$key]['clicks'] + $row['clicks'];
                $consolidatedData[$key]['cost'] =  $consolidatedData[$key]['cost'] + ($row['cost'] / 1000000);
                $consolidatedData[$key]['conversions'] = $consolidatedData[$key]['conversions'] + $row['conversions'];
            }
        }

        // Write consolidated data to Piwik's database
        // TODO: Use MySQL transaction to improve performance!
        foreach ($consolidatedData as $data) {
            Db::query(
                'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS)
                . ' (id_account_internal, idsite, date, account, campaign_id, campaign, ad_group_id, ad_group, '
                . 'keyword_id, keyword_placement, criteria_type, network, impressions, clicks, cost, conversions, '
                . 'ts_created) '
                . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $accountId,
                    $account['websiteId'],
                    $data['date'],
                    $data['account'],
                    $data['campaignId'],
                    $data['campaign'],
                    $data['adGroupId'],
                    $data['adGroup'],
                    $data['keywordId'],
                    $data['keywordPlacement'],
                    $data['criteriaType'],
                    $data['network'],
                    $data['impressions'],
                    $data['clicks'],
                    $data['cost'],
                    $data['conversions'],
                ]
            );
        }
    }

    /**
     * Imports the AdWords click performance report into adwords_gclid-table.
     * Tries to fix/update ad params of related visits when they are empty.
     *
     * @param ReportUtils $reportUtils
     * @param AdWordsUser $user
     * @param $accountId
     * @param $account
     * @param $date
     */
    private function importClickPerformanceReport(ReportUtils $reportUtils, AdWordsUser $user, $accountId, $account, $date)
    {
        $xmlString = $reportUtils->DownloadReportWithAwql(
            'SELECT AccountDescriptiveName, AdFormat, AdGroupId, AdGroupName, AdNetworkType1, AdNetworkType2, '
            . 'AoiMostSpecificTargetId, CampaignId, CampaignLocationTargetId, CampaignName, '
            . 'ClickType, CreativeId, CriteriaId, CriteriaParameters, Date, Device, ExternalCustomerId, GclId, '
            . 'KeywordMatchType, LopMostSpecificTargetId, Page, Slot, UserListId '
            . 'FROM CLICK_PERFORMANCE_REPORT DURING '
            . str_replace('-', '', $date) . ','
            . str_replace('-', '', $date),
            null,
            $user,
            'XML',
            [
                'version' => 'v201607',
                'skipReportHeader' => true,
                'skipColumnHeader' => true,
                'skipReportSummary' => true,
            ]
        );
        $xml = simplexml_load_string($xmlString);

        // Get all visits which ad params we could possibly improve
        // We cannot use gclid as key as multiple visits might have the same gclid!
        $visits = Db::fetchAssoc(
            'SELECT idvisit, 
                  SUBSTRING_INDEX(SUBSTR(aom_ad_params, LOCATE(\'gclid\', aom_ad_params)+CHAR_LENGTH(\'gclid\')+3),\'"\',1) AS gclid, 
                  aom_ad_params
                FROM ' . Common::prefixTable('log_visit') . '
                WHERE idsite = ? AND aom_platform = ? AND visit_first_action_time >= ? AND
                    visit_first_action_time <= ?',
            [
                $account['websiteId'],
                AOM::PLATFORM_AD_WORDS,
                AOM::convertLocalDateTimeToUTC($date . ' 00:00:00', Site::getTimezoneFor($account['websiteId'])),
                AOM::convertLocalDateTimeToUTC($date . ' 23:59:59', Site::getTimezoneFor($account['websiteId'])),
            ]
        );
        foreach ($visits as &$visit) {
            $visit['adParams'] = @json_decode($visit['aom_ad_params'], true);
            if (!is_array($visit['adParams'])) {
                $visit['adParams'] = [];
            }
        }

        // Persist data in aom_adwords_gclid and build up a lookup table based on gclids
        $gclids = [];
        foreach ($xml->table->row as $row) {

            // Map some values
            if (!in_array((string) $row['networkWithSearchPartners'], array_keys(AdWords::$networks))) {
                var_dump('Network "' . (string) $row['networkWithSearchPartners'] . '" not supported.');
                continue;
            } else {
                $network = AdWords::$networks[(string) $row['networkWithSearchPartners']];
            }

            $gclids[(string) $row['googleClickID']] = [
                'campaignId' => (string) $row['campaignID'],
                'adGroupId' => (string) $row['adGroupID'],
                'placement' => (string) $row['keywordPlacement'],
                'creative' => (string) $row['adID'],
                'network' => $network,
            ];

            // Write to database
            // TODO: Use MySQL transaction to improve performance!
            Db::query(
                'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS) . '_gclid'
                    . ' (id_account_internal, idsite, date, account, campaign_id, campaign, ad_group_id, ad_group, '
                    . 'keyword_id, keyword_placement, match_type, ad_id, ad_type, network, device, gclid, ts_created) '
                    . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $accountId,
                    $account['websiteId'],
                    $row['day'],
                    $row['account'],
                    $row['campaignID'],
                    $row['campaign'],
                    $row['adGroupID'],
                    $row['adGroup'],
                    $row['keywordID'],
                    $row['keywordPlacement'],
                    $row['matchType'],
                    $row['adID'],
                    $row['adType'],
                    $network,
                    $row['device'],
                    (string) $row['googleClickID'],
                ]
            );
        }

        // Improve ad params
        foreach ($visits as $visit) {
            if (array_key_exists($visit['gclid'], $gclids)
                && (!array_key_exists('campaignId', $visit['adParams'])
                    || $visit['adParams']['campaignId'] != $gclids[$visit['gclid']]['campaignId']
                    || !array_key_exists('adGroupId', $visit['adParams'])
                    || $visit['adParams']['adGroupId'] != $gclids[$visit['gclid']]['adGroupId']
                    || !array_key_exists('network', $visit['adParams'])
                    || $visit['adParams']['network'] != $gclids[$visit['gclid']]['network'])
            ) {
                $visit['adParams']['campaignId'] = $gclids[$visit['gclid']]['campaignId'];
                $visit['adParams']['adGroupId'] = $gclids[$visit['gclid']]['adGroupId'];
                $visit['adParams']['placement'] = $gclids[$visit['gclid']]['placement'];
                $visit['adParams']['creative'] = $gclids[$visit['gclid']]['creative'];
                $visit['adParams']['network'] = $gclids[$visit['gclid']]['network'];

                Db::exec("UPDATE " . Common::prefixTable('log_visit')
                    . " SET aom_ad_params = '" . json_encode($visit['adParams']) . "'"
                    . " WHERE idvisit = " . $visit['idvisit']);

                $this->log(
                    Logger::DEBUG,
                    'Improved ad params of visit ' . $visit['idvisit'] . ' via gclid-matching.'
                );
            }
        }
    }

    /**
     * Delete data from "aom_adwords" and "aom_adwords_gclid"
     *
     * @param string $platformName
     * @param string $accountId
     * @param string $account
     * @param int $date
     */
    public function deleteExistingData($platformName, $accountId, $account, $date)
    {
        parent::deleteExistingData(AOM::PLATFORM_AD_WORDS, $accountId, $account['websiteId'], $date);

        // We also need to delete data from "aom_adwords_gclid"
        $deleted = Db::deleteAllRows(
            AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS) . '_gclid',
            'WHERE id_account_internal = ? AND idsite = ? AND date = ?',
            'date',
            100000,
            [
                $accountId,
                $account['websiteId'],
                $date,
            ]
        );
        $this->log(Logger::DEBUG, 'Deleted existing AdWords gclid-data (' . $deleted . ' records).');
    }

    /**
     * Convenience function for shorter logging statements
     *
     * @param string $logLevel
     * @param string $message
     * @param array $additionalContext
     */
    private function log($logLevel, $message, $additionalContext = [])
    {
        $this->logger->log(
            $logLevel,
            $message,
            array_merge(['platform' => AOM::PLATFORM_AD_WORDS, 'task' => 'import'], $additionalContext)
        );
    }
}
