<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Bing\Reporting\SortOrder;
use Bing\Proxy\ClientProxy;
use Bing\Reporting\SubmitGenerateReportRequest;
use Bing\Reporting\KeywordPerformanceReportRequest;
use Bing\Reporting\ReportFormat;
use Bing\Reporting\ReportAggregation;
use Bing\Reporting\AccountThroughAdGroupReportScope;
use Bing\Reporting\ReportTime;
use Bing\Reporting\Date;
use Bing\Reporting\KeywordPerformanceReportColumn;
use Bing\Reporting\PollGenerateReportRequest;
use Bing\Reporting\ReportRequestStatusType;
use Bing\Reporting\KeywordPerformanceReportSort;
use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Plugins\AOM\Settings;
use SoapFault;

//TODO Replace
set_include_path(get_include_path() . PATH_SEPARATOR . getcwd() . '/plugins/AOM/Platforms/Bing');
include 'ReportingClasses.php';
include 'ClientProxy.php';

class Bing implements PlatformInterface
{
    const AD_CAMPAIGN_ID = 1;
    const AD_AD_GROUP_ID = 2;
    const AD_KEYWORD_ID = 3;

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
        return $this->settings->bingIsActive->getValue();
    }

    public function activatePlugin()
    {
        try {
            $sql = 'CREATE TABLE ' . Common::prefixTable('aom_bing') . ' (
                        date DATE NOT NULL,
                        account_id INTEGER NOT NULL,
                        account VARCHAR(255) NOT NULL,
                        campaign_id INTEGER NOT NULL,
                        campaign VARCHAR(255) NOT NULL,
                        ad_group_id BIGINT NOT NULL,
                        ad_group VARCHAR(255) NOT NULL,
                        keyword_id BIGINT NOT NULL,
                        keyword VARCHAR(255) NOT NULL,
                        impressions INTEGER NOT NULL,
                        clicks INTEGER NOT NULL,
                        cost FLOAT NOT NULL,
                        conversions INTEGER NOT NULL
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE INDEX index_aom_bing ON ' . Common::prefixTable('aom_bing')
                . ' (date, campaign_id)'; // TODO...
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
        Db::dropTables(Common::prefixTable('aom_bing'));
    }

    private function refreshToken()
    {
        $context = null;
        if ($this->settings->proxyIsActive->getValue()) {
            $context = stream_context_create(
                [
                    'http' => [
                        'proxy' => "tcp://" . $this->settings->proxyHost->getValue() . ":" . $this->settings->proxyPort->getValue(),
                        'request_fulluri' => true,
                    ]
                ]
            );
        }

        $url = sprintf(
            "https://login.live.com/oauth20_token.srf?client_id=%s&grant_type=refresh_token&redirect_uri=/oauth20_desktop.srf&refresh_token=%s",
            $this->settings->bingClientId->getValue(),
            $this->settings->bingRefreshToken->getValue()
        );

        $response = file_get_contents($url, null, $context);
        $response = json_decode($response);
        $this->settings->bingRefreshToken->setValue($response->refresh_token);
        $this->settings->bingAccessToken->setValue($response->access_token);

    }

    public function import($startDate, $endDate)
    {
        $data = $this->getBingReport($startDate, $endDate);

        Db::deleteAllRows(
            Common::prefixTable('aom_bing'),
            'WHERE date >= ? AND date <= ?',
            'date',
            1000000,
            [$startDate, $endDate]
        );

        $result = simplexml_load_string($data);
        foreach ($result->Table->Row as $row) {
            $date = date_create_from_format('m/j/Y', $row->GregorianDate->attributes()['value']);
            $date = $date->format('Y-m-d');

            Db::query(
                'INSERT INTO ' . Common::prefixTable('aom_bing') . ' (date, account_id, account, campaign_id, campaign, '
                . 'ad_group_id, ad_group, keyword_id, keyword, impressions, '
                . 'clicks, cost, conversions) VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $date,
                    $row->AccountId->attributes()['value'],
                    $row->AccountName->attributes()['value'],
                    $row->CampaignId->attributes()['value'],
                    $row->CampaignName->attributes()['value'],
                    $row->AdGroupId->attributes()['value'],
                    $row->AdGroupName->attributes()['value'],
                    $row->KeywordId->attributes()['value'],
                    $row->Keyword->attributes()['value'],
                    $row->Impressions->attributes()['value'],
                    $row->Clicks->attributes()['value'],
                    $row->Spend->attributes()['value'],
                    $row->Conversions->attributes()['value'],
                ]
            );
        }
    }

    public function getBingReport($startDate, $endDate)
    {
        //Always refresh token as it expires after 60m
        $this->refreshToken();
        try {
            $proxy = ClientProxy::ConstructWithAccountId(
                "https://api.bingads.microsoft.com/Api/Advertiser/Reporting/V9/ReportingService.svc?singleWsdl",
                null,
                null,
                $this->settings->bingDeveloperToken->getValue(),
                $this->settings->bingAccountId->getValue(),
                $this->settings->bingAccessToken->getValue(),
                $this->settings->proxyIsActive->getValue(),
                $this->settings->proxyHost->getValue(),
                $this->settings->proxyPort->getValue()
            );

            // Build a keyword performance report request,
            $report = new KeywordPerformanceReportRequest();

            $report->Format = ReportFormat::Xml;
            $report->ReportName = 'AOM Performance Report';
            $report->ReturnOnlyCompleteData = false;
            $report->Aggregation = ReportAggregation::Daily;

            $report->Scope = new AccountThroughAdGroupReportScope();
            $report->Scope->AccountIds = null;
            $report->Scope->AdGroups = null;
            $report->Scope->Campaigns = null;

            $report->Time = new ReportTime();
            $report->Time->CustomDateRangeStart = new Date();
            $report->Time->CustomDateRangeStart->Month = explode('-', $startDate)[1];
            $report->Time->CustomDateRangeStart->Day = explode('-', $startDate)[2];
            $report->Time->CustomDateRangeStart->Year = explode('-', $startDate)[0];
            $report->Time->CustomDateRangeEnd = new Date();
            $report->Time->CustomDateRangeEnd->Month = explode('-', $endDate)[1];
            $report->Time->CustomDateRangeEnd->Day = explode('-', $endDate)[2];
            $report->Time->CustomDateRangeEnd->Year = explode('-', $endDate)[0];

            $report->Columns = array(
                KeywordPerformanceReportColumn::TimePeriod,
                KeywordPerformanceReportColumn::AccountId,
                KeywordPerformanceReportColumn::AccountName,
                KeywordPerformanceReportColumn::CampaignId,
                KeywordPerformanceReportColumn::CampaignName,
                KeywordPerformanceReportColumn::AdGroupId,
                KeywordPerformanceReportColumn::AdGroupName,
                KeywordPerformanceReportColumn::Keyword,
                KeywordPerformanceReportColumn::KeywordId,
                KeywordPerformanceReportColumn::DeviceType,
                KeywordPerformanceReportColumn::BidMatchType,
                KeywordPerformanceReportColumn::Clicks,
                KeywordPerformanceReportColumn::Impressions,
                KeywordPerformanceReportColumn::Spend,
                KeywordPerformanceReportColumn::QualityScore,
                KeywordPerformanceReportColumn::Conversions,
            );

            // You may optionally sort by any KeywordPerformanceReportColumn, and optionally
            // specify the maximum number of rows to return in the sorted report.

            $report->Sort = array();
            $keywordPerformanceReportSort = new KeywordPerformanceReportSort();
            $keywordPerformanceReportSort->SortColumn = KeywordPerformanceReportColumn::Clicks;
            $keywordPerformanceReportSort->SortOrder = SortOrder::Ascending;
            $report->Sort[] = $keywordPerformanceReportSort;

            $encodedReport = new \SoapVar($report, SOAP_ENC_OBJECT, 'KeywordPerformanceReportRequest', $proxy->GetNamespace());

            $request = new SubmitGenerateReportRequest();
            $request->ReportRequest = $encodedReport;

            $reportRequestId = $proxy->GetService()->SubmitGenerateReport($request)->ReportRequestId;

            printf("Report Request ID: %s\n\n", $reportRequestId);


            // This sample polls every 30 seconds up to 5 minutes.
            // In production you may poll the status every 1 to 2 minutes for up to one hour.
            // If the call succeeds, stop polling. If the call or
            // download fails, the call throws a fault.

            $reportRequestStatus = null;
            for ($i = 0; $i < 60; $i++) {
                sleep(3);

                // PollGenerateReport helper method calls the corresponding Bing Ads service operation
                // to get the report request status.

                $reportRequestStatus = $this->PollGenerateReport(
                    $proxy,
                    $reportRequestId
                );

                if ($reportRequestStatus->Status == ReportRequestStatusType::Success ||
                    $reportRequestStatus->Status == ReportRequestStatusType::Error
                ) {
                    break;
                }
            }

            if ($reportRequestStatus != null) {
                if ($reportRequestStatus->Status == ReportRequestStatusType::Success) {
                    $reportDownloadUrl = $reportRequestStatus->ReportDownloadUrl;
                    printf("Downloading from %s\n\n", $reportDownloadUrl);
                    return $this->DownloadFile($reportDownloadUrl);
                } else if ($reportRequestStatus->Status == ReportRequestStatusType::Error) {
                    printf("The request failed. Try requesting the report " .
                        "later.\nIf the request continues to fail, contact support.\n");
                } else // Pending
                {
                    printf("The request is taking longer than expected.\n " .
                        "Save the report ID (%s) and try again later.\n",
                        $reportRequestId);
                }
            }

        } catch (SoapFault $e) {
            // Output the last request/response.

            print "\nLast SOAP request/response:\n";
            print $proxy->GetWsdl() . "\n";
            print $this->formatXmlString($proxy->GetService()->__getLastRequest()) . "\n";
            print $this->formatXmlString($proxy->GetService()->__getLastResponse()) . "\n";

            // Reporting service operations can throw AdApiFaultDetail.
        } catch (Exception $e) {
            if ($e->getPrevious()) {
                ; // Ignore fault exceptions that we already caught.
            } else {
                print $e->getCode() . " " . $e->getMessage() . "\n\n";
                print $e->getTraceAsString() . "\n\n";
            }
        }
    }

    protected function formatXmlString($xml)
    {
        $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);
        $token = strtok($xml, "\n");
        $result = '';
        $pad = 0;
        $matches = array();
        while ($token !== false) :
            if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) :
                $indent = 0;
            elseif (preg_match('/^<\/\w/', $token, $matches)) :
                $pad--;
                $indent = 0;
            elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
                $indent = 1;
            else :
                $indent = 0;
            endif;
            $line = str_pad($token, strlen($token) + $pad, ' ', STR_PAD_LEFT);
            $result .= $line . "\n";
            $token = strtok("\n");
            $pad += $indent;
        endwhile;

        return $result;
    }



// Check the status of the report request. The guidance of how often to poll
// for status is from every five to 15 minutes depending on the amount
// of data being requested. For smaller reports, you can poll every couple
// of minutes. You should stop polling and try again later if the request
// is taking longer than an hour.

    function PollGenerateReport($proxy, $reportRequestId)
    {
        // Set the request information.

        $request = new PollGenerateReportRequest();
        $request->ReportRequestId = $reportRequestId;

        return $proxy->GetService()->PollGenerateReport($request)->ReportRequestStatus;
    }

// Using the URL that the PollGenerateReport operation returned,
// send an HTTP request to get the report and write it to the specified
// ZIP file.

    function DownloadFile($reportDownloadUrl)
    {
        $data = file_get_contents($reportDownloadUrl);

        $head = unpack("Vsig/vver/vflag/vmeth/vmodt/vmodd/Vcrc/Vcsize/Vsize/vnamelen/vexlen", substr($data, 0, 30));
        return gzinflate(substr($data, 30 + $head['namelen'] + $head['exlen'], $head['csize']));
    }


    /**
     * Enriches a specific visit with additional Criteo information when this visit came from Criteo.
     *
     * @param array &$visit
     * @param array $ad
     * @return array
     * @throws \Exception
     */
    public function enrichVisit(array &$visit, array $ad)
    {
        $sql = 'SELECT
                    campaign_id AS campaignId,
                    campaign,
                    ad_group_id AS adGroupId,
                    ad_group AS adGroup,
                    keyword_id AS keywordId,
                    keyword,
                    clicks,
                    cost,
                    impressions,
                    conversions,
                    (cost / clicks) AS cpc
                FROM ' . Common::prefixTable('aom_bing') . '
                WHERE date = ? AND campaign_id = ? AND ad_group_id = ? AND keyword_id = ?';

        $results = Db::fetchRow(
            $sql,
            [
                date('Y-m-d', strtotime($visit['firstActionTime'])),
                $ad[self::AD_CAMPAIGN_ID],
                $ad[self::AD_AD_GROUP_ID],
                $ad[self::AD_KEYWORD_ID],
            ]
        );

        $visit['ad'] = array_merge(['source' => 'Bing'], $results);

        return $visit;



    }
}
