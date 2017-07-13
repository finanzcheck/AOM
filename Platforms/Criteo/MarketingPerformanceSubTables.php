<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;

class MarketingPerformanceSubTables extends \Piwik\Plugins\AOM\Platforms\MarketingPerformanceSubTables
{
    public static $SUB_TABLE_ID_CAMPAIGNS = 'Campaigns';

    /**
     * Returns the name of the first level sub table
     *
     * @return string
     */
    public static function getMainSubTableId()
    {
        return self::$SUB_TABLE_ID_CAMPAIGNS;
    }

    /**
     * Returns the names of all supported sub tables
     *
     * @return string[]
     */
    public static function getSubTableIds()
    {
        return [
            self::$SUB_TABLE_ID_CAMPAIGNS,
        ];
    }

    /**
     * @param DataTable $table
     * @param array $summaryRow
     * @param $startDate
     * @param $endDate
     * @param $idSite
     * @param string $id An arbitrary identifier of a specific platform element (e.g. a campaign or an ad group)
     * @return array
     * @throws \Exception
     */
    public function getCampaigns(DataTable $table, array $summaryRow, $startDate, $endDate, $idSite, $id)
    {
        // TODO: Use "id" in "platform_data" of aom_visits instead for merging?!

        // Imported data (data like impressions is not available in aom_visits table!)
        $importedData = Db::fetchAssoc(
            'SELECT CONCAT(\'C\', campaign_id) AS campaignId, campaign, ROUND(sum(cost), 2) as cost, '
                . 'SUM(clicks) as clicks, SUM(impressions) as impressions '
                . 'FROM ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_CRITEO) . ' '
                . 'WHERE idsite = ? AND date >= ? AND date <= ? '
                . 'GROUP BY campaignId',
            [
                $idSite,
                $startDate,
                $endDate,
            ]
        );

        // TODO: This will have bad performance when there's lots of data (use platform_key or something else in future)
        $aomVisits = Db::fetchAssoc(
            'SELECT '
                . '(CASE WHEN (LOCATE(\'campaignId\', platform_data) > 0) '
                . 'THEN CONCAT(\'C\', SUBSTRING_INDEX(SUBSTR(platform_data, LOCATE(\'campaignId\', platform_data)+CHAR_LENGTH(\'campaignId\')+3),\'"\',1))'
                . 'ELSE CONCAT(\'C\', SUBSTRING_INDEX(SUBSTR(platform_data, LOCATE(\'campaign_id\', platform_data)+CHAR_LENGTH(\'campaign_id\')+3),\'"\',1))'
                . 'END) AS campaignId, '
                . 'COUNT(*) AS visits, COUNT(DISTINCT(piwik_idvisitor)) AS unique_visitors, SUM(conversions) AS conversions, SUM(revenue) AS revenue '
                . 'FROM ' . Common::prefixTable('aom_visits') . ' '
                . 'WHERE idsite = ? AND channel = ? AND date_website_timezone >= ? AND date_website_timezone <= ? '
                . 'GROUP BY campaignId',
            [
                $idSite,
                AOM::PLATFORM_CRITEO,
                $startDate,
                $endDate,
            ]
        );


        // Merge data based on campaignId
        foreach (array_merge_recursive($importedData, $aomVisits) as $data) {

            // We might have visits that we identified as coming from this platform but that we could not merge
            if (!isset($data['campaign'])) {
                $data['campaign'] = 'unknown (Criteo identified but not merged)';  // TODO: Add translation
            }

            // Add to DataTable
            $table->addRowFromArray([
                Row::COLUMNS => $this->getColumns($data['campaign'], $data, $idSite),
            ]);

            // Add to summary
            $summaryRow = $this->addToSummary($summaryRow, $data);
        }

        return [$table, $summaryRow];
    }
}
