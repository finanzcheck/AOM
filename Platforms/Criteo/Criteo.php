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
     * @param array $adParams
     * @return array
     * @throws \Exception
     */
    public function enrichVisit(array &$visit, array $adParams)
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
                $adParams['campaignId'],
            ]
        );

        $visit['adParams'] = array_merge($adParams, ($results ? $results : []));

        return $visit;
    }

    /**
     * Extracts advertisement platform specific data from the query params.
     *
     * @param string $paramPrefix
     * @param array $queryParams
     * @return mixed
     */
    public function getAdParamsFromQueryParams($paramPrefix, array $queryParams)
    {
        $adParams = [
            'platform' => 'Criteo',
        ];

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adParams['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        } else {
            $adParams['campaignId'] = null;
        }

        return $adParams;
    }

    /**
     * Builds a string key from the ad data to reference explicit platform data.
     * This key is only built when all required ad data is available. It is being stored in piwik_log_visit.aom_ad_key.
     *
     * @param array $adParams
     * @return mixed
     */
    public function getAdKeyFromAdParams(array $adParams)
    {
        if (array_key_exists('campaignId', $adParams)) {
            return 'criteo' . '-' . $adParams['campaignId'];
        }

        return null;
    }
}
