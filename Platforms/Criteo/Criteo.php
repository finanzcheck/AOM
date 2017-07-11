<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Piwik\Common;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractPlatform;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
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
     * @return array
     */
    protected function getAdParamsFromUrl($url, array $queryParams, $paramPrefix, Request $request)
    {
        // Validate required params
        $missingParams = array_diff([$paramPrefix . '_campaign_id',], array_keys($queryParams));
        if (count($missingParams)) {
            return [false, $missingParams];
        }

        return [
            true,
            [
                'platform' => AOM::PLATFORM_CRITEO,
                'campaignId' => $queryParams[$paramPrefix . '_campaign_id'],
            ]
        ];
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

            if (is_array($platformData) && array_key_exists('campaign', $platformData)) {
                return Piwik::translate(
                    'AOM_Platform_VisitDescription_Criteo',
                    [
                        $formatter->getPrettyMoney($visit['cost'], $visit['idsite']),
                        $platformData['campaign'],
                    ]
                );
            } else {
                return Piwik::translate('AOM_Platform_VisitDescription_Criteo_Incomplete');
            }
        }

        return false;
    }
}
