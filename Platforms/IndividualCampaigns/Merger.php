<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\IndividualCampaigns;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractMerger;
use Piwik\Plugins\AOM\Platforms\MergerInterface;
use Piwik\Plugins\AOM\Platforms\MergerPlatformDataOfVisit;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Plugins\AOM\Services\PiwikVisitService;
use Piwik\Site;

class Merger extends AbstractMerger implements MergerInterface
{
    public function merge()
    {
        $this->logger->info('Will sync deleted and added individual campaigns independent of date range now.');

        $dates = [];

        array_merge ($dates, $this->syncDeletedIndividualCampaigns());
        array_merge ($dates, $this->syncAddedIndividualCampaigns());

        // Validate merge results
        foreach (array_unique($dates) as $date) {
            $this->validateMergeResults(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS, $date);
        }
    }

    /**
     * Find out which individual campaigns have been deleted (ordered by date ASC).
     * The visits that resulted from this individual campaign need to be updated.
     *
     * @return array
     */
    private function syncDeletedIndividualCampaigns()
    {
        $dates = [];

        $unsyncedIndividualCampaignRows = Db::fetchAll(
            'SELECT id, idsite, date, campaign_id '
                . ' FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                . ' WHERE obsolete = 1 AND synced = 0 ORDER BY date ASC'
        );

        $this->logger->debug(
            'Got ' . count($unsyncedIndividualCampaignRows) . ' individual campaign record'
                . (1 != count($unsyncedIndividualCampaignRows) ? 's' : '') . ' to delete.'
        );

        foreach ($unsyncedIndividualCampaignRows as $unsyncedIndividualCampaignRow) {

            // Clean-up regular visits table
            // (there might be processed and unprocessed visits in log_visit associated with the campaign to be deleted)
            $result = Db::query(
                'UPDATE ' . Common::prefixTable('log_visit')
                    . ' SET aom_platform = NULL, aom_ad_params = NULL '
                    . ' WHERE idsite = ? AND aom_platform = ? AND aom_ad_params LIKE CONCAT("%",?,"%")',
                [
                    $unsyncedIndividualCampaignRow['idsite'],
                    AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS,
                    $unsyncedIndividualCampaignRow['campaign_id'],
                ]
            );

            if ($result->rowCount() > 0) {
                $this->logger->debug(
                    'Unset ' . $result->rowCount() . ' record' . (1 != $result->rowCount() ? 's' : '') . ' in log_visit'
                        . ' due to the deletion of campaign ID ' . $unsyncedIndividualCampaignRow['campaign_id'] . '.'
                );
            }

            // We do not need to care about unmerged visits in aom_visits.
            // Merged visits will have the correct platform key.

            // TODO: Fetching affected visits for update messages and updating should happen within one transaction.

            $affectedVisits = Db::fetchAll(
                'SELECT id, piwik_idvisit FROM ' . Common::prefixTable('aom_visits')
                    . ' WHERE idsite = ? AND date_website_timezone = ? AND channel = ? AND platform_key = ?',
                [
                    $unsyncedIndividualCampaignRow['idsite'],
                    $unsyncedIndividualCampaignRow['date'],
                    AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS,
                    $unsyncedIndividualCampaignRow['campaign_id'],
                ]
            );

            if (count($affectedVisits) > 0) {

                foreach ($affectedVisits as $affectedVisit) {

                    // Identify original channel (direct, website, seo, campaign...)
                    $originalChannel = ($affectedVisit['piwik_idvisit']
                        ? Db::fetchOne(
                            'SELECT CASE referer_type
                                    WHEN 1 THEN "direct"
                                    WHEN 2 THEN "search_engine"
                                    WHEN 3 THEN "website"
                                    WHEN 6 THEN "campaign"
                                    ELSE ""
                             END AS source FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?',
                            [$affectedVisit['piwik_idvisit']]
                        )
                        : null);

                    Db::query(
                        'UPDATE ' . Common::prefixTable('aom_visits')
                            . ' SET channel = ?, platform_key = NULL, platform_data = "null", cost = NULL, '
                            . ' ts_last_update = NOW() WHERE id = ?',
                        [$originalChannel, $affectedVisit['id'],]
                    );
                }

                $this->logger->debug(
                    'Updated ' . count($affectedVisits) . ' visit' . (1 != count($affectedVisits) ? 's' : '')
                        . ' for date ' . $unsyncedIndividualCampaignRow['date'] . ' due to the deletion of campaign '
                        . $unsyncedIndividualCampaignRow['campaign_id']  . '.');

                // Publish event for every single update
                foreach ($affectedVisits as $affectedVisit) {
                    PiwikVisitService::postAomVisitAddedOrUpdatedEvent($affectedVisit['id']);
                }
            }

            // As we successfully synced an obsolete individual campaign row, we can delete that row now.
            Db::query(
                'DELETE FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                    . ' WHERE id = ?',
                [$unsyncedIndividualCampaignRow['id'],]
            );

            $dates[] = $unsyncedIndividualCampaignRow['date'];
        }

        return $dates;
    }

    /**
     * Find out which individual campaigns have been added (ordered by date ASC).
     * The visits resulting from an individual campaign need to be linked to it (and costs need to be allocated).
     *
     * @return array
     */
    public function syncAddedIndividualCampaigns()
    {
        $dates = [];

        $unsyncedIndividualCampaignRows = Db::fetchAll(
            'SELECT * '
                . ' FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                . ' WHERE obsolete = 0 AND synced = 0 ORDER BY date ASC'
        );

        $this->logger->debug(
            'Got ' . count($unsyncedIndividualCampaignRows) . ' individual campaign record'
                . (1 != count($unsyncedIndividualCampaignRows) ? 's' : '') . ' to add.'
        );

        foreach ($unsyncedIndividualCampaignRows as $unsyncedIndividualCampaignRow) {

            $date = $unsyncedIndividualCampaignRow['date'];
            $idSite = $unsyncedIndividualCampaignRow['idsite'];#
            $platformData = [
                'platform' => AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS,
                'campaignId' => $unsyncedIndividualCampaignRow['campaign_id'],
                'campaignName' => $unsyncedIndividualCampaignRow['campaign'],
            ];

            // Update visits in log_visit that match the campaign's specification
            // TODO: Add index idsite + visit_first_action_time?
            $result = Db::query(
                'UPDATE ' . Common::prefixTable('log_visit') . ' AS v '
                    . ' LEFT JOIN piwik_log_action AS a ON v.visit_entry_idaction_url = a.idaction '
                    . ' SET v.aom_platform = ?, v.aom_ad_params = ? '
                    . ' WHERE v.idsite = ? '
                    . '     AND v.visit_first_action_time >= ? AND v.visit_first_action_time <= ? '
                    . '     AND v.aom_platform IS NULL '    // We do not want to overwrite something like AdWords
                    . '     AND ((? <> \'\' AND a.name LIKE CONCAT("%",?,"%")) '
                    . '         OR (? <> \'\' AND v.referer_url LIKE CONCAT("%",?,"%")))',
                [
                    AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS,
                    json_encode($platformData),
                    $idSite,
                    AOM::convertLocalDateTimeToUTC(($date . ' 00:00:00'), Site::getTimezoneFor($idSite)),
                    AOM::convertLocalDateTimeToUTC(($date . ' 23:59:59'), Site::getTimezoneFor($idSite)),
                    $unsyncedIndividualCampaignRow['params_substring'],
                    $unsyncedIndividualCampaignRow['params_substring'],
                    $unsyncedIndividualCampaignRow['referrer_substring'],
                    $unsyncedIndividualCampaignRow['referrer_substring'],
                ]
            );

            if ($result->rowCount() > 0) {
                $this->logger->debug(
                    'Updated ' . $result->rowCount() . ' record' . (1 != $result->rowCount() ? 's' : '')
                        . ' in log_visit at date ' . $date . ' to individual '
                        . ' campaign ID ' . $unsyncedIndividualCampaignRow['campaign_id'] . '.'
                );
            }

            // Get updated visits from log_visit to update visits in aom_visits
            Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits')
                    . ' SET channel = ?, platform_key = ?, platform_data = ? '
                    . ' WHERE piwik_idvisit IN ('
                    . '     SELECT idvisit FROM ' . Common::prefixTable('log_visit')
                    . '     WHERE idsite = ? AND visit_first_action_time >= ? AND visit_first_action_time <= ? '
                    . '     AND aom_ad_params LIKE CONCAT("%",?,"%"))',
                [
                    AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS,
                    $unsyncedIndividualCampaignRow['campaign_id'],
                    json_encode($platformData),
                    $idSite,
                    AOM::convertLocalDateTimeToUTC(($date . ' 00:00:00'), Site::getTimezoneFor($idSite)),
                    AOM::convertLocalDateTimeToUTC(($date . ' 23:59:59'), Site::getTimezoneFor($idSite)),
                    $unsyncedIndividualCampaignRow['campaign_id'],
                ]
            );

            // Distribute cost (this will create an artificial visit if no visit could be found in aom_visits)
            $this->allocateCostOfPlatformRow(
                AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS,
                $unsyncedIndividualCampaignRow,
                $unsyncedIndividualCampaignRow['campaign_id'],
                $platformData
            );

            Db::query(
                'UPDATE ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                    . ' SET synced = 1 WHERE id = ?',
                [$unsyncedIndividualCampaignRow['id'],]
            );

            $dates[] = $unsyncedIndividualCampaignRow['date'];
        }

        return $dates;
    }

    public function getPlatformDataOfVisit($idsite, $date, $idvisit, array $aomAdParams)
    {
        $mergerPlatformDataOfVisit = new MergerPlatformDataOfVisit(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS);

        $mergerPlatformDataOfVisit->setPlatformKey($aomAdParams['campaignId']);

        // Get the exactly matching platform row
        $platformRow = $this->getExactMatchPlatformRow($idsite, $date, $aomAdParams['campaignId']);
        if (!$platformRow) {
            return $mergerPlatformDataOfVisit->setPlatformData(['campaignId' => $aomAdParams['campaignId']]);
        }

        // Exact match
        return $mergerPlatformDataOfVisit
            ->setPlatformData(array_merge(
                ['campaignId' => (string) $aomAdParams['campaignId']],
                ['campaign' => $platformRow['campaign']]
            ))
            ->setPlatformRowId($platformRow['platformRowId']);
    }

    /**
     * Returns platform data when a match is found. False otherwise.
     *
     * TODO: Imported data should also create platform_key which would make querying easier.
     *
     * @param int $idsite
     * @param string $date
     * @param string $campaignId
     * @return array|bool
     */
    private function getExactMatchPlatformRow($idsite, $date, $campaignId)
    {
        $result = Db::fetchRow(
            'SELECT id AS platformRowId, campaign '
                . ' FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                . ' WHERE idsite = ? AND date = ? AND campaign_id = ?',
            [$idsite, $date, $campaignId,]
        );

        if ($result) {
            $this->logger->debug(
                'Found exact match platform row ID ' . $result['platformRowId'] . ' in individual campaigns for visit.'
            );
        } else {
            $this->logger->debug('Could not find exact match in individual campaigns for visit.');
        }

        return $result;
    }
}
