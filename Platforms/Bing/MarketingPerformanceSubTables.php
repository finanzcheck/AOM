<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\MarketingPerformanceSubTablesInterface;

class MarketingPerformanceSubTables implements MarketingPerformanceSubTablesInterface
{
    public static $SUB_TABLE_ID_CAMPAIGNS = 'Campaigns';
    public static $SUB_TABLE_ID_AD_GROUPS = 'AdGroups';

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
            self::$SUB_TABLE_ID_AD_GROUPS,
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
        $formatter = new Formatter();

        // TODO: Use "id" in "platform_data" of aom_visits instead for merging?!

        // Imported data (data like impressions is not available in aom_visits table!)
        $campaignData = Db::fetchAssoc(
            'SELECT CONCAT(\'C\', campaign_id) AS campaignId, campaign, ROUND(sum(cost), 2) as cost, '
                . 'SUM(clicks) as clicks, SUM(impressions) as impressions '
                . 'FROM ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING) . ' '
                . 'WHERE idsite = ? AND date >= ? AND date <= ? '
                . 'GROUP BY campaignId',
            [
                $idSite,
                $startDate,
                $endDate,
            ]
        );

        // Replenished visits data
        // TODO: This will have bad performance when there's lots of data
        $replenishedVisitsData = Db::fetchAssoc(
            'SELECT '
                . 'CONCAT(\'C\', SUBSTRING_INDEX(SUBSTR(platform_data, LOCATE(\'campaign_id\', platform_data)+CHAR_LENGTH(\'campaign_id\')+3),\'"\',1)) as campaignId, '
                . 'COUNT(*) AS visits, COUNT(DISTINCT(piwik_idvisitor)) AS unique_visitors, SUM(conversions) AS conversions, SUM(revenue) AS revenue '
                . 'FROM ' . Common::prefixTable('aom_visits') . ' '
                . 'WHERE idsite = ? AND channel = ? AND date_website_timezone >= ? AND date_website_timezone <= ?'
                . 'GROUP BY campaignId',
            [
                $idSite,
                AOM::PLATFORM_BING,
                $startDate,
                $endDate,
            ]
        );

        // Merge data based on campaignId
        foreach (array_merge_recursive($campaignData, $replenishedVisitsData) as $data) {

            // Add to DataTable
            $table->addRowFromArray([
                Row::COLUMNS => [
                    'label' => $data['campaign'],
                    'platform_impressions' => $data['impressions'],
                    'platform_clicks' => $data['clicks'],
                    'platform_cost' => ($data['cost'] > 0)
                        ? $formatter->getPrettyMoney($data['cost'], $idSite) : null,
                    'platform_cpc' => ($data['clicks'] > 0 && $data['cost'] / $data['clicks'] > 0)
                        ? $formatter->getPrettyMoney($data['cost'] / $data['clicks'], $idSite) : null,
                    'nb_visits' => $data['visits'],
                    'nb_uniq_visitors' => $data['unique_visitors'],
                    'conversion_rate' => ($data['visits'] > 0)
                        ? $formatter->getPrettyPercentFromQuotient($data['conversions'] / $data['visits']) : null,
                    'nb_conversions' => $data['conversions'],
                    'cost_per_conversion' => ($data['cost'] > 0 && $data['conversions'] > 0)
                        ? $formatter->getPrettyMoney($data['cost'] / $data['conversions'], $idSite) : null,
                    'revenue' => ($data['revenue'] > 0)
                        ? $formatter->getPrettyMoney($data['revenue'], $idSite) : null,
                    'return_on_ad_spend' => ($data['revenue'] > 0 && $data['cost'] > 0)
                        ? $formatter->getPrettyPercentFromQuotient($data['revenue'] / $data['cost']) : null,
                ],
                Row::DATATABLE_ASSOCIATED => 'Bing_AdGroups_' . str_replace('C', '', $data['campaignId'][0]),
            ]);

            // Add to summary
            $summaryRow['platform_impressions'] += $data['impressions'];
            $summaryRow['platform_clicks'] += $data['clicks'];
            $summaryRow['platform_cost'] += $data['cost'];
            $summaryRow['nb_visits'] += $data['visits'];
            $summaryRow['nb_uniq_visitors'] += (int) $data['unique_visitors'];
            $summaryRow['nb_conversions'] += $data['conversions'];
            $summaryRow['revenue'] += $data['revenue'];
        }

        return [$table, $summaryRow];
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
    public function getAdGroups(DataTable $table, array $summaryRow, $startDate, $endDate, $idSite, $id)
    {
        $formatter = new Formatter();

        // TODO: Use "id" in "platform_data" of aom_visits instead for merging?!

        // Imported data (data like impressions is not available in aom_visits table!)
        $campaignData = Db::fetchAssoc(
            'SELECT CONCAT(\'AG\', ad_group_id) AS adGroupId, ad_group AS adGroup, ROUND(sum(cost), 2) as cost, '
            . 'SUM(clicks) as clicks, SUM(impressions) as impressions '
            . 'FROM ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING) . ' '
            . 'WHERE idsite = ? AND date >= ? AND date <= ? AND campaign_id = ?'
            . 'GROUP BY adGroupId',
            [
                $idSite,
                $startDate,
                $endDate,
                $id,
            ]
        );

        // Replenished visits data
        // TODO: This will have bad performance when there's lots of data
        $replenishedVisitsData = Db::fetchAssoc(
            'SELECT '
            . 'CONCAT(\'AG\', SUBSTRING_INDEX(SUBSTR(platform_data, LOCATE(\'ad_group_id\', platform_data)+CHAR_LENGTH(\'ad_group_id\')+3),\'"\',1)) as adGroupId, '
            . 'COUNT(*) AS visits, COUNT(DISTINCT(piwik_idvisitor)) AS unique_visitors, SUM(conversions) AS conversions, SUM(revenue) AS revenue '
            . 'FROM ' . Common::prefixTable('aom_visits') . ' '
            . 'WHERE idsite = ? AND channel = ? AND date_website_timezone >= ? AND date_website_timezone <= ? AND platform_data LIKE ?'
            . 'GROUP BY adGroupId',
            [
                $idSite,
                AOM::PLATFORM_BING,
                $startDate,
                $endDate,
                '%"campaign_id":"' . $id . '"%',
            ]
        );

        // Merge data based on campaignId
        foreach (array_merge_recursive($campaignData, $replenishedVisitsData) as $data) {

            // Add to DataTable
            $table->addRowFromArray([
                Row::COLUMNS => [
                    'label' => $data['adGroup'],
                    'platform_impressions' => $data['impressions'],
                    'platform_clicks' => $data['clicks'],
                    'platform_cost' => ($data['cost'] > 0)
                        ? $formatter->getPrettyMoney($data['cost'], $idSite) : null,
                    'platform_cpc' => ($data['clicks'] > 0 && $data['cost'] / $data['clicks'] > 0)
                        ? $formatter->getPrettyMoney($data['cost'] / $data['clicks'], $idSite) : null,
                    'nb_visits' => $data['visits'],
                    'nb_uniq_visitors' => $data['unique_visitors'],
                    'conversion_rate' => ($data['visits'] > 0)
                        ? $formatter->getPrettyPercentFromQuotient($data['conversions'] / $data['visits']) : null,
                    'nb_conversions' => $data['conversions'],
                    'cost_per_conversion' => ($data['cost'] > 0 && $data['conversions'] > 0)
                        ? $formatter->getPrettyMoney($data['cost'] / $data['conversions'], $idSite) : null,
                    'revenue' => ($data['revenue'] > 0)
                        ? $formatter->getPrettyMoney($data['revenue'], $idSite) : null,
                    'return_on_ad_spend' => ($data['revenue'] > 0 && $data['cost'] > 0)
                        ? $formatter->getPrettyPercentFromQuotient($data['revenue'] / $data['cost']) : null,
                ],
//                Row::DATATABLE_ASSOCIATED => 'Bing_Keyword_' . $data['campaignId'],
            ]);

            // Add to summary
            $summaryRow['platform_impressions'] += $data['impressions'];
            $summaryRow['platform_clicks'] += $data['clicks'];
            $summaryRow['platform_cost'] += $data['cost'];
            $summaryRow['nb_visits'] += $data['visits'];
            $summaryRow['nb_uniq_visitors'] += (int) $data['unique_visitors'];
            $summaryRow['nb_conversions'] += $data['conversions'];
            $summaryRow['revenue'] += $data['revenue'];
        }

        return [$table, $summaryRow];
    }
}
