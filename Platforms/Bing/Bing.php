<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Piwik\Common;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractPlatform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Tracker\Request;

class Bing extends AbstractPlatform implements PlatformInterface
{
    const AD_CAMPAIGN_ID = 1;
    const AD_AD_GROUP_ID = 2;
    const AD_KEYWORD_ID = 3;

    /**
     * Extracts and returns advertisement platform specific data from an URL.
     * $queryParams and $paramPrefix are only passed as params for convenience reasons.
     *
     * @param string $url
     * @param array $queryParams
     * @param string $paramPrefix
     * @param Request $request
     * @return array
     */
    protected function getAdParamsFromUrl($url, array $queryParams, $paramPrefix, Request $request)
    {
        // Validate required params
        $missingParams = array_diff(
            [$paramPrefix . '_campaign_id', $paramPrefix . '_ad_group_id', $paramPrefix . '_target_id',],
            array_keys($queryParams)
        );
        if (count($missingParams)) {
            return [false, $missingParams];
        }

        $adParams = [
            'platform' => AOM::PLATFORM_BING,
            'campaignId' => $queryParams[$paramPrefix . '_campaign_id'],
            'adGroupId' => $queryParams[$paramPrefix . '_ad_group_id'],
            'targetId' => $queryParams[$paramPrefix . '_target_id'],
        ];

        return [true, $adParams];
    }

    /**
     * Retrieves contents from the given URI.
     *
     * @param $url
     * @return bool|mixed|string
     */
    public static function urlGetContents($url)
    {
        if (function_exists('curl_exec')) {
            $conn = curl_init($url);
            curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($conn, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
            $urlGetContentsData = (curl_exec($conn));
            curl_close($conn);
        } elseif (function_exists('file_get_contents')) {
            $urlGetContentsData = file_get_contents($url);
        } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
            $handle = fopen($url, "r");
            $urlGetContentsData = stream_get_contents($handle);
        } else {
            $urlGetContentsData = false;
        }
        return $urlGetContentsData;
    }

    /**
     * Retrieves contents from the given URI via POST.
     *
     * @param string $url
     * @param $fields
     * @return mixed
     */
    public static function urlPostContents($url, $fields)
    {
    	$fields = (is_array($fields)) ? http_build_query($fields) : $fields;

        $conn = curl_init($url);
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($conn, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($conn, CURLOPT_HTTPHEADER, [
            'Content-type: application/x-www-form-urlencoded',
            'Content-length: ' . strlen($fields)
        ]);
        curl_setopt($conn, CURLOPT_POST, 1);
        curl_setopt($conn, CURLOPT_POSTFIELDS, $fields);
        $urlPostContentsData = (curl_exec($conn));
        curl_close($conn);

        return $urlPostContentsData;
    }

    /**
     * Activates sub tables for the marketing performance report in the Piwik UI for Criteo.
     *
     * @return MarketingPerformanceSubTables
     */
    public function getMarketingPerformanceSubTables()
    {
        return new MarketingPerformanceSubTables();
    }
    
    /**
     * Returns a platform-specific description of a specific visit optimized for being read by humans or false when no
     * platform-specific description is available.
     *
     * @param int $idVisit
     * @return string|false
     */
    public static function getHumanReadableDescriptionForVisit($idVisit)
    {
        $visit = Db::fetchRow(
            'SELECT
                idsite,
                platform_data,
                cost
             FROM ' . Common::prefixTable('aom_visits') . '
             WHERE piwik_idvisit = ?',
            [
                $idVisit,
            ]
        );

        if ($visit) {

            $formatter = new Formatter();

            $platformData = json_decode($visit['platform_data'], true);

            if (is_array($platformData)
                && array_key_exists('account', $platformData) && array_key_exists('campaign', $platformData)
                && array_key_exists('adGroup', $platformData))
            {
                return Piwik::translate(
                    'AOM_Platform_VisitDescription_Bing',
                    [
                        $formatter->getPrettyMoney($visit['cost'], $visit['idsite']),
                        $platformData['account'],
                        $platformData['campaign'],
                        $platformData['adGroup'],
                        $platformData['keywordPlacement'],
                    ]
                );
            } else {
                return Piwik::translate('AOM_Platform_VisitDescription_Bing_Incomplete');
            }
        }

        return false;
    }
}
