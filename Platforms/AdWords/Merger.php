<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\MergerInterface;

/*
Query for single keyword
select count(*), date, campaign_id, ad_group_id, keyword_id from piwik_aom_adwords group by date, campaign_id, ad_group_id, keyword_id  order by count(*) desc

Query for single display
select count(*), date, campaign_id, ad_group_id, keyword_placement  from piwik_aom_adwords where network = 'd'  group by date, campaign_id, ad_group_id, keyword_placement order by count(*) DESC
*
 */


class Merger extends \Piwik\Plugins\AOM\Platforms\Merger implements MergerInterface
{
    /**
     * @param array $adData
     * @return string
     */
    protected function buildKeyFromAdData(array $adData)
    {
        if ($adData['network'] == 'd') {
            return implode('-', [
                $adData['network'],
                $adData['idsite'],
                $adData['date'],
                $adData['campaign_id'],
                $adData['ad_group_id'],
                $adData['keyword_placement'],
            ]);
        }

        return implode('-', [
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
        $ids['date'] = substr(AOM::convertUTCToLocalDateTime($visit['visit_first_action_time'], $visit['idsite']), 0, 10);
        $ids['idsite'] = $visit['idsite'];
        $adParams = @json_decode($visit['aom_ad_params']);
        $ids['campaign_id'] = isset($adParams->campaignId) ? $adParams->campaignId : null;
        $ids['ad_group_id'] = isset($adParams->adGroupId) ? $adParams->adGroupId : null;
        $ids['network'] = isset($adParams->network) ? $adParams->network : null;
        $ids['placement'] = isset($adParams->placement) ? $adParams->placement : null;
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
                $ids['ad_group_id'],
                $ids['placement'],
            ]);
        }

        return implode('-', [
            $ids['idsite'],
            $ids['date'],
            $ids['campaign_id'],
            $ids['ad_group_id'],
            $ids['keyword_id'],
        ]);
    }

    public function merge()
    {
        $this->logger->info('Will merge AdWords now.');

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

        $this->logger->info(
            'Merged data (' . count($nonMatchedVisits) . ' without direct match out of ' . count($platformData) . ')'
        );
    }
}
