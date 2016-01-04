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
    /**
     * @param array $adData
     * @return string
     */
    protected function buildKeyFromAdData(array $adData)
    {
        return "{$adData['idsite']}-{$adData['date']}-{$adData['campaign_id']}";
    }

    /**
     * @param array $visit
     * @return array
     */
    protected function getIdsFromVisit(array $visit)
    {
        $date = substr($visit['visit_first_action_time'], 0, 10);
        $adParams = @json_decode($visit['aom_ad_params']);
        $campaignId = null;
        if (isset($adParams->campaignId)) {
            $campaignId = $adParams->campaignId;
        }

        return [$visit['idsite'], $date, $campaignId];
    }

    /**
     * @param array $visit
     * @return null|string
     */
    protected function buildKeyFromVisit($visit)
    {
        list($idsite, $date, $campaignId) = $this->getIdsFromVisit($visit);
        if (!$campaignId) {
            return null;
        }

        return "{$idsite}-{$date}-{$campaignId}";
    }

    public function merge()
    {
        // Get all relevant visits
        // TODO: Convert local datetime into UTC before querying visits (by iterating website for website?)
        // TODO: Example AOM::convertLocalDateTimeToUTC($this->startDate, Site::getTimezoneFor($idsite))
        // TODO: The example returns 2015-12-19 23:00:00 for startDate 2015-12-20 00:00:00 for Europe/Berlin.
        // We assume that the website's timezone matches the timezone of all advertising platforms.
        $visits = DB::fetchAll(
            'SELECT * FROM  ' . Common::prefixTable('log_visit')
                . '  WHERE visit_first_action_time >= ? AND visit_first_action_time <= ? AND aom_platform = ?',
            [
                $this->startDate,
                $this->endDate,
                AOM::PLATFORM_CRITEO,
            ]
        );

        // Get all relevant ad data
        $result = DB::fetchAll(
            'SELECT * FROM ' . Criteo::getDataTableName() . ' WHERE date >= ? AND date <= ?',
            [
                $this->startDate,
                $this->endDate,
            ]
        );

        $adDataMap = [];
        foreach ($result as $row) {
            $adDataMap[$this->buildKeyFromAdData($row)] = $row;
        }

        // Update visits
        $updateStatements = [];
        foreach ($visits as $visit) {
            $data = null;

            $key = $this->buildKeyFromVisit($visit);
            if (isset($adDataMap[$key])) {
                // Set aom_ad_data
                $updateStatements[] = 'UPDATE ' . Common::prefixTable(
                        'log_visit'
                    ) . ' SET aom_ad_data = \'' . json_encode(
                        $adDataMap[$key]
                    ) . '\', aom_platform_row_id = ' . $adDataMap[$key]['id'] .
                    ' WHERE idvisit = ' . $visit['idvisit'];
            } else {

                // Search for historical data
                list($idsite, $date, $campaignId) = $this->getIdsFromVisit($visit);
                $data = Criteo::getAdData($idsite, $date, $campaignId);
                if ($data) {

                    $updateStatements[] = 'UPDATE ' . Common::prefixTable(
                            'log_visit'
                        ) . ' SET aom_ad_data = \'' . json_encode($data) . '\'' .
                        ' WHERE idvisit = ' . $visit['idvisit'];
                } elseif ($visit['aom_platform_row_id'] || $visit['aom_ad_data']) {

                    // Unset aom_ad_data
                    $updateStatements[] = 'UPDATE ' . Common::prefixTable(
                            'log_visit'
                        ) . ' SET aom_ad_data = null, aom_platform_row_id = null' .
                        ' WHERE idvisit = ' . $visit['idvisit'];
                }
            }
        }

        // TODO: Use only one statement
        foreach ($updateStatements as $statement) {
            DB::exec($statement);
        }

        $this->logger->info('Merged data.');
    }
}
