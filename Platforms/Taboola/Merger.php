<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Taboola;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractMerger;
use Piwik\Plugins\AOM\Platforms\MergerInterface;
use Piwik\Plugins\AOM\Platforms\MergerPlatformDataOfVisit;

class Merger extends AbstractMerger implements MergerInterface
{
    public function getPlatformDataOfVisit($idsite, $date, array $aomAdParams)
    {
        $mergerPlatformDataOfVisit = new MergerPlatformDataOfVisit(AOM::PLATFORM_TABOOLA);

        // Make sure that we have the campaignId and siteId available
        $missingParams = array_diff(['campaignId', 'siteId',], array_keys($aomAdParams));
        if (count($missingParams)) {
            $this->logger->warning(
                'Could not find ' . implode(', ', $missingParams) . ' in ad params although platform has been '
                    . ' identified as Taboola.'
            );
            return $mergerPlatformDataOfVisit;
        }

        $mergerPlatformDataOfVisit->setPlatformKey(
            $this->getPlatformKey($aomAdParams['campaignId'], $aomAdParams['siteId'])
        );

        // Get the exactly matching platform row
        $platformRow = $this->getExactMatchPlatformRow(
            $idsite, $date, $aomAdParams['campaignId'], $aomAdParams['siteId']
        );
        if (!$platformRow) {

            $platformRow = $this->getHistoricalMatchPlatformRow(
                $idsite, $aomAdParams['campaignId'], $aomAdParams['siteId']
            );

            // Neither exact nor historical match with platform data found
            if (!$platformRow) {
                return $mergerPlatformDataOfVisit->setPlatformData(
                    ['campaignId' => $aomAdParams['campaignId'], 'siteId' => $aomAdParams['siteId']]
                );
            }

            // Historical match only
            return $mergerPlatformDataOfVisit->setPlatformData(array_merge(
                ['campaignId' => $aomAdParams['campaignId'], 'siteId' => $aomAdParams['siteId']],
                $platformRow
            ));
        }

        // Exact match
        return $mergerPlatformDataOfVisit
            ->setPlatformData(array_merge(
                ['campaignId' => $aomAdParams['campaignId'], 'siteId' => $aomAdParams['siteId']],
                ['campaign' => $platformRow['campaign'], 'site' => $platformRow['site']]
            ))
            ->setPlatformRowId($platformRow['platformRowId']);
    }

    /**
     * Returns platform data when a match of Taboola click and platform data including cost is found. False otherwise.
     *
     * TODO: Imported data should also create platform_key which would make querying easier.
     *
     * @param int $idsite
     * @param string $date
     * @param string $campaignId
     * @param string $siteId
     * @return array|bool
     */
    private function getExactMatchPlatformRow($idsite, $date, $campaignId, $siteId)
    {
        $result = Db::fetchRow(
            'SELECT id AS platformRowId, campaign, site FROM ' . Common::prefixTable('aom_taboola')
                . ' WHERE idsite = ? AND date = ? AND campaign_id = ? AND site_id = ?',
            [$idsite, $date, $campaignId, $siteId,]
        );

        if ($result) {
            $this->logger->debug(
                'Found exact match platform row ID ' . $result['platformRowId'] . ' in imported Taboola data for visit.'
            );
        } else {
            $this->logger->debug('Could not find exact match in imported Taboola data for Taboola visit.');
        }

        return $result;
    }

    /**
     * Returns platform data when a historical match of Taboola click and platform data is found. False otherwise.
     *
     * TODO: Imported data should also create platform_key which would make querying easier.
     *
     * @param int $idsite
     * @param string $campaignId
     * @param string $siteId
     * @return array|bool
     */
    private function getHistoricalMatchPlatformRow($idsite, $campaignId, $siteId)
    {
        $result = Db::fetchRow(
            'SELECT campaign, site FROM ' . Common::prefixTable('aom_taboola')
            . ' WHERE idsite = ? AND campaign_id = ? AND site_id = ?',
            [$idsite, $campaignId, $siteId,]
        );

        if ($result) {
            $this->logger->debug('Found historical match in imported Taboola data for visit.');
        } else {
            $this->logger->debug('Could not find historical match in imported Taboola data for Taboola visit.');
        }

        return $result;
    }

    /**
     * @param string $campaignId
     * @param string $siteId
     * @return string
     */
    private function getPlatformKey($campaignId, $siteId)
    {
        return $campaignId . '-' . $siteId;
    }
}
