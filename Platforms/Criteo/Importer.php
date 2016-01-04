<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Exception;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Settings;
use SoapClient;
use SoapFault;
use SoapHeader;

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
    /**
     * Imports all active accounts day by day
     */
    public function import()
    {
        $settings = new Settings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_CRITEO]['accounts'] as $accountId => $account) {
            if (array_key_exists('active', $account) && true === $account['active']) {
                foreach ($this->getPeriodAsArrayOfDates() as $date) {
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
     */
    private function importAccount($accountId, $account, $date)
    {
        $this->logger->info('Will import account ' . $accountId. ' for date ' . $date . ' now.');
        $this->deleteImportedData(Criteo::getDataTableName(), $accountId, $account['websiteId'], $date);

        $soapClient = new SoapClient('https://advertising.criteo.com/api/v201010/advertiserservice.asmx?WSDL', [
            'soap_version' => SOAP_1_2,
            'exceptions' => true,
            'trace' => 0,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);

        $clientLogin = new \stdClass();
        $clientLogin->username = $account['username'];
        $clientLogin->password = $account['password'];

        // TODO: Use multiple try catch blocks instead?!
        try {
            $loginResponse = $soapClient->__soapCall('clientLogin', [$clientLogin]);

            $apiHeader = new \stdClass();
            $apiHeader->appToken = $interval = $account['appToken'];
            $apiHeader->authToken = $loginResponse->clientLoginResult;

            // Create Soap Header, then set the Headers of Soap Client.
            $soapHeader = new SoapHeader('https://advertising.criteo.com/API/v201010', 'apiHeader', $apiHeader, false);
            $soapClient->__setSoapHeaders([$soapHeader]);

            // Get names of campaigns
            $campaigns = [];
            $getCampaignsParameters = new \stdClass();
            $getCampaignsParameters->campaignSelector = new \stdClass();
            $getCampaignsParameters->campaignSelector->campaignStatus = ['RUNNING', 'NOT_RUNNING'];
            $result = $soapClient->__soapCall('getCampaigns', [$getCampaignsParameters]);
            if (!is_array($result->getCampaignsResult->campaign)) {
                return;
            }
            foreach ($result->getCampaignsResult->campaign as $campaign) {
                $campaigns[$campaign->campaignID] = $campaign->campaignName;
            }

            // Schedule report for date range
            $scheduleReportJobParameters = new \stdClass();
            $scheduleReportJobParameters->reportJob = new \stdClass();
            $scheduleReportJobParameters->reportJob->reportSelector = new \stdClass();
            $scheduleReportJobParameters->reportJob->reportType = 'Campaign';
            $scheduleReportJobParameters->reportJob->aggregationType = 'Daily';
            $scheduleReportJobParameters->reportJob->startDate = $date;
            $scheduleReportJobParameters->reportJob->endDate = $date;
            $scheduleReportJobParameters->reportJob->selectedColumns = [];
            $scheduleReportJobParameters->reportJob->isResultGzipped = false;
            $result = $soapClient->__soapCall('scheduleReportJob', [$scheduleReportJobParameters]);
            $jobId = $result->jobResponse->jobID;

            // Wait until report has been completed
            $jobIsPending = true;
            while (true === $jobIsPending) {
                $getJobStatus = new \stdClass();
                $getJobStatus->jobID = $jobId;
                $result = $soapClient->__soapCall('getJobStatus', [$getJobStatus]);
                if ('Completed' === $result->getJobStatusResult) {
                    $jobIsPending = false;
                } else {
                    sleep(5);
                }
            }

            // Download report
            $getReportDownloadUrl = new \stdClass();
            $getReportDownloadUrl->jobID = $jobId;
            $result = $soapClient->__soapCall('getReportDownloadUrl', [$getReportDownloadUrl]);
            $xmlString = file_get_contents($result->jobURL);
            $xml = simplexml_load_string($xmlString);

            // TODO: Use MySQL transaction to improve performance!
            foreach ($xml->table->rows->row as $row) {
                 Db::query(
                    'INSERT INTO ' . Criteo::getDataTableName()
                        . ' (id_account_internal, idsite, date, campaign_id, campaign, impressions, clicks, cost, '
                        . 'conversions, conversions_value, conversions_post_view, conversions_post_view_value, '
                        . 'ts_created) '
                        . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $accountId,
                        $account['websiteId'],
                        $row['dateTime'],
                        $row['campaignID'],
                        $campaigns[(string) $row['campaignID']],
                        $row['impressions'],
                        $row['click'],
                        $row['cost'],
                        $row['sales'],
                        $row['orderValue'],
                        $row['salesPostView'],
                        $row['orderValuePostView'],
                    ]
                );
            }

            // TODO: Improve exception handling!
        } catch (SoapFault $fault) {
            echo $fault->faultcode.'-'.$fault->faultstring;
        } catch (Exception $e) {
            echo $e->getMessage();
            echo $soapClient->__getLastResponse();
        }
    }
}
