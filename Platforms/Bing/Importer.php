<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Bing\Proxy\ClientProxy;
use Bing\Reporting\AccountThroughAdGroupReportScope;
use Bing\Reporting\Date;
use Bing\Reporting\KeywordPerformanceReportColumn;
use Bing\Reporting\KeywordPerformanceReportRequest;
use Bing\Reporting\KeywordPerformanceReportSort;
use Bing\Reporting\PollGenerateReportRequest;
use Bing\Reporting\ReportAggregation;
use Bing\Reporting\ReportFormat;
use Bing\Reporting\ReportRequestStatusType;
use Bing\Reporting\ReportTime;
use Bing\Reporting\SortOrder;
use Bing\Reporting\SubmitGenerateReportRequest;
use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use SoapFault;

//TODO Replace
set_include_path(get_include_path() . PATH_SEPARATOR . getcwd() . '/plugins/AOM/Platforms/Bing');
include 'ReportingClasses.php';
include 'ClientProxy.php';

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
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
                'INSERT INTO ' . Common::prefixTable(
                    'aom_bing'
                ) . ' (date, account_id, account, campaign_id, campaign, '
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
                $this->platform->getSettings()->bingDeveloperToken->getValue(),
                $this->platform->getSettings()->bingAccountId->getValue(),
                $this->platform->getSettings()->bingAccessToken->getValue(),
                $this->platform->getSettings()->proxyIsActive->getValue(),
                isset($this->platform->getSettings()->proxyHost) ? $this->platform->getSettings()->proxyHost->getValue() : null,
                isset($this->platform->getSettings()->proxyPort) ? $this->platform->getSettings()->proxyPort->getValue() : null
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

            $encodedReport = new \SoapVar(
                $report,
                SOAP_ENC_OBJECT,
                'KeywordPerformanceReportRequest',
                $proxy->GetNamespace()
            );

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

                $reportRequestStatus = $this->pollGenerateReport(
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
                    return $this->downloadFile($reportDownloadUrl);
                } else {
                    if ($reportRequestStatus->Status == ReportRequestStatusType::Error) {
                        printf(
                            "The request failed. Try requesting the report " .
                            "later.\nIf the request continues to fail, contact support.\n"
                        );
                    } else // Pending
                    {
                        printf(
                            "The request is taking longer than expected.\n " .
                            "Save the report ID (%s) and try again later.\n",
                            $reportRequestId
                        );
                    }
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

    private function refreshToken()
    {
        $context = null;
        if ($this->platform->getSettings()->proxyIsActive->getValue()) {
            $context = stream_context_create(
                [
                    'http' => [
                        'proxy' => "tcp://" . $this->platform->getSettings()->proxyHost->getValue() . ":"
                            . $this->platform->getSettings()->proxyPort->getValue(),
                        'request_fulluri' => true,
                    ]
                ]
            );
        }

        $url = sprintf(
            "https://login.live.com/oauth20_token.srf?client_id=%s&grant_type=refresh_token&redirect_uri=/oauth20_desktop.srf&refresh_token=%s",
            $this->platform->getSettings()->bingClientId->getValue(),
            $this->platform->getSettings()->bingRefreshToken->getValue()
        );

        $response = file_get_contents($url, null, $context);
        $response = json_decode($response);
        $this->platform->getSettings()->bingRefreshToken->setValue($response->refresh_token);
        $this->platform->getSettings()->bingAccessToken->setValue($response->access_token);

    }

    private function pollGenerateReport($proxy, $reportRequestId)
    {
        // Set the request information.

        $request = new PollGenerateReportRequest();
        $request->ReportRequestId = $reportRequestId;

        return $proxy->GetService()->PollGenerateReport($request)->ReportRequestStatus;
    }

    private function downloadFile($reportDownloadUrl)
    {
        $data = $this->getSslPage($reportDownloadUrl);
        $head = unpack('Vsig/vver/vflag/vmeth/vmodt/vmodd/Vcrc/Vcsize/Vsize/vnamelen/vexlen', substr($data, 0, 30));
        return gzinflate(substr($data, 30 + $head['namelen'] + $head['exlen'], $head['csize']));
    }

    private function getSslPage($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_SSLv3);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
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
}
