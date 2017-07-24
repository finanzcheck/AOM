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
    public function merge()
    {
        foreach (AOM::getPeriodAsArrayOfDates($this->startDate, $this->endDate) as $date) {

            // TODO: Do not merge if there are no processed real visits yet?

            foreach ($this->getPlatformRows(AOM::PLATFORM_BING, $date) as $platformRow) {

                $platformKey = $this->getPlatformKey(
                    $platformRow['campaign_id'], $platformRow['ad_group_id'], $platformRow['keyword_id']
                );

                $platformData = [
                    'accountId' => (string) $platformRow['account_id'],
                    'account' => $platformRow['account'],
                    'campaignId' => (string) $platformRow['campaign_id'],
                    'campaign' => $platformRow['campaign'],
                    'adGroupId' => (string) $platformRow['ad_group_id'],
                    'adGroup' => $platformRow['ad_group'],
                    'keywordId' => $platformRow['keyword_id'],
                    'keyword' => $platformRow['keyword'],
                ];

                // Update visit's platform data (including historic records) and publish update events when necessary
                $this->updatePlatformData($platformRow['idsite'], $platformKey, $platformData);

                $this->allocateCostOfPlatformRow(AOM::PLATFORM_BING, $platformRow, $platformKey, $platformData);
            }

            $this->validateMergeResults(AOM::PLATFORM_BING, $date);
        }
    }

    public function getPlatformDataOfVisit($idsite, $date, $idvisit, array $aomAdParams)
    {
        $mergerPlatformDataOfVisit = new MergerPlatformDataOfVisit(AOM::PLATFORM_BING);

        // Make sure that we have the campaignId, adGroupId and keywordId available
        $missingParams = array_diff(['campaignId', 'adGroupId', 'targetId',], array_keys($aomAdParams));
        if (count($missingParams)) {
            $this->logger->warning(
                'Could not find ' . implode(', ', $missingParams) . ' in ad params of visit ' . $idvisit
                    . ' although platform has been identified as Bing.'
            );
            return $mergerPlatformDataOfVisit;
        }

        // keywordId results from targetId
        // TODO: We should check if this really works
        $aomAdParams['keywordId'] = (false !== strpos($aomAdParams['targetId'], 'kwd-'))
            ? substr($aomAdParams['targetId'], strpos($aomAdParams['targetId'], 'kwd-') + 4)
            : null;
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
                        'campaignId' => (string) $aomAdParams['campaignId'],
                        'adGroupId' => (string) $aomAdParams['adGroupId'],
                        'keywordId' => (string) $aomAdParams['keywordId'],
                    ]
                );
            }

            // Historical match only
            return $mergerPlatformDataOfVisit->setPlatformData(array_merge(
                [
                    'campaignId' => (string) $aomAdParams['campaignId'],
                    'adGroupId' => (string) $aomAdParams['adGroupId'],
                    'keywordId' => (string) $aomAdParams['keywordId'],
                ],
                $platformRow
            ));
        }

        // Exact match
        return $mergerPlatformDataOfVisit
            ->setPlatformData(array_merge(
                [
                    'campaignId' => (string) $aomAdParams['campaignId'],
                    'adGroupId' => (string) $aomAdParams['adGroupId'],
                    'keywordId' => (string) $aomAdParams['keywordId'],
                ],
                [
                    'account' => $platformRow['account'],
                    'accountId' => (string) $platformRow['accountId'],
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
            'SELECT id AS platformRowId, account_id AS accountId, account, campaign, ad_group AS adGroup, keyword '
                . ' FROM ' . Common::prefixTable('aom_bing')
                . ' WHERE idsite = ? AND date = ? AND campaign_id = ? AND ad_group_id = ? AND keyword_id = ?',
            [$idsite, $date, $campaignId, $adGroupId, $keywordId,]
        );

        if ($result) {
            $this->logger->debug(
                'Found exact match platform row ID ' . $result['platformRowId'] . ' in imported Bing data for visit.'
            );
        } else {
            $this->logger->debug('Could not find exact match in imported Bing data for Bing visit.');
        }

        return $result;
    }

    /**
     * Returns platform data when a historical match of Bing click and platform data is found. False otherwise.
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
            'SELECT account_id AS accountId, account, campaign, ad_group AS adGroup, keyword '
                .' FROM ' . Common::prefixTable('aom_bing')
                . ' WHERE idsite = ? AND campaign_id = ? AND ad_group_id = ? AND keyword_id = ?',
            [$idsite, $campaignId, $adGroupId, $keywordId,]
        );

        if ($result) {
            $this->logger->debug('Found historical match in imported Bing data for visit.');
        } else {
            $this->logger->debug('Could not find historical match in imported Bing data for Bing visit.');
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
