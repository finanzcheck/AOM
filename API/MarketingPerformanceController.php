<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\API;

use Exception;
use Piwik\Archive\DataTableFactory;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Map;
use Piwik\DataTable\Row;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Period;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Site;

class MarketingPerformanceController
{
    /**
     * @param int $idSite
     * @param $period
     * @param $date
     * @param string $idSubtable
     * @return DataTable|Map
     */
    public static function getMarketingPerformance($idSite, $period, $date, $idSubtable)
    {
        // Multiple periods
        if (Period::isMultiplePeriod($date, $period)) {
            $map = new Map();
            $period = PeriodFactory::build($period, $date, Site::getTimezoneFor($idSite));
            foreach ($period->getSubperiods() as $subperiod) {
                $map->addTable(
                    self::getPeriodDataTable($idSite, $subperiod, $idSubtable),
                    $subperiod->getLocalizedShortString()
                );
            }

            return $map;
        }

        // One period only
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        return self::getPeriodDataTable($idSite, $period, $idSubtable);
    }

    /**
     * @param int $idSite
     * @param Period $period
     * @param string $idSubtable
     * @return DataTable
     * @throws Exception
     */
    private static function getPeriodDataTable($idSite, Period $period, $idSubtable)
    {
        $table = new DataTable();
        $table->setMetadata(DataTableFactory::TABLE_METADATA_PERIOD_INDEX, $period);

        $startDate = $period->getDateStart()->toString('Y-m-d');
        $endDate = $period->getDateEnd()->toString('Y-m-d');

        $summaryRow = [
            'label' => Piwik::translate('AOM_Report_MarketingPerformance_Total'),
            'platform_impressions' => 0,
            'platform_clicks' => 0,
            'platform_cost' => 0,
            'nb_visits' => 0,
            'nb_uniq_visitors' => 0,
            'nb_conversions' => 0,
            'revenue' => 0,
        ];

        if ($idSubtable) {
            list($table, $summaryRow) = self::addSubTableData(
                $table,
                $summaryRow,
                $startDate,
                $endDate,
                $idSite,
                $idSubtable
            );
        } else {
            list($table, $summaryRow) = self::addPlatformData($table, $summaryRow, $startDate, $endDate, $idSite);
            list($table, $summaryRow) = self::addNonPlatformData($table, $summaryRow, $startDate, $endDate, $idSite);
        }

        $formatter = new Formatter();

        // Summary row calculations
        $summaryRow['platform_cpc'] = $summaryRow['platform_clicks'] > 0
            ? $formatter->getPrettyMoney($summaryRow['platform_cost'] / $summaryRow['platform_clicks'], $idSite)
            : 0;
        $summaryRow['conversion_rate'] = $summaryRow['nb_conversions'] > 0 && $summaryRow['nb_visits'] > 0
            ? $formatter->getPrettyPercentFromQuotient($summaryRow['nb_conversions'] / $summaryRow['nb_visits'])
            : 0;
        $summaryRow['cost_per_conversion'] = $summaryRow['platform_cost'] > 0 && $summaryRow['nb_conversions'] > 0
            ? $formatter->getPrettyMoney($summaryRow['platform_cost'] / $summaryRow['nb_conversions'], $idSite)
            : 0;
        $summaryRow['return_on_ad_spend'] = $summaryRow['revenue'] > 0 && $summaryRow['platform_cost'] > 0
            ? ($summaryRow['revenue'] / $summaryRow['platform_cost'])
            : 0;


        // Summary formatting (must happen after calculations!)
        $summaryRow['platform_cost'] = $formatter->getPrettyMoney($summaryRow['platform_cost'], $idSite);

        // TODO: Fix
//        $summaryRow['revenue'] = $formatter->getPrettyMoney($summaryRow['revenue'], $idSite);
        $summaryRow['return_on_ad_spend'] = $formatter->getPrettyPercentFromQuotient($summaryRow['return_on_ad_spend']);

        $table->addSummaryRow(new Row([Row::COLUMNS => $summaryRow]));

        return $table;
    }

    /**
     * @param DataTable $table
     * @param array $summaryRow
     * @param $startDate
     * @param $endDate
     * @param $idSite
     * @return array
     * @throws Exception
     */
    private static function addPlatformData(DataTable $table, array $summaryRow, $startDate, $endDate, $idSite)
    {
        $formatter = new Formatter();

        foreach (AOM::getPlatforms() as $platformName) {

            $platform = AOM::getPlatformInstance($platformName);

            // Imported data (data like cost is not available in aom_visits table!)
            // Some platforms do not have clicks and impressions (e.g. individual campaigns)
            $columns = array_keys(Db::fetchAssoc('SHOW COLUMNS FROM ' . $platform->getDataTableName()));
            $platformData = Db::fetchRow(
                'SELECT ROUND(sum(cost), 2) as cost, '
                    . (in_array('clicks', $columns) ? 'sum(clicks)' : '0') . ' as clicks, '
                    . (in_array('impressions', $columns) ? 'sum(impressions)' : '0') . ' as impressions '
                    . ' FROM ' . $platform->getDataTableName() . ' AS platform '
                    . ' WHERE idsite = ? AND date >= ? AND date <= ?',
                [
                    $idSite,
                    $startDate,
                    $endDate,
                ]
            );

            // Reprocessed visits data
            $reprocessedVisitsData = Db::fetchRow(
                'SELECT COUNT(*) AS visits, COUNT(DISTINCT(piwik_idvisitor)) AS unique_visitors, '
                    . 'SUM(conversions) AS conversions, SUM(revenue) AS revenue '
                    . ' FROM ' . Common::prefixTable('aom_visits')
                    . ' WHERE idsite = ? AND channel = ? AND date_website_timezone >= ? AND date_website_timezone <= ?',
                [
                    $idSite,
                    $platform->getName(),
                    $startDate,
                    $endDate,
                ]
            );

            // Add to DataTable
            $row = [
                Row::COLUMNS => [
                    'label' => $platform->getLocalizedPlatformName(),
                    'platform_impressions' => $platformData['impressions'],
                    'platform_clicks' => $platformData['clicks'],
                    'platform_cost' => ($platformData['cost'] > 0)
                        ? $formatter->getPrettyMoney($platformData['cost'], $idSite) : null,
                    'platform_cpc' => ($platformData['clicks'] > 0 && $platformData['cost'] / $platformData['clicks'] > 0)
                        ? $formatter->getPrettyMoney($platformData['cost'] / $platformData['clicks'], $idSite) : null,
                    'nb_visits' => $reprocessedVisitsData['visits'],
                    'nb_uniq_visitors' => $reprocessedVisitsData['unique_visitors'],
                    'conversion_rate' => ($reprocessedVisitsData['visits'] > 0)
                        ? $formatter->getPrettyPercentFromQuotient($reprocessedVisitsData['conversions'] / $reprocessedVisitsData['visits']) : null,
                    'nb_conversions' => $reprocessedVisitsData['conversions'],
                    'cost_per_conversion' => ($platformData['cost'] > 0 && $reprocessedVisitsData['conversions'] > 0)
                        ? $formatter->getPrettyMoney($platformData['cost'] / $reprocessedVisitsData['conversions'], $idSite) : null,
                    'revenue' => ($reprocessedVisitsData['revenue'] > 0 ? $reprocessedVisitsData['revenue'] : 0),
                    'return_on_ad_spend' => ($reprocessedVisitsData['revenue'] > 0 && $platformData['cost'] > 0)
                        ? $formatter->getPrettyPercentFromQuotient($reprocessedVisitsData['revenue'] / $platformData['cost']) : null,
                ],
            ];

            // TODO: Add drill-down only when data is not null!
            $marketingPerformanceSubTables = $platform->getMarketingPerformanceSubTables();
            if ($marketingPerformanceSubTables) {
                $row[Row::DATATABLE_ASSOCIATED] = $platformName . '_'
                    . $marketingPerformanceSubTables->getMainSubTableId();
            }
            $table->addRowFromArray($row);

            // Add to summary
            $summaryRow['platform_impressions'] += $platformData['impressions'];
            $summaryRow['platform_clicks'] += $platformData['clicks'];
            $summaryRow['platform_cost'] += $platformData['cost'];
            $summaryRow['nb_visits'] += $reprocessedVisitsData['visits'];
            $summaryRow['nb_uniq_visitors'] += (int) $reprocessedVisitsData['unique_visitors'];
            $summaryRow['nb_conversions'] += $reprocessedVisitsData['conversions'];
            $summaryRow['revenue'] += ($reprocessedVisitsData['revenue'] > 0 ? $reprocessedVisitsData['revenue'] : 0);

        }

        return [$table, $summaryRow];
    }

    /**
     * @param DataTable $table
     * @param array $summaryRow
     * @param $startDate
     * @param $endDate
     * @param $idSite
     * @return array
     * @throws Exception
     */
    private static function addNonPlatformData(DataTable $table, array $summaryRow, $startDate, $endDate, $idSite)
    {
        $formatter = new Formatter();

        $platforms = join('","', AOM::getPlatforms());
        $data = Db::fetchAll(
            'SELECT channel, COUNT(*) AS visits, COUNT(DISTINCT(piwik_idvisitor)) AS unique_visitors, '
                . 'SUM(conversions) AS conversions, SUM(revenue) AS revenue '
                . ' FROM ' . Common::prefixTable('aom_visits') . ' AS visits '
                . ' WHERE idsite = ? AND channel NOT IN ("' . $platforms . '") AND date_website_timezone >= ? '
                . ' AND date_website_timezone <= ? GROUP BY channel',
            [
                $idSite,
                $startDate,
                $endDate,
            ]
        );

        foreach ($data as $row) {

            // Add to DataTable
            $table->addRowFromArray([
                Row::COLUMNS => [
                    'label' => $row['channel'],
                    'nb_visits' => $row['visits'],
                    'nb_uniq_visitors' => $row['unique_visitors'],
                    'conversion_rate' => $formatter->getPrettyPercentFromQuotient($row['conversions'] / $row['visits']),
                    'nb_conversions' => $row['conversions'],
                    'revenue' => ($row['revenue'] > 0 ? $row['revenue'] : 0),
                ]
            ]);

            // Add to summary
            $summaryRow['nb_visits'] += $row['visits'];
            $summaryRow['nb_uniq_visitors'] += (int) $row['unique_visitors'];
            $summaryRow['nb_conversions'] += $row['conversions'];
            $summaryRow['revenue'] += ($row['revenue'] > 0 ? $row['revenue'] : 0);
        }

        return [$table, $summaryRow];
    }

    /**
     * @param DataTable $table
     * @param array $summaryRow
     * @param $startDate
     * @param $endDate
     * @param $idSite
     * @param string $idSubTable
     * @return array
     * @throws Exception
     */
    private static function addSubTableData(
        DataTable $table,
        array $summaryRow,
        $startDate,
        $endDate,
        $idSite,
        $idSubTable
    )
    {
        $exploded = explode('_', $idSubTable);

        // Validate $idSubTable
        if (!in_array($exploded[0], AOM::getPlatforms())) {
            throw new \Exception('idSubTable must start with platform name');
        }

        $platform = AOM::getPlatformInstance($exploded[0]);

        $marketingPerformanceSubTables = $platform->getMarketingPerformanceSubTables();
        if (!$marketingPerformanceSubTables) {
            throw new \Exception('platform does not support marketing performance sub tables');
        }

        if (count($exploded) < 2) {
            throw new \Exception('idSubTable must at least consist of platform name and sub table identifier');
        }

        if (!in_array($exploded[1], $marketingPerformanceSubTables->getSubTableIds())) {
            throw new \Exception('platform does not support the given sub table');
        }

        return $marketingPerformanceSubTables->{'get' . $exploded[1]}(
            $table,
            $summaryRow,
            $startDate,
            $endDate,
            $idSite,
            count($exploded) == 3 ? $exploded[2] : null
        );
    }
}
