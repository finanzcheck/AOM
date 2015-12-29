<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use DateTime;
use DateTimeZone;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Settings;

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
    public function import($startDate, $endDate)
    {
        $settings = new Settings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'] as $id => $account) {
            $this->importAccount($account, $startDate, $endDate);
        }
    }

    private function importAccount($account, $startDate, $endDate)
    {
        // Delete existing data for the specified period
        // TODO: this might be more complicated when we already merged / assigned data to visits!?!
        // TODO: There might be more than 100000 rows?!
        Db::deleteAllRows(
            FacebookAds::getDataTableName(),
            'WHERE date >= ? AND date <= ?',
            'date',
            100000,
            [$startDate, $endDate]
        );












        // Calculate time interval
        // https://developers.facebook.com/docs/marketing-api/guides/chapter-7-ad-report-stats?locale=de_DE#range
        $startDate = DateTime::createFromFormat(
            'Y-m-d h:i:s',
            ($startDate . '00:00:00'),
            new DateTimeZone($this->platform->getSettings()->facebookAdsTimezone->getValue())
        );
        $endDate = DateTime::createFromFormat(
            'Y-m-d h:i:s',
            ($endDate . '00:00:00'),
            new DateTimeZone($this->platform->getSettings()->facebookAdsTimezone->getValue())
        );

        // Trigger report creation
        // TODO: Consider this: https://developers.facebook.com/docs/marketing-api/guides/chapter-7-ad-report-stats?locale=de_DE#windows!
        $ch = curl_init();
        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://graph.facebook.com/v2.5/act_' . $this->platform->getSettings()->facebookAdsAccountId->getValue() . '/insights'
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'access_token' => $this->platform->getSettings()->facebookAdsAccessToken->getValue(),
            'level' => 'ad',

            // TODO: https://developers.facebook.com/docs/marketing-api/reference/ad-account/insights/
//            'time_interval' => '{since:' . $startDate->getTimestamp() . ', until:' . ($endDate->getTimestamp() + 86400) . '}',

            // TODO: Following stuff is outdated!
//            'data_columns' => '["account_id", "account_name", "campaign_group_id", "campaign_group_name", "campaign_id",
//                "campaign_name", "adgroup_id", "adgroup_name", "adgroup_objective", "total_actions", "spend"]',
//            'time_interval' => '{time_start:' . $startDate->getTimestamp() . ', time_stop:' . ($endDate->getTimestamp() + 86400) . '}',
//            'time_increment' => 1,
//            'actions_group_by' => "['action_type','action_target_id']",
            'async' => true,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $output = curl_exec($ch);
        $error = curl_errno($ch);
        if ($error > 0) {
            die('(error #' . $error . ': ' . curl_error($ch) . ').');
        }
        curl_close($ch);
        $response = json_decode($output, true);
        if (is_array($response) && array_key_exists('error', $response)) {
            die('ERROR: ' . $output);
        } elseif (is_array($response) && array_key_exists('report_run_id', $response)) {
            $reportId = $response['report_run_id'];
        } else {
            die('INVALID RESPONSE: ' . $output);
        }

        // Wait for report
        while(true) {

            sleep(30);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v2.5/' . $reportId
                . '?access_token=' . $this->platform->getSettings()->facebookAdsAccessToken->getValue());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $output = curl_exec($ch);
            $error = curl_errno($ch);
            if ($error > 0) {
                die('(error #' . $error . ': ' . curl_error($ch) . ').');
            }
            curl_close($ch);
            $response = json_decode($output, true);
            if (is_array($response) && array_key_exists('error', $response)) {
                die('ERROR: ' . $output);
            } elseif (is_array($response) && array_key_exists('async_status', $response)) {
                if ('Job Completed' === $response['async_status']) {
                    break;
                }
                continue;
            } else {
                die('INVALID RESPONSE: ' . $output);
            }
        }

        // Download report
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v2.5/act_'
            . $this->platform->getSettings()->facebookAdsUserAccountId->getValue() . '/insights?report_run_id='
            . $reportId . '&access_token=' . $this->platform->getSettings()->facebookAdsAccessToken->getValue());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $output = curl_exec($ch);
        $error = curl_errno($ch);
        if ($error > 0) {
            die('(error #' . $error . ': ' . curl_error($ch) . ').');
        }
        curl_close($ch);

        $results = json_decode($output, true);

        var_dump($results);

        // TODO: Use MySQL transaction to improve performance!
        if (array_key_exists('data', $results) && is_array($results['data'])) {
            foreach ($results['data'] as $row) {
                Db::query(
                    'INSERT INTO ' . FacebookAds::getDataTableName() . ' (idsite, date, account_id, '
                    . 'account_name, campaign_group_id, campaign_group_name, campaign_id, campaign_name, adgroup_id, '
                    . 'adgroup_name, adgroup_objective, spend, total_actions) VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $account['websiteId'],
                        $row['date_start'],
                        $row['account_id'],
                        $row['account_name'],
                        $row['campaign_group_id'],
                        $row['campaign_group_name'],
                        $row['campaign_id'],
                        $row['campaign_name'],
                        $row['adgroup_id'],
                        $row['adgroup_name'],
                        $row['adgroup_objective'],
                        $row['spend'],
                        $row['total_actions'],
                    ]
                );
            }
        }
    }
}
