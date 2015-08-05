<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Exception;
use Piwik\Common;
use Piwik\Db;
use SoapClient;
use SoapFault;
use SoapHeader;

class Criteo
{
    /**
     * @var  Settings
     */
    private $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    public function isActive() {
        return $this->settings->criteoIsActive->getValue();
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
            Common::prefixTable('aom_criteo'),
            'WHERE date >= ? AND date <= ?',
            'date',
            100000,
            [$startDate, $endDate]
        );

        $soapClient = new SoapClient('https://advertising.criteo.com/api/v201010/advertiserservice.asmx?WSDL', [
            'soap_version' => SOAP_1_2,
            'exceptions' => true,
            'trace' => 0,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);

        $clientLogin = new \stdClass();
        $clientLogin->username = $this->settings->criteoUsername->getValue();
        $clientLogin->password = $this->settings->criteoPassword->getValue();

        // TODO: Use multiple try catch blocks instead?!
        try {
            $loginResponse = $soapClient->__soapCall('clientLogin', [$clientLogin]);

            $apiHeader = new \stdClass();
            $apiHeader->appToken = $interval = $this->settings->criteoAppToken->getValue();
            $apiHeader->authToken = $loginResponse->clientLoginResult;

            //Create Soap Header, then set the Headers of Soap Client.
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
            $scheduleReportJobParameters->reportJob->startDate = $startDate;
            $scheduleReportJobParameters->reportJob->endDate = $endDate;
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
                    'INSERT INTO ' . Common::prefixTable('aom_criteo') . ' (date, campaign_id, campaign, '
                    . 'impressions, clicks, cost, conversions, conversions_value, conversions_post_view, '
                    . 'conversions_post_view_value) VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
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
