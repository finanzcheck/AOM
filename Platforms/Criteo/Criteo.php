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
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractPlatform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Tracker\Request;

class Criteo extends AbstractPlatform implements PlatformInterface
{
    /**
     * Extracts and returns advertisement platform specific data from an URL.
     * $queryParams and $paramPrefix are only passed as params for convenience reasons.
     *
     * @param string $url
     * @param array $queryParams
     * @param string $paramPrefix
     * @param Request $request
     * @return array|null
     */
    protected function getAdParamsFromUrl($url, array $queryParams, $paramPrefix, Request $request)
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
    public function getAdDataFromAdParams($idsite, array $adParams, $date = null)
    {
        if(!$date) {
            $date = date('Y-m-d');
        }
        return $this::getAdData($idsite, $date, $adParams['campaignId']);
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
            'SELECT * FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_CRITEO) . '
                WHERE idsite = ? AND date = ? AND campaign_id = ?',
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
            'SELECT * FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_CRITEO)
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

        if ($visit && $visit['platform_data'] && $visit['cost']) {

            $formatter = new Formatter();

            $platformData = json_decode($visit['platform_data'], true);
            
            return Piwik::translate(
                'AOM_Platform_VisitDescription_Criteo',
                [
                    $formatter->getPrettyMoney($visit['cost'], $visit['idsite']),
                    $platformData['campaign'],
                ]
            );
        }

        return false;
    }
}
