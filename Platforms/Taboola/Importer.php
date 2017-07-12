<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Taboola;

use Monolog\Logger;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractImporter;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Platforms\ImportException;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Plugins\AOM\Services\ExchangeRateService;
use Piwik\Plugins\AOM\SystemSettings;
use Piwik\Site;

class Importer extends AbstractImporter implements ImporterInterface
{
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
     * @throws ImportException
     */
    private function importAccount($accountId, $account, $date)
    {
        $this->log(Logger::INFO, 'Will import Taboola account ' . $accountId. ' for date ' . $date . ' now.');
        $this->deleteExistingData(AOM::PLATFORM_TABOOLA, $accountId, $account['websiteId'], $date);

        // The Taboola API is not reliable; we might need some retries
        $accessToken = $this->getAccessToken($account);


        // Although stated in the documentation, the "campaign-summary" "campaign_site_day_breakdown" does not export
        // the campaign name. Thus we do this additional call.
        $campaignIdNameMapping = $this->getCampaignNames($account, $accessToken, $date);

        $reportData = $this->getReportData($account, $accessToken, $date);

        // We convert the reported currency to the website's currency if they are different.
        // The exchange rate can differ on a daily basis. It is being cached here to avoid unnecessary API requests.
        // If base and target currency are the same, the exchange rate is 1.0.
        $exchangeRatesCache = [];

        // We'll create a very big INSERT here to improve performance (INSERT INTO a (b,c) VALUES (1,1),(1,2),...)
        $dataToInsert = [];
        foreach ($reportData as $row) {

            $date = substr($row['date'], 0, 10);

            // Get the exchange rate
            $exchangeRateKey = $date . '-' . $row['currency'] . '' . Site::getCurrencyFor($account['websiteId']);
            if (!array_key_exists($exchangeRateKey, $exchangeRatesCache)) {
                $exchangeRatesCache[$exchangeRateKey] =
                    ExchangeRateService::getExchangeRate(
                        $row['currency'],
                        Site::getCurrencyFor($account['websiteId']),
                        $date
                    );
            }
            $exchangeRate = $exchangeRatesCache[$exchangeRateKey];

            array_push(
                $dataToInsert,
                $accountId, $account['websiteId'], $date, $row['campaign'],
                $campaignIdNameMapping[(string) $row['campaign']], $row['site'], $row['site_name'], $row['impressions'],
                $row['clicks'], ($row['spent'] * $exchangeRate), $row['cpa_actions_num']
            );
        }

        if (count($dataToInsert) > 0) {

            // Setup the placeholders - a fancy way to make the long "(?, ?, ?)..." string
            $columns = ['id_account_internal', 'idsite', 'date', 'campaign_id', 'campaign', 'site_id', 'site',
                'impressions', 'clicks', 'cost', 'conversions', 'ts_created'];
            $rowPlaces = '(' . implode(', ', array_fill(0, count($columns) - 1, '?')) . ', NOW())';
            $allPlaces = implode(', ', array_fill(0, count($reportData), $rowPlaces));

            $result = Db::query(
                'INSERT INTO ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA)
                . ' (' . implode(', ', $columns) . ') VALUES ' . $allPlaces,
                $dataToInsert
            );

            $this->log(
                Logger::DEBUG,
                'Inserted ' . $result->rowCount() . ' records of Taboola account ' . $accountId . ' into '
                    . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA) . '.'
            );
        }
    }

    /**
     * @param array $account
     * @return string
     * @throws ImportException
     */
    private function getAccessToken(array $account)
    {
        // The Taboola API is not reliable; we might need some retries
        $attempts = 0;

        while ($attempts < 5) {
            try {
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
                    $this->log(
                        Logger::WARNING,
                        'Taboola API-request to get access token failed (response: "' . $output . '").'
                    );
                    throw new ImportException();
                }

                $error = curl_errno($ch);
                if ($error > 0) {
                    $this->log(
                        Logger::WARNING,
                        'Taboola API-request to get access token failed (error #' . $error . ': ' . curl_error($ch) . ').'
                    );
                    throw new ImportException();
                }
                curl_close($ch);
                $this->log(Logger::DEBUG, 'Got Taboola access token.');

                return $response['access_token'];

            } catch(ImportException $e) {

                $attempts++;

                if ($attempts > 5) {
                    $error = 'Failed to get Taboola access token.';
                    $this->log(Logger::ERROR, $error);
                    throw new ImportException($error);
                }
            }
        }
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
        // The Taboola API is not reliable; we might need some retries
        $attempts = 0;

        while ($attempts < 5) {
            try {
                $ch = curl_init();
                curl_setopt(
                    $ch,
                    CURLOPT_URL,
                    'https://backstage.taboola.com/backstage/api/1.0/' . $account['accountName']
                        . '/reports/campaign-summary/dimensions/campaign_breakdown?start_date=' . $date
                        . '&end_date=' . $date
                );
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken,]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);

                $output = curl_exec($ch);
                $response = json_decode($output, true);
                if (!is_array($response) || !array_key_exists('results', $response) || !is_array($response['results']) ) {
                    $this->log(
                        Logger::WARNING,
                        'Taboola API-request to get report data failed (response: "' . $output . '").'
                    );
                    throw new ImportException();
                }

                $error = curl_errno($ch);
                if ($error > 0) {
                    $this->log(
                        Logger::WARNING,
                        'Taboola API-request to get report data failed (error #' . $error . ': ' . curl_error($ch) . ').'
                    );
                    throw new ImportException();
                }
                curl_close($ch);

                $campaignIdNameMapping = [];
                foreach ($response['results'] as $row) {
                    $campaignIdNameMapping[(string) $row['campaign']] = $row['campaign_name'];
                }

                $this->log(Logger::DEBUG, 'Got Taboola campaign ID to campaign name mapping.');

                return $campaignIdNameMapping;

            } catch(ImportException $e) {

                $attempts++;

                if ($attempts > 5) {
                    $error = 'Failed to get Taboola campaign names.';
                    $this->log(Logger::ERROR, $error);
                    throw new ImportException($error);
                }
            }
        }
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
        // The Taboola API is not reliable; we might need some retries
        $attempts = 0;

        while ($attempts < 5) {

            try {
                $ch = curl_init();
                curl_setopt(
                    $ch,
                    CURLOPT_URL,
                    'https://backstage.taboola.com/backstage/api/1.0/' . $account['accountName'] . '/reports'
                        . '/campaign-summary/dimensions/campaign_site_day_breakdown?start_date=' . $date
                        . '&end_date=' . $date
                );
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken,]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 180);

                $output = curl_exec($ch);
                $response = json_decode($output, true);
                if (!is_array($response) || !array_key_exists('results', $response) || !is_array($response['results']) ) {
                    $this->log(
                        Logger::WARNING,
                        'Taboola API-request to get report data failed (response: "' . $output . '").'
                    );
                    throw new ImportException();
                }

                $error = curl_errno($ch);
                if ($error > 0) {
                    $this->log(
                        Logger::WARNING,
                        'Taboola API-request to get report data failed (error #' . $error . ': ' . curl_error($ch) . ').'
                    );
                    throw new ImportException();
                }
                curl_close($ch);

                $this->log(Logger::DEBUG, 'Got Taboola report data.');

                return $response['results'];

            } catch(ImportException $e) {

                $attempts++;

                if ($attempts > 5) {
                    $error = 'Failed to get Taboola report data.';
                    $this->log(Logger::ERROR, $error);
                    throw new ImportException($error);
                }
            }
        }
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
