<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Taboola;

use Exception;
use Monolog\Logger;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Platforms\ImportException;
use Piwik\Plugins\AOM\SystemSettings;
use Piwik\Site;

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
    /**
     * Imports all active accounts day by day
     */
    public function import()
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_TABOOLA]['accounts'] as $accountId => $account) {
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
     */
    private function importAccount($accountId, $account, $date)
    {
        $this->log(Logger::INFO, 'Will import Taboola account ' . $accountId. ' for date ' . $date . ' now.');
        $this->deleteExistingData(AOM::PLATFORM_TABOOLA, $accountId, $account['websiteId'], $date);

        $accessToken = $this->getAccessToken($account);

        // Although stated in the documentation, the "campaign-summary" "campaign_site_day_breakdown" does not export
        // the campaign name. Thus we do this additional call.
        $campaignIdNameMapping = $this->getCampaignNames($account, $accessToken, $date);

        $reportData = $this->getReportData($account, $accessToken, $date);

        // We convert the reported currency to the website's currency if they are different.
        // The exchange rate can differ on a daily basis. It is being cached here to avoid unnecessary API requests.
        // If base and target currency are the same, the exchange rate is 1.0.
        $exchangeRatesCache = [];

        foreach ($reportData as $row) {

            $date = substr($row['date'], 0, 10);

            // Get the exchange rate
            $exchangeRateKey = $date . '-' . $row['currency'] . '' . Site::getCurrencyFor($account['websiteId']);
            if (!array_key_exists($exchangeRateKey, $exchangeRatesCache)) {
                $exchangeRatesCache[$exchangeRateKey] =
                    AOM::getExchangeRate($row['currency'], Site::getCurrencyFor($account['websiteId']), $date);
            }
            $exchangeRate = $exchangeRatesCache[$exchangeRateKey];

            // TODO: Use MySQL transaction to improve performance!
            Db::query(
                'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_TABOOLA)
                    . ' (id_account_internal, idsite, date, campaign_id, campaign, site_id, site, impressions, clicks, '
                    . 'cost, conversions, ts_created) '
                    . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $accountId,
                    $account['websiteId'],
                    $date,
                    $row['campaign'],
                    $campaignIdNameMapping[(string) $row['campaign']],
                    $row['site'],
                    $row['site_name'],
                    $row['impressions'],
                    $row['clicks'],
                    ($row['spent'] * $exchangeRate),
                    $row['cpa_actions_num'],
                ]
            );
        }

        $this->log(Logger::INFO, 'Imported ' . count($reportData) . ' records of Taboola account ' . $accountId . '.');
    }

    /**
     * @param array $account
     * @return string
     * @throws Exception
     */
    private function getAccessToken(array $account)
    {
        $ch = curl_init();
        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://backstage.taboola.com/backstage/oauth/token?client_id=' . $account['clientId']
            . '&client_secret=' . $account['clientSecret'] . '&grant_type=client_credentials'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded',]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POST, true);

        $output = curl_exec($ch);
        $response = json_decode($output, true);
        if (!is_array($response) || !array_key_exists('access_token', $response)) {
            $this->logger->error('Taboola API-request to get access token failed (response: "' . $output . '").');
            throw new \Exception();
        }

        $error = curl_errno($ch);
        if ($error > 0) {
            $this->logger->error(
                'Taboola API-request to get access token failed (error #' . $error . ': ' . curl_error($ch) . ').'
            );
            throw new \Exception();
        }
        curl_close($ch);

        return $response['access_token'];
    }

    /**
     * Although stated in the documentation, the "campaign-summary" "campaign_site_day_breakdown" does not export
     * the campaign name. Thus we do this additional call.
     *
     * @param array $account
     * @param string $accessToken
     * @param string $date
     * @return array
     * @throws ImportException
     */
    private function getCampaignNames(array $account, $accessToken, $date)
    {
        $ch = curl_init();
        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://backstage.taboola.com/backstage/api/1.0/' . $account['accountName'] . '/reports/campaign-summary'
            . '/dimensions/campaign_breakdown?start_date=' . $date . '&end_date=' . $date
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken,]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $output = curl_exec($ch);
        $response = json_decode($output, true);
        if (!is_array($response) || !array_key_exists('results', $response) || !is_array($response['results']) ) {
            $this->logger->error('Taboola API-request to get report data failed (response: "' . $output . '").');
            throw new ImportException();
        }

        $error = curl_errno($ch);
        if ($error > 0) {
            $this->logger->error(
                'Taboola API-request to get report data failed (error #' . $error . ': ' . curl_error($ch) . ').'
            );
            throw new ImportException();
        }
        curl_close($ch);

        $campaignIdNameMapping = [];
        foreach ($response['results'] as $row) {
            $campaignIdNameMapping[(string) $row['campaign']] = $row['campaign_name'];
        }

        return $campaignIdNameMapping;
    }

    /**
     * @param array $account
     * @param string $accessToken
     * @param string $date
     * @return array
     * @throws ImportException
     */
    private function getReportData(array $account, $accessToken, $date)
    {
        $ch = curl_init();
        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://backstage.taboola.com/backstage/api/1.0/' . $account['accountName'] . '/reports/campaign-summary'
                . '/dimensions/campaign_site_day_breakdown?start_date=' . $date . '&end_date=' . $date
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken,]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);

        $output = curl_exec($ch);
        $response = json_decode($output, true);
        if (!is_array($response) || !array_key_exists('results', $response) || !is_array($response['results']) ) {
            $this->logger->error('Taboola API-request to get report data failed (response: "' . $output . '").');
            throw new ImportException();
        }

        $error = curl_errno($ch);
        if ($error > 0) {
            $this->logger->error(
                'Taboola API-request to get report data failed (error #' . $error . ': ' . curl_error($ch) . ').'
            );
            throw new ImportException();
        }
        curl_close($ch);

        return $response['results'];
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
            array_merge(['platform' => AOM::PLATFORM_TABOOLA, 'task' => 'import'], $additionalContext)
        );
    }
}
