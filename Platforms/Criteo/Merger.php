<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractMerger;
use Piwik\Plugins\AOM\Platforms\MergerInterface;
use Piwik\Plugins\AOM\Platforms\MergerPlatformDataOfVisit;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;

class Merger extends AbstractMerger implements MergerInterface
{
    public function merge()
    {
        foreach (AOM::getPeriodAsArrayOfDates($this->startDate, $this->endDate) as $date) {

            // TODO: Do not merge if there are no processed real visits yet?

            foreach ($this->getPlatformRows(AOM::PLATFORM_CRITEO, $date) as $platformRow) {

                $platformKey = $platformRow['campaign_id'];
                $platformData = [
                    'campaignId' => (string) $platformRow['campaign_id'],
                    'campaign' => $platformRow['campaign'],
                ];

                // Update visit's platform data (including historic records) and publish update events when necessary
                $this->updatePlatformData($platformRow['idsite'], $platformKey, $platformData);

                $this->allocateCostOfPlatformRow(AOM::PLATFORM_CRITEO, $platformRow, $platformKey, $platformData);
            }

            $this->validateMergeResults(AOM::PLATFORM_CRITEO, $date);
        }
    }

    public function getPlatformDataOfVisit($idsite, $date, $idvisit, array $aomAdParams)
    {
        $mergerPlatformDataOfVisit = new MergerPlatformDataOfVisit(AOM::PLATFORM_CRITEO);

        // We need a campaignId for Criteo
        if (!array_key_exists('campaignId', $aomAdParams)) {
            $this->logger->warning(
                'Could not find campaignId in ad params of visit ' . $idvisit
                    . ' although platform has been identified as Criteo.'
            );
            return $mergerPlatformDataOfVisit;
        }

        $mergerPlatformDataOfVisit->setPlatformKey($aomAdParams['campaignId']);

        // Get the exactly matching platform row
        $platformRow = $this->getExactMatchPlatformRow($idsite, $date, $aomAdParams['campaignId']);
        if (!$platformRow) {

            $platformRow = $this->getHistoricalMatchPlatformRow($idsite, $aomAdParams['campaignId']);

            // Neither exact nor historical match with platform data found
            if (!$platformRow) {
                return $mergerPlatformDataOfVisit->setPlatformData(['campaignId' => $aomAdParams['campaignId']]);
            }

            // Historical match only
            return $mergerPlatformDataOfVisit->setPlatformData(array_merge(
                ['campaignId' => (string) $aomAdParams['campaignId']],
                $platformRow
            ));
        }

        // Exact match
        return $mergerPlatformDataOfVisit
            ->setPlatformData([
                'campaignId' => (string) $aomAdParams['campaignId'],
                'campaign' => $platformRow['campaign'],
            ])
            ->setPlatformRowId($platformRow['platformRowId']);
    }

    /**
     * Returns platform data when a match of Criteo click and platform data including cost is found. False otherwise.
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
                . ' FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_CRITEO)
                . ' WHERE idsite = ? AND date = ? AND campaign_id = ?',
            [$idsite, $date, $campaignId,]
        );

        if ($result) {
            $this->logger->debug(
                'Found exact match platform row ID ' . $result['platformRowId'] . ' in imported Criteo data for visit.'
            );
        } else {
            $this->logger->debug('Could not find exact match in imported Criteo data for Criteo visit.');
        }

        return $result;
    }

    /**
     * Returns platform data when a historical match of Criteo click and platform data is found. False otherwise.
     *
     * TODO: Imported data should also create platform_key which would make querying easier.
     *
     * @param int $idsite
     * @param string $campaignId
     * @return array|bool
     */
    private function getHistoricalMatchPlatformRow($idsite, $campaignId)
    {
        $result = Db::fetchRow(
            'SELECT campaign FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_CRITEO)
                . ' WHERE idsite = ? AND campaign_id = ?',
            [$idsite, $campaignId,]
        );

        if ($result) {
            $this->logger->debug('Found historical match in imported Criteo data for visit.');
        } else {
            $this->logger->debug('Could not find historical match in imported Criteo data for Criteo visit.');
        }

        return $result;
    }
}
