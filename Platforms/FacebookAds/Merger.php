<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\MergerInterface;
use Piwik\Site;

/*
Query for single keyword
select count(*), date, campaign_id, adset_id, ad_id from piwik_aom_facebookads group by date, campaign_id, adset_id, ad_id  order by count(*) desc
 */


class Merger extends \Piwik\Plugins\AOM\Platforms\Merger implements MergerInterface
{
    /**
     * @param array $adData
     * @return string
     */
    protected function buildKeyFromAdData(array $adData)
    {
        return implode('-', [
            $adData['idsite'],
            $adData['date'],
            $adData['campaign_id'],
            $adData['adset_id'],
            $adData['ad_id'],
        ]);
    }

    /**
     * @param array $visit
     * @return array
     */
    protected function getIdsFromVisit(array $visit)
    {
        $ids = [];
        $ids['date'] = substr(AOM::convertUTCToLocalDateTime($visit['visit_first_action_time'], $visit['idsite']), 0, 10);
        $ids['idsite'] = $visit['idsite'];
        $adParams = @json_decode($visit['aom_ad_params']);
        $ids['campaignId'] = isset($adParams->campaignId) ? $adParams->campaignId : null;
        $ids['adsetId'] = isset($adParams->adsetId) ? $adParams->adsetId : null;
        $ids['adId'] = isset($adParams->adId) ? $adParams->adId : null;

        return $ids;
    }

    /**
     * @param array $visit
     * @return null|string
     */
    protected function buildKeyFromVisit($visit)
    {
        $ids = $this->getIdsFromVisit($visit);

        return implode('-', [
            $ids['idsite'],
            $ids['date'],
            $ids['campaignId'],
            $ids['adsetId'],
            $ids['adId'],
        ]);
    }

    public function merge()
    {
        $this->logger->info('Will merge FacebookAds now.');

        $platformData = $this->getPlatformData();

        $adDataMap = [];
        foreach ($platformData as $row) {
            //TODO: Duplicate filter
            $adDataMap[$this->buildKeyFromAdData($row)] = $row;
        }

        // Update visits
        $updateStatements = [];
        $nonMatchedVisits = [];
        foreach ($this->getVisits() as $visit) {
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
            $data = FacebookAds::getHistoricalAdData($visit['idsite'], $ids['campaignId'], $ids['adsetId']);

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

        $this->logger->info(
            'Merged data (' . count($nonMatchedVisits) . ' without direct match out of ' . count($this->getVisits()) . ')'
        );
    }
}
