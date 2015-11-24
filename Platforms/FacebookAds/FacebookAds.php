<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use DateTime;
use DateTimeZone;
use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Plugins\AOM\Settings;

class FacebookAds implements PlatformInterface
{
    /**
     * @var  Settings
     */
    private $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    public function isActive()
    {
        return $this->settings->facebookAdsIsActive->getValue();
    }

    public function activatePlugin()
    {
        try {
            $sql = 'CREATE TABLE ' . Common::prefixTable('aom_facebook_ads') . ' (
                        date DATE NOT NULL,
                        account_id BIGINT NOT NULL,
                        account_name VARCHAR(255) NOT NULL,
                        campaign_group_id BIGINT NOT NULL,
                        campaign_group_name VARCHAR(255) NOT NULL,
                        campaign_id BIGINT NOT NULL,
                        campaign_name VARCHAR(255) NOT NULL,
                        adgroup_id BIGINT NOT NULL,
                        adgroup_name VARCHAR(255) NOT NULL,
                        adgroup_objective VARCHAR(255) NOT NULL,
                        spend FLOAT NOT NULL,
                        total_actions INTEGER NOT NULL
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE INDEX index_aom_facebook ON ' . Common::prefixTable('aom_facebook_ads')
                . ' (date, account_id)';  // TODO...
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if index already exists (1061)
            if (!Db::get()->isErrNo($e, '1061')) {
                throw $e;
            }
        }
    }

    public function uninstallPlugin()
    {
        Db::dropTables(Common::prefixTable('aom_facebook_ads'));
    }

    public function import($startDate, $endDate)
    {
        if (!$this->isActive()) {
            return;
        }

        // Delete existing data for the specified period
        // TODO: this might be more complicated when we already merged / assigned data to visits!?!
        // TODO: There might be more than 100000 rows?!
        Db::deleteAllRows(
            Common::prefixTable('aom_facebook_ads'),
            'WHERE date >= ? AND date <= ?',
            'date',
            100000,
            [$startDate, $endDate]
        );

        // We do not use Facebook's PHP library to keep composer dependencies to a minimum.

        // TODO: Find a better way for this...
        // This is how to obtain an ACCESS_TOKEN:
        // 1. Open https://www.facebook.com/dialog/oauth?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_URL&scope=ads_read
        // 2. This redirects to YOUR_URL?code=...
        // 3. Paste in code and open https://graph.facebook.com/v2.3/oauth/access_token?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_URL&client_secret=YOUR_CLIENT_SECRET&code=...
        // 4. Use the result as ACCESS_TOKEN

        // Calculate time interval
        // https://developers.facebook.com/docs/marketing-api/guides/chapter-7-ad-report-stats?locale=de_DE#range
        $startDate = DateTime::createFromFormat(
            'Y-m-d h:i:s',
            ($startDate . '00:00:00'),
            new DateTimeZone($this->settings->facebookAdsTimezone->getValue())
        );
        $endDate = DateTime::createFromFormat('Y-m-d h:i:s', ($endDate . '00:00:00'), new DateTimeZone($this->settings->facebookAdsTimezone->getValue()));

        // Trigger report creation
        // TODO: Consider this: https://developers.facebook.com/docs/marketing-api/guides/chapter-7-ad-report-stats?locale=de_DE#windows!
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v2.3/act_' . $this->settings->facebookAdsAccountId->getValue() . '/reportstats');
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'access_token' => $this->settings->facebookAdsAccessToken->getValue(),
            'data_columns' => '["account_id", "account_name", "campaign_group_id", "campaign_group_name", "campaign_id",
                "campaign_name", "adgroup_id", "adgroup_name", "adgroup_objective", "total_actions", "spend"]',
            'time_interval' => '{time_start:' . $startDate->getTimestamp() . ', time_stop:' . ($endDate->getTimestamp() + 86400) . '}',
            'time_increment' => 1,
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
        } elseif (is_array($response) && array_key_exists('id', $response)) {
            $reportId = $response['id'];
        } else {
            die('INVALID RESPONSE: ' . $output);
        }

        // Wait for report
        while(true) {

            sleep(30);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v2.3/' . $reportId
                . '?access_token=' . $this->settings->facebookAdsAccessToken->getValue());
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
        curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v2.3/act_'
            . $this->settings->facebookAdsUserAccountId->getValue() . '/reportstats?report_run_id=' . $reportId
            . '&access_token=' . $this->settings->facebookAdsAccessToken->getValue());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $output = curl_exec($ch);
        $error = curl_errno($ch);
        if ($error > 0) {
            die('(error #' . $error . ': ' . curl_error($ch) . ').');
        }
        curl_close($ch);

        $results = json_decode($output, true);

        // TODO: Use MySQL transaction to improve performance!
        if (array_key_exists('data', $results) && is_array($results['data'])) {
            foreach ($results['data'] as $row) {
                Db::query(
                    'INSERT INTO ' . Common::prefixTable('aom_facebook_ads') . ' (date, account_id, account_name, '
                    . 'campaign_group_id, campaign_group_name, campaign_id, campaign_name, adgroup_id, adgroup_name, '
                    . 'adgroup_objective, spend, total_actions) VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
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

    /**
     * Enriches a specific visit with additional Facebook information when this visit came from Facebook.
     *
     * @param array &$visit
     * @param array $ad
     * @return array
     * @throws \Exception
     */
    public function enrichVisit(array &$visit, array $ad)
    {
        // TODO ...
        $results = [];

        $visit['ad'] = array_merge(['source' => 'FacebookAds'], $results);

        return $visit;
    }

    /**
     * Builds a string key from the ad data that has been passed via URL (as URL-encoded JSON) and is used to reference
     * explicit platform data (this key is being stored in piwik_log_visit.aom_ad_key).
     *
     * @param array $adData
     * @return mixed
     */
    public function getAdKeyFromAdData(array $adData)
    {
        // TODO: Implement me!

        return 'not implemented';
    }
}
