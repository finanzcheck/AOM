<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\MergerInterface;

class Merger extends \Piwik\Plugins\AOM\Platforms\Merger implements MergerInterface
{
    protected function buildKeyFromAdData($adData)
    {
        return "{$adData['idsite']}-{$adData['date']}-{$adData['campaign_id']}";
    }

    protected function buildKeyFromVisitor($visitor)
    {
        $date = substr($visitor['visit_first_action_time'], 0, 10);
        $adParams = @json_decode($visitor['aom_ad_params']);
        if(!isset($adParams->campaignId)){
            return null;
        }
        return "{$visitor['idsite']}-{$date}-{$adParams->campaignId}";
    }

    public function merge($startDate, $endDate)
    {
        // Get all relevant visits
        $visits = DB::fetchAll(
            'SELECT * FROM  ' . Common::prefixTable(
                'log_visit'
            ) . '  WHERE visit_first_action_time >= ? AND visit_first_action_time <= ? AND aom_platform = "?"',
            [$startDate, $endDate, AOM::PLATFORM_CRITEO]
        );

        // Get all relevant ad data
        $result = DB::fetchAll(
            'SELECT * FROM ' . Criteo::getDataTableName() . ' WHERE date >= ? AND date <= ?',
            [$startDate, $endDate]
        );

        $adDataMap = [];
        foreach ($result as $row) {
            $adDataMap[$this->buildKeyFromAdData($row)] = $row;
        }

        // Update visits
        $updateStatements = [];
        foreach ($visits as $visit) {
            $key = $this->buildKeyFromVisitor($visit);
            if (isset($adDataMap[$key])) {
                // Set aom_ad_data
                $data = json_encode($adDataMap[$key]);
                $updateStatements[] = 'UPDATE ' . Common::prefixTable(
                        'log_visit'
                    ) . ' SET aom_ad_data = \'' . $data . '\', aom_platform_row_id = ' . $adDataMap[$key]['id'] .
                    ' WHERE idvisit = ' . $visit['idvisit'];
            } elseif ($visit['aom_platform_row_id'] || $visit['aom_ad_data']) {
                // Unset aom_ad_data
                $updateStatements[] = 'UPDATE ' . Common::prefixTable(
                        'log_visit'
                    ) . ' SET aom_ad_data = null, aom_platform_row_id = null' .
                    ' WHERE idvisit = ' . $visit['idvisit'];
            }
        }

        //TODO: Use only one statement
        foreach ($updateStatements as $statement) {
            DB::exec($statement);
        }

    }
}
