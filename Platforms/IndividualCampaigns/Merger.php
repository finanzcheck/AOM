<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\IndividualCampaigns;

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
        $this->logger->warning('The merger for individual campaigns has not yet been implemented.');
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
