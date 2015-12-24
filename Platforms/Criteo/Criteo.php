<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class Criteo extends Platform implements PlatformInterface
{
    /**
     * Enriches a specific visit with additional Criteo information when this visit came from Criteo.
     *
     * @param array &$visit
     * @param array $adData
     * @return array
     * @throws \Exception
     */
    public function enrichVisit(array &$visit, array $adData)
    {
        $sql = 'SELECT
                    campaign_id AS campaignId,
                    campaign,
                    (cost / clicks) AS cpc
                FROM ' . Common::prefixTable('aom_criteo') . '
                WHERE
                    date = ? AND
                    campaign_id = ?';

        $results = Db::fetchRow(
            $sql,
            [
                date('Y-m-d', strtotime($visit['firstActionTime'])),
                $adData['campaignId'],
            ]
        );

        $visit['adData'] = array_merge($adData, ($results ? $results : []));

        return $visit;
    }

    /**
     * Extracts advertisement platform specific data from the query params.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdDataFromQueryParams($paramPrefix, array $queryParams)
    {
        $adData = [
            'platform' => 'Criteo',
        ];

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adData['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        } else {
            $adData['campaignId'] = null;
        }

        return $adData;
    }

    /**
     * Builds a string key from the ad data to reference explicit platform data.
     * This key is only built when all required ad data is available. It is being stored in piwik_log_visit.aom_ad_key.
     *
     * @param array $adData
     * @return mixed
     */
    public function getAdKeyFromAdData(array $adData)
    {
        if (array_key_exists('campaignId', $adData)) {
            return 'criteo' . '-' . $adData['campaignId'];
        }

        return null;
    }
}
