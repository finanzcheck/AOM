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
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\Platform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class Criteo extends Platform implements PlatformInterface
{
    /**
     * Returns the platform's data table name.
     */
    public static function getDataTableName()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_CRITEO));
    }

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
                FROM ' . self::getDataTableName() . '
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
            'platform' => AOM::PLATFORM_CRITEO,
        ];

        if (array_key_exists($paramPrefix . '_campaign_id', $queryParams)) {
            $adParams['campaignId'] = $queryParams[$paramPrefix . '_campaign_id'];
        } else {
            $adParams['campaignId'] = null;
        }

        return $adParams;
    }

    /**
     * @inheritdoc
     */
    public function getAdDataFromAdParams($idsite, array $adParams)
    {
        return $this::getAdData($idsite, date('Y-m-d'), $adParams['campaignId']);
    }

    /**
     * @param int $idsite
     * @param string $date
     * @param int $campaignId
     * @return array|null
     * @throws Exception
     */
    public static function getAdData($idsite, $date, $campaignId)
    {
        // Exact match
        $result = DB::fetchAll(
            'SELECT * FROM ' . Criteo::getDataTableName() . ' WHERE idsite = ? AND date = ? AND campaign_id = ?',
            [
                $idsite,
                $date,
                $campaignId,
            ]
        );
        if (count($result) > 1) {
            throw new \Exception('Found more than one match for exact match.');
        } elseif (1 === count($result)) {
            return $result[0];
        }

        // No exact match found; search for historic data
        $result = DB::fetchAll(
            'SELECT * FROM ' . Criteo::getDataTableName()
                . ' WHERE idsite = ? AND campaign_id = ? ORDER BY date DESC LIMIT 1',
            [
                $idsite,
                $campaignId
            ]
        );
        if (count($result) > 0) {

            // Keep generic date-independent information only
            return [
                'campaign_id' => $campaignId,
                'campaign' => $result[0]['campaign'],
            ];
        }

        return null;
    }
}
