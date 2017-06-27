<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Taboola;

use Piwik\Plugins\AOM\AOM;

class OldMerger extends \Piwik\Plugins\AOM\Platforms\Merger
{
    /**
     * @param array $adData
     * @return string
     */
    protected function buildKeyFromAdData(array $adData)
    {
        return implode('-', [$adData['idsite'], $adData['date'], $adData['campaign_id'], $adData['site_id'],]);
    }

    /**
     * @param array $visit
     * @return array
     */
    protected function getIdsFromVisit(array $visit)
    {
        $date = substr(AOM::convertUTCToLocalDateTime($visit['visit_first_action_time'], $visit['idsite']), 0, 10);
        $adParams = @json_decode($visit['aom_ad_params'], true);
        $campaignId = isset($adParams['campaignId']) ? $adParams['campaignId'] : null;
        $siteId = isset($adParams['siteId']) ? $adParams['siteId'] : null;

        return [$visit['idsite'], $date, $campaignId, $siteId];
    }

    /**
     * @param array $visit
     * @return null|string
     */
    protected function buildKeyFromVisit($visit)
    {
        list($idsite, $date, $campaignId, $siteId) = $this->getIdsFromVisit($visit);
        if (!$campaignId || !$siteId) {
            return null;
        }

        return implode('-', [$idsite, $date, $campaignId, $siteId,]);
    }

    public function merge()
    {
        $this->logger->info('Will merge Taboola now.');

        $adDataMap = $this->getAdData();

        // Update visits
        $updateStatements = [];
        foreach ($this->getVisits() as $visit) {
            $updateMap = null;

            $key = $this->buildKeyFromVisit($visit);
            if (isset($adDataMap[$key])) {
                // Set aom_ad_data
                $updateMap = [
                    'aom_ad_data' => json_encode($adDataMap[$key]),
                    'aom_platform_row_id' => $adDataMap[$key]['id']
                ];
            } else {
                // Search for historical data
                list($idsite, $date, $campaignId, $siteId) = $this->getIdsFromVisit($visit);
                list($rowId, $data) = Taboola::getAdData($idsite, $date, $campaignId, $siteId);

                if ($data) {
                    $updateMap = [
                        'aom_ad_data' => json_encode($data),
                        'aom_platform_row_id' => $rowId
                    ];
                } elseif ($visit['aom_platform_row_id'] || $visit['aom_ad_data']) {

                    // Unset aom_ad_data
                    $updateMap = [
                        'aom_ad_data' => 'null',
                        'aom_platform_row_id' => 'null'
                    ];
                }
            }
            if ($updateMap) {
                $updateStatements[] = [$visit['idvisit'], $updateMap];
            }
        }

        $this->updateVisits($updateStatements);

        $this->logger->info('Merged data.');
    }
}
