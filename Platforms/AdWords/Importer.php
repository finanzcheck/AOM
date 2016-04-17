<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Settings;
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
                if (Db::fetchOne('SELECT DATE(MAX(ts_created)) FROM '
                        . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS)
                        . ' WHERE date = "' . date('Y-m-d', strtotime($i . ' day', time())) . '"') != date('Y-m-d')
                ) {
                    $startDate = date('Y-m-d', strtotime($i . ' day', time()));
                    break;
                }
            }

            $endDate = date('Y-m-d');
            $this->logger->info('Identified period from ' . $startDate. ' until ' . $endDate . ' to import.');
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

        foreach ($configuration[AOM::PLATFORM_AD_WORDS]['accounts'] as $accountId => $account) {;
            if (array_key_exists('active', $account) && true === $account['active']) {
                foreach (AOM::getPeriodAsArrayOfDates($this->startDate, $this->endDate) as $date) {
                    $this->importAccount($accountId, $account, $date);
                }
            } else {
                $this->logger->info('Skipping inactive account.');
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
        $this->logger->info('Will import AdWords account ' . $accountId. ' for date ' . $date . ' now.');
        $this->deleteExistingData(AOM::PLATFORM_AD_WORDS, $accountId, $account['websiteId'], $date);

        $user = AdWords::getAdWordsUser($account);

        $user->LogAll();

        // Download report (@see https://developers.google.com/adwords/api/docs/appendix/reports?hl=de#criteria)
        // https://developers.google.com/adwords/api/docs/appendix/reports/criteria-performance-report?hl=de
        $xmlString = ReportUtils::DownloadReportWithAwql(
            'SELECT AccountDescriptiveName, AccountCurrencyCode, AccountTimeZoneId, CampaignId, CampaignName, '
            . 'AdGroupId, AdGroupName, Id, Criteria, CriteriaType, AdNetworkType1, AdNetworkType2, AveragePosition, '
            . 'Conversions, QualityScore, CpcBid, Impressions, Clicks, Cost, Date '
            . 'FROM CRITERIA_PERFORMANCE_REPORT WHERE Impressions > 0 DURING '
            . str_replace('-', '', $date) . ','
            . str_replace('-', '', $date),
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


        // Matching placements based on the string in the value track param {placement} did not work successfully.
        // This is why we aggregate up all placements of an ad group and merge on that level.
        $consolidatedData = [];
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
}
