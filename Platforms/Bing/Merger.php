<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

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
        $mergerPlatformDataOfVisit = new MergerPlatformDataOfVisit(AOM::PLATFORM_BING);

        // Make sure that we have the campaignId, adGroupId and keywordId available
        $missingParams = array_diff(['campaignId', 'adGroupId', 'keywordId',], array_keys($aomAdParams));
        if (count($missingParams)) {
            $this->logger->warning(
                'Could not find ' . implode(', ', $missingParams) . ' in ad params although platform has been '
                    . ' identified as Bing.'
            );
            return $mergerPlatformDataOfVisit;
        }

        $mergerPlatformDataOfVisit->setPlatformKey(
            $this->getPlatformKey($aomAdParams['campaignId'], $aomAdParams['adGroupId'], $aomAdParams['keywordId'])
        );

        // Get the exactly matching platform row
        $platformRow = $this->getExactMatchPlatformRow(
            $idsite, $date, $aomAdParams['campaignId'], $aomAdParams['adGroupId'], $aomAdParams['keywordId']
        );
        if (!$platformRow) {

            $platformRow = $this->getHistoricalMatchPlatformRow(
                $idsite, $aomAdParams['campaignId'], $aomAdParams['adGroupId'], $aomAdParams['keywordId']
            );

            // Neither exact nor historical match with platform data found
            if (!$platformRow) {
                return $mergerPlatformDataOfVisit->setPlatformData(
                    [
                        'campaignId' => $aomAdParams['campaignId'],
                        'adGroupId' => $aomAdParams['adGroupId'],
                        'keywordId' => $aomAdParams['keywordId'],
                    ]
                );
            }

            // Historical match only
            return $mergerPlatformDataOfVisit->setPlatformData(array_merge(
                [
                    'campaignId' => $aomAdParams['campaignId'],
                    'adGroupId' => $aomAdParams['adGroupId'],
                    'keywordId' => $aomAdParams['keywordId'],
                ],
                $platformRow
            ));
        }

        // Exact match
        return $mergerPlatformDataOfVisit
            ->setPlatformData(array_merge(
                [
                    'campaignId' => $aomAdParams['campaignId'],
                    'adGroupId' => $aomAdParams['adGroupId'],
                    'keywordId' => $aomAdParams['keywordId'],
                ],
                [
                    'account' => $platformRow['account'],
                    'accountId' => $platformRow['accountId'],
                    'campaign' => $platformRow['campaign'],
                    'adGroup' => $platformRow['adGroup'],
                    'keyword' => $platformRow['keyword'],
                ]
            ))
            ->setPlatformRowId($platformRow['platformRowId']);
    }

    /**
     * Returns platform data when a match of Bing click and platform data including cost is found. False otherwise.
     *
     * TODO: Imported data should also create platform_key which would make querying easier.
     *
     * @param int $idsite
     * @param string $date
     * @param string $campaignId
     * @param string $adGroupId
     * @param string $keywordId
     * @return array|bool
     */
    private function getExactMatchPlatformRow($idsite, $date, $campaignId, $adGroupId, $keywordId)
    {
        $result = Db::fetchRow(
            'SELECT id AS platformRowId, account_id AS accountId, accoung, campaign, ad_group AS adGroup, keyword '
                . ' FROM ' . Common::prefixTable('aom_bing')
                . ' WHERE idsite = ? AND date = ? AND campaign_id = ? AND ad_group_id = ? AND keyword_id = ?',
            [$idsite, $date, $campaignId, $adGroupId, $keywordId,]
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
     * @param string $adGroupId
     * @param string $keywordId
     * @return array|bool
     */
    private function getHistoricalMatchPlatformRow($idsite, $campaignId, $adGroupId, $keywordId)
    {
        $result = Db::fetchRow(
            'SELECT campaign, site FROM ' . Common::prefixTable('aom_criteo')
            . ' WHERE idsite = ? AND campaign_id = ? AND ad_group_id = ? AND keyword_id = ?',
            [$idsite, $campaignId, $adGroupId, $keywordId,]
        );

        if ($result) {
            $this->logger->debug('Found historical match in imported Criteo data for visit.');
        } else {
            $this->logger->debug('Could not find historical match in imported Criteo data for Criteo visit.');
        }

        return $result;
    }

    /**
     * @param string $campaignId
     * @param string $adGroupId
     * @param string $keywordId
     * @return string
     */
    private function getPlatformKey($campaignId, $adGroupId, $keywordId)
    {
        return $campaignId . '-' . $adGroupId . '-' . $keywordId;
    }
}
