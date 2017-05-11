<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\Reporting\v201702\DownloadFormat;
use Google\AdsApi\AdWords\Reporting\v201702\ReportDownloader;
use Monolog\Logger;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Site;
use Psr\Log\LoggerInterface;

class ImporterClickPerformance
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = (null === $logger ? AOM::getTasksLogger() : $logger);
    }

    /**
     * Imports the AdWords click performance report into adwords_gclid-table.
     * Tries to fix/update ad params of related visits when they are empty.
     *
     * @param AdWordsSession $adWordsSession
     * @param $accountId
     * @param $account
     * @param $date
     */
    public function import(AdWordsSession $adWordsSession, $accountId, $account, $date)
    {
        $reportQuery = 'SELECT AccountDescriptiveName, AdFormat, AdGroupId, AdGroupName, AdNetworkType1, '
            . 'AdNetworkType2, AoiMostSpecificTargetId, CampaignId, CampaignLocationTargetId, CampaignName, ClickType, '
            . 'CreativeId, CriteriaId, CriteriaParameters, Date, Device, ExternalCustomerId, GclId, KeywordMatchType, '
            . 'LopMostSpecificTargetId, Page, Slot, UserListId '
            . 'FROM CLICK_PERFORMANCE_REPORT DURING '
            . str_replace('-', '', $date) . ','
            . str_replace('-', '', $date);

        $reportDownloader = new ReportDownloader($adWordsSession);
        $reportDownloadResult = $reportDownloader->downloadReportWithAwql(
            $reportQuery, DownloadFormat::XML);

        $xml = simplexml_load_string($reportDownloadResult->getAsString());


        // Get all visits which ad params we could possibly improve

        // Leave one hour tolerance for visits that arrive late
        $endDate = new \DateTime($date . ' 23:59:59', new \DateTimeZone(Site::getTimezoneFor($account['websiteId'])));
        $endDate->add(new \DateInterval('PT1H'));
        $endDate->setTimezone(new \DateTimeZone('UTC'));
        $endDate = $endDate->format('Y-m-d H:i:s');

        // We cannot use gclid as key as multiple Piwik visits might have the same gclid (e.g. when the visitor reload)!
        $visits = Db::fetchAssoc(
            'SELECT idvisit, 
                  SUBSTRING_INDEX(
                    SUBSTR(aom_ad_params, LOCATE(\'gclid\', aom_ad_params)+CHAR_LENGTH(\'gclid\')+3),\'"\',1
                  ) AS gclid, 
                  aom_ad_params
                FROM ' . Common::prefixTable('log_visit') . '
                WHERE idsite = ? AND aom_platform = ? AND visit_first_action_time >= ?
                     AND visit_first_action_time <= ?',
            [
                $account['websiteId'],
                AOM::PLATFORM_AD_WORDS,
                AOM::convertLocalDateTimeToUTC($date . ' 00:00:00', Site::getTimezoneFor($account['websiteId'])),
                $endDate,
            ]
        );
        foreach ($visits as &$visit) {
            $visit['adParams'] = @json_decode($visit['aom_ad_params'], true);
            if (!is_array($visit['adParams'])) {
                $visit['adParams'] = [];
            }
        }

        // This will be our lookup table based on gclids
        $gclids = [];

        // We'll create a very big INSERT here to improve performance (INSERT INTO a (b,c) VALUES (1,1),(1,2),...)
        $dataToInsert = [];

        // Add data to both lookup table and big INSERT statement
        $validRows = 0;
        foreach ($xml->table->row as $row) {

            // Map some values
            if (!in_array((string) $row['networkWithSearchPartners'], array_keys(AdWords::$networks))) {
                $this->log(
                    Logger::ERROR,
                    'Network "' . (string) $row['networkWithSearchPartners'] . '" not supported.'
                );
                continue;
            } else {
                $network = AdWords::$networks[(string) $row['networkWithSearchPartners']];
            }

            // Add data to lookup table
            $gclids[(string) $row['googleClickID']] = [
                'campaignId' => (string) $row['campaignID'],
                'adGroupId' => (string) $row['adGroupID'],
                'targetId' => 'kwd-' . (string) $row['keywordID'],
                'placement' => (string) $row['keywordPlacement'],
                'creative' => (string) $row['adID'],
                'network' => $network,
                'matchType' => (string) $row['matchType'],
            ];

            // Add data to big INSERT statement
            array_push(
                $dataToInsert,
                $accountId, $account['websiteId'], $row['day'], $row['account'], $row['campaignID'], $row['campaign'],
                $row['adGroupID'], $row['adGroup'], $row['keywordID'], $row['keywordPlacement'], $row['matchType'],
                $row['adID'], $row['adType'], $network, $row['device'], (string) $row['googleClickID']
            );

            $validRows++;
        }

        $this->bulkInsertGclidData($dataToInsert, $validRows);

        // Improve ad params
        foreach ($visits as $visit) {
            if (array_key_exists($visit['gclid'], $gclids)
                && (!array_key_exists('campaignId', $visit['adParams'])
                    || $visit['adParams']['campaignId'] != $gclids[$visit['gclid']]['campaignId']
                    || !array_key_exists('adGroupId', $visit['adParams'])
                    || $visit['adParams']['adGroupId'] != $gclids[$visit['gclid']]['adGroupId']
                    || !array_key_exists('targetId', $visit['adParams'])
                    || !array_key_exists('network', $visit['adParams'])
                    || $visit['adParams']['network'] != $gclids[$visit['gclid']]['network']
                    || !array_key_exists('matchType', $visit['adParams'])
                    || $visit['adParams']['matchType'] != $gclids[$visit['gclid']]['matchType'])
            ) {
                $visit['adParams']['campaignId'] = $gclids[$visit['gclid']]['campaignId'];
                $visit['adParams']['adGroupId'] = $gclids[$visit['gclid']]['adGroupId'];
                $visit['adParams']['targetId'] = $gclids[$visit['gclid']]['targetId'];
                $visit['adParams']['placement'] = $gclids[$visit['gclid']]['placement'];
                $visit['adParams']['creative'] = $gclids[$visit['gclid']]['creative'];
                $visit['adParams']['network'] = $gclids[$visit['gclid']]['network'];
                $visit['adParams']['matchType'] = $gclids[$visit['gclid']]['matchType'];

                // TODO: Create bulk update here!
                Db::exec("UPDATE " . Common::prefixTable('log_visit')
                    . " SET aom_ad_params = '" . json_encode($visit['adParams']) . "'"
                    . " WHERE idvisit = " . $visit['idvisit']);

                $this->log(
                    Logger::DEBUG,
                    'Improved ad params of visit ' . $visit['idvisit'] . ' via gclid-matching.'
                );
            }

            // TODO:
            // There are often visits with gclid that belongs to previous days,
            // i.e. this visit should not be assigned to AdWords!
        }
    }

    /**
     * @param array $dataToInsert
     * @param $validRows
     * @throws \Exception
     */
    private function bulkInsertGclidData(array $dataToInsert, $validRows)
    {
        // Setup the placeholders - a fancy way to make the long "(?, ?, ?)..." string
        $columns = ['id_account_internal', 'idsite', 'date', 'account', 'campaign_id', 'campaign', 'ad_group_id',
            'ad_group', 'keyword_id', 'keyword_placement', 'match_type', 'ad_id', 'ad_type', 'network', 'device',
            'gclid', 'ts_created'];
        $rowPlaces = '(' . implode(', ', array_fill(0, count($columns) - 1, '?')) . ', NOW())';
        $allPlaces = implode(', ', array_fill(0, $validRows, $rowPlaces));

        // In rare cases duplicate keys occur
        $result = Db::query(
            'INSERT IGNORE INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS) . '_gclid'
            . ' (' . implode(', ', $columns) . ') VALUES ' . $allPlaces,
            $dataToInsert
        );
        $duplicates = $validRows - $result->rowCount();
        if ($duplicates > 0) {
            $this->log(
                Logger::WARNING,
                'Got ' . $duplicates . ' duplicate key' . (1 == $duplicates ? '' : 's') . ' when inserting into '
                . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS) . '_gclid.'
            );
        }
        if ($duplicates > 10) {
            throw new \Exception(
                'Too many duplicate key errors (' . $duplicates . ') when inserting into '
                . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS) . '_gclid.'
            );
        }

        $this->log(
            Logger::DEBUG,
            'Inserted ' . $result->rowCount() . ' records into '
            . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_AD_WORDS) . '_gclid.'
        );
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
