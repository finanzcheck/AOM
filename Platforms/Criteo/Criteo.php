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
    public static function getDataTableNameStatic()
    {
        return Common::prefixTable('aom_' . strtolower(AOM::PLATFORM_CRITEO));
    }

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
        $missingParams = array_diff([$paramPrefix . '_campaign_id',], array_keys($queryParams));
        if (count($missingParams)) {
            $this->getLogger()->warning(
                'Visit with platform ' . AOM::PLATFORM_CRITEO . ' without required param/s: '
                . implode(', ', $missingParams)
            );

            return null;
        }

        return [
            'platform' => AOM::PLATFORM_CRITEO,
            'campaignId' => $queryParams[$paramPrefix . '_campaign_id'],
        ];
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
            'SELECT * FROM ' . Criteo::getDataTableNameStatic() . ' WHERE idsite = ? AND date = ? AND campaign_id = ?',
            [
                $idsite,
                $date,
                $campaignId,
            ]
        );
        if (count($result) > 1) {
            throw new \Exception('Found more than one match for exact match.');
        } elseif (1 === count($result)) {
            return [$result[0]['id'], $result[0]];
        }

        // No exact match found; search for historic data
        $result = DB::fetchAll(
            'SELECT * FROM ' . Criteo::getDataTableNameStatic()
                . ' WHERE idsite = ? AND campaign_id = ? ORDER BY date DESC LIMIT 1',
            [
                $idsite,
                $campaignId
            ]
        );
        if (count($result) > 0) {
            // Keep generic date-independent information only
            return [
                null,
                [
                    'campaign_id' => $campaignId,
                    'campaign' => $result[0]['campaign'],
                ]
            ];
        }

        return [null, null];
    }
}
