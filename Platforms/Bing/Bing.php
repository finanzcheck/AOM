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
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class Bing extends Platform implements PlatformInterface
{
    const AD_CAMPAIGN_ID = 1;
    const AD_AD_GROUP_ID = 2;
    const AD_KEYWORD_ID = 3;

    /**
     * Extracts advertisement platform specific data from the query params and validates it.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdParamsFromQueryParams($paramPrefix, array $queryParams)
    {
        // Validate required params
        $missingParams = array_diff(
            [
                $paramPrefix . '_campaign_id',
                $paramPrefix . '_ad_group_id',
                $paramPrefix . '_order_item_id',
                $paramPrefix . '_target_id',
            ],
            array_keys($queryParams)
        );
        if (count($missingParams)) {
            $this->getLogger()->warning(
                'Visit with platform ' . AOM::PLATFORM_BING . ' without required param/s: '
                . implode(', ', $missingParams)
            );

            return null;
        }

        $adParams = [
            'platform' => AOM::PLATFORM_BING,
            'campaignId' => $queryParams[$paramPrefix . '_campaign_id'],
            'adGroupId' => $queryParams[$paramPrefix . '_ad_group_id'],
            'orderItemId' => $queryParams[$paramPrefix . '_order_item_id'],
            'targetId' => $queryParams[$paramPrefix . '_target_id'],
        ];

        // Add optional params
        if (array_key_exists($paramPrefix . '_ad_id', $queryParams)) {
            $adParams['adId'] = $queryParams[$paramPrefix . '_ad_id'];
        }

        return $adParams;
    }

    /**
     * @inheritdoc
     */
    public function getAdDataFromAdParams($idsite, array $adParams, $date = null)
    {
        if(!$date) {
            $date = date('Y-m-d');
        }

        $data = $this->getAdData($idsite, $date, $adParams);
        if(!$data[0]) {
            $data = [null, $this::getHistoricalAdData($idsite, $adParams['campaignId'], $adParams['adGroupId'])];
        }
        return $data;
    }

    /**
     * Searches for matching ad data.
     *
     * @param $idsite
     * @param $date
     * @param $adParams
     * @return array|null
     * @throws \Exception
     */
    public static function getAdData($idsite, $date, $adParams)
    {
        $targetId = $adParams['targetId'];
        if (strpos($adParams['targetId'], 'kwd-') !== false) {
            $targetId = substr($adParams['targetId'], strpos($adParams['targetId'], 'kwd-') + 4);
        }

        $result = DB::fetchAll(
            'SELECT * FROM ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING) . '
                WHERE idsite = ? AND date = ? AND campaign_id = ? AND ad_group_id = ? AND keyword_id = ?',
            [
                $idsite,
                $date,
                $adParams['campaignId'],
                $adParams['adGroupId'],
                $targetId
            ]
        );

        if (count($result) > 1) {
            throw new \Exception('Found more than one match for exact match.');
        } elseif (count($result) == 0) {
            return null;
        }

        return [$result[0]['id'], $result[0]];
    }

    /**
     * Searches for historical ad data.
     *
     * @param $idsite
     * @param $campaignId
     * @param $adGroupId
     * @return array|null
     * @throws \Exception
     */
    public static function getHistoricalAdData($idsite, $campaignId, $adGroupId)
    {
        $result = DB::fetchAll(
            'SELECT * FROM ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING) . '
                WHERE idsite = ? AND campaign_id = ? AND ad_group_id = ?',
            [
                $idsite,
                $campaignId,
                $adGroupId
            ]
        );

        if (count($result) > 0) {
            // Keep generic date-independent information only
            return [
                'campaign_id' => $campaignId,
                'campaign' => $result[0]['campaign'],
                'ad_group_id' => $adGroupId,
                'ad_group' => $result[0]['ad_group'],
            ];
        }

        return null;
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
            $url_get_contents_data = (curl_exec($conn));
            curl_close($conn);
        } elseif (function_exists('file_get_contents')) {
            $url_get_contents_data = file_get_contents($url);
        } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
            $handle = fopen($url, "r");
            $url_get_contents_data = stream_get_contents($handle);
        } else {
            $url_get_contents_data = false;
        }
        return $url_get_contents_data;
    }
    
    /**
     * Retrieves contents from the given URI via POST.
     *
     * @param $url
     * @param $fields
     * @return bool|mixed|string
     */
    public static function urlPostContents($url, $fields)
    {
    	$fields = (is_array($fields)) ? http_build_query($fields) : $fields;
        if (function_exists('curl_exec')) {
        $headers = array( 
            "Content-type: application/x-www-form-urlencoded",
            "Content-length: ".strlen($fields)
        ); 
            $conn = curl_init($url);
            curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($conn, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($conn, CURLOPT_POST, 1);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $fields);
            $url_post_contents_data = (curl_exec($conn));
            curl_close($conn);
        } else {
        	throw new \Exception('This plugin requires php-curl to be enabled. Please install and restart your web server.');
            $url_post_contents_data = false;
        }
        return $url_post_contents_data;
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

            return Piwik::translate(
                'AOM_Platform_VisitDescription_Bing',
                [
                    $formatter->getPrettyMoney($visit['cost'], $visit['idsite']),
                    $platformData['account'],
                    $platformData['campaign'],
                    $platformData['ad_group'],
                ]
            );
        }

        return false;
    }
}
