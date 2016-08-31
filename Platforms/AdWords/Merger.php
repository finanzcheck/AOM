<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Monolog\Logger;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\MergerInterface;

class Merger extends \Piwik\Plugins\AOM\Platforms\Merger implements MergerInterface
{
    /**
     * Build a unique key from the platform's ad data.
     *
     * @param array $adData
     * @return string
     */
    protected function buildKeyFromAdData(array $adData)
    {
        // We must aggregate up all placements of an ad group and merge on that level.
        if ($adData['network'] == 'd') {
            return implode('-', [
                $adData['network'],
                $adData['idsite'],
                $adData['date'],
                $adData['campaign_id'],
                $adData['ad_group_id']
            ]);
        }

        return implode('-', [
            $adData['network'],
            $adData['idsite'],
            $adData['date'],
            $adData['campaign_id'],
            $adData['ad_group_id'],
            $adData['keyword_id'],
        ]);
    }

    /**
     * @param array $visit
     * @return array
     */
    protected function getIdsFromVisit(array $visit)
    {
        $ids = [];
        $ids['date'] = substr(
            AOM::convertUTCToLocalDateTime($visit['visit_first_action_time'], $visit['idsite']),
            0,
            10
        );
        $ids['idsite'] = $visit['idsite'];

        $adParams = @json_decode($visit['aom_ad_params']);
        $ids['campaign_id'] = isset($adParams->campaignId) ? $adParams->campaignId : null;
        $ids['ad_group_id'] = isset($adParams->adGroupId) ? $adParams->adGroupId : null;
        $ids['network'] = isset($adParams->network) ? $adParams->network : null;
        $ids['keyword_id'] = null;
        if (isset($adParams->targetId)) {
            if (strpos($adParams->targetId, 'kwd-') !== false) {
                $ids['keyword_id'] = substr($adParams->targetId, strpos($adParams->targetId, 'kwd-') + 4);
            }
        }

        return $ids;
    }

    /**
     * @param array $visit
     * @return null|string
     */
    protected function buildKeyFromVisit($visit)
    {
        $ids = $this->getIdsFromVisit($visit);

        if ($ids['network'] == 'd') {
            return implode('-', [
                $ids['network'],
                $ids['idsite'],
                $ids['date'],
                $ids['campaign_id'],
                $ids['ad_group_id']
            ]);
        }

        return implode('-', [
            $ids['network'],
            $ids['idsite'],
            $ids['date'],
            $ids['campaign_id'],
            $ids['ad_group_id'],
            $ids['keyword_id'],
        ]);
    }

    public function merge()
    {
        $this->log(Logger::INFO, 'Will merge AdWords now.');

        $adDataMap = $this->getAdData();

        $visits = $this->getVisits();

        // Update visits
        $updateStatements = [];
        $nonMatchedVisits = [];
        foreach ($visits as $visit) {
            $data = null;
            $key = $this->buildKeyFromVisit($visit);
            if (isset($adDataMap[$key])) {
                // Set aom_ad_data
                $updateMap = [
                    'aom_ad_data' => json_encode($adDataMap[$key]),
                    'aom_platform_row_id' => $adDataMap[$key]['id']
                ];
                $updateStatements[] = [$visit['idvisit'], $updateMap];
            } else {
                $nonMatchedVisits[] = $visit;
            }
        }

        // Search for historical data
        foreach ($nonMatchedVisits as $visit) {
            $ids = $this->getIdsFromVisit($visit);
            $data = AdWords::getHistoricalAdData($visit['idsite'], $ids['campaign_id'], $ids['ad_group_id']);
            if ($data) {
                $updateMap = [
                    'aom_ad_data' => json_encode($data),
                    'aom_platform_row_id' => 'null'
                ];
                $updateStatements[] = [$visit['idvisit'], $updateMap];

            } elseif ($visit['aom_platform_row_id'] || $visit['aom_ad_data']) {
                // Unset aom_ad_data
                $updateMap = [
                    'aom_ad_data' => 'null',
                    'aom_platform_row_id' => 'null'
                ];
                $updateStatements[] = [$visit['idvisit'], $updateMap];
            }
        }

        $this->updateVisits($updateStatements);

        $this->log(
            Logger::INFO,
            'Merged AdWords data (' . count($nonMatchedVisits) . ' visits without direct match out of '
                . count($visits) . ')'
        );
    }

    /**
     * Convenience function for shorter logging statements
     *
     * @param string $logLevel
     * @param string $message
     * @param array $additionalContext
     */
    private function log($logLevel, $message, $additionalContext = [])
    {
        $this->logger->log(
            $logLevel,
            $message,
            array_merge(['platform' => AOM::PLATFORM_AD_WORDS, 'task' => 'merge'], $additionalContext)
        );
    }
}
