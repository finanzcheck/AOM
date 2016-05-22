<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
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
     * @return DataTable|Map
     */
    public static function getMarketingPerformance($idSite, $period, $date)
    {
        // Multiple periods
        if (Period::isMultiplePeriod($date, $period)) {
            $map = new Map();
            $period = PeriodFactory::build($period, $date, Site::getTimezoneFor($idSite));
            foreach ($period->getSubperiods() as $subperiod) {
                $map->addTable(self::getPeriodDataTable($idSite, $subperiod), $subperiod->getLocalizedShortString());
            }

            return $map;
        }

        // One period only
        $period = PeriodFactory::makePeriodFromQueryParams(Site::getTimezoneFor($idSite), $period, $date);

        return self::getPeriodDataTable($idSite, $period);
    }

    /**
     * @param int $idSite
     * @param Period $period
     * @return DataTable
     * @throws Exception
     */
    private static function getPeriodDataTable($idSite, Period $period)
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

        list($table, $summaryRow) = self::addPlatformData($table, $summaryRow, $startDate, $endDate, $idSite);
        list($table, $summaryRow) = self::addNonPlatformData($table, $summaryRow, $startDate, $endDate, $idSite);

        $formatter = new Formatter();

        // Summary row calculations
        $summaryRow['platform_cpc'] = $summaryRow['platform_clicks'] > 0
            ? $formatter->getPrettyMoney($summaryRow['platform_cost'] / $summaryRow['platform_clicks'], $idSite)
            : 0;
        $summaryRow['conversion_rate'] = ($summaryRow['nb_visits'] > 0)
            ? $formatter->getPrettyPercentFromQuotient($summaryRow['nb_conversions'] / $summaryRow['nb_visits'])
            : 0;

        // Summary formatting (must happen after calculations!)
        $summaryRow['platform_cost'] = $formatter->getPrettyMoney($summaryRow['platform_cost'], $idSite);
        $summaryRow['revenue'] = $formatter->getPrettyMoney($summaryRow['revenue'], $idSite);

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

            // TODO: Timezones correct?!

            // Imported data (data like impressions is not available in aom_visits table!)
            $platform = AOM::getPlatformInstance($platformName);
            $platformData = Db::fetchRow(
                'SELECT ROUND(sum(cost), 2) as cost, sum(clicks) as clicks, sum(impressions) as impressions '
                    . ' FROM ' . $platform->getDataTableName() . ' AS platform '
                    . ' WHERE date >= ? AND date <= ? AND idsite = ?',
                [
                    $startDate,
                    $endDate,
                    $idSite,
                ]
            );

            // Replenished visits data
            $replenishedVisitsData = Db::fetchRow(
                'SELECT COUNT(*) AS visits, COUNT(DISTINCT(piwik_idvisitor)) AS unique_visitors, '
                    . 'SUM(conversions) AS conversions, SUM(revenue) AS revenue '
                    . ' FROM ' . Common::prefixTable('aom_visits')
                    . ' WHERE channel = ? AND date_website_timezone >= ? AND date_website_timezone <= ? AND idsite = ?',
                [
                    $platformName,
                    $startDate,
                    $endDate,
                    $idSite,
                ]
            );

            // Add to DataTable
            $table->addRowFromArray([
                Row::COLUMNS => [
                    'label' => $platformName,
                    'platform_impressions' => $platformData['impressions'],
                    'platform_clicks' => $platformData['clicks'],
                    'platform_cost' => ($platformData['cost'] > 0)
                        ? $formatter->getPrettyMoney($platformData['cost'], $idSite) : null,
                    'platform_cpc' => ($platformData['clicks'] > 0 && $platformData['cost'] / $platformData['clicks'] > 0)
                        ? $formatter->getPrettyMoney($platformData['cost'] / $platformData['clicks'], $idSite) : null,
                    'nb_visits' => $replenishedVisitsData['visits'],
                    'nb_uniq_visitors' => $replenishedVisitsData['unique_visitors'],
                    'conversion_rate' => ($replenishedVisitsData['visits'] > 0)
                        ? $formatter->getPrettyPercentFromQuotient($replenishedVisitsData['conversions'] / $replenishedVisitsData['visits']) : null,
                    'nb_conversions' => $replenishedVisitsData['conversions'],
                    'revenue' => ($replenishedVisitsData['revenue'] > 0)
                        ? $formatter->getPrettyMoney($replenishedVisitsData['revenue'], $idSite) : null,
                ]
            ]);

            // Add to summary
            $summaryRow['platform_impressions'] += $platformData['impressions'];
            $summaryRow['platform_clicks'] += $platformData['clicks'];
            $summaryRow['platform_cost'] += $platformData['cost'];
            $summaryRow['nb_visits'] += $replenishedVisitsData['visits'];
            $summaryRow['nb_uniq_visitors'] += (int) $replenishedVisitsData['unique_visitors'];
            $summaryRow['nb_conversions'] += $replenishedVisitsData['conversions'];
            $summaryRow['revenue'] += $replenishedVisitsData['revenue'];

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
                . ' WHERE channel NOT IN ("' . $platforms . '") AND date_website_timezone >= ? '
                . ' AND date_website_timezone <= ? AND idsite = ? GROUP BY channel',
            [
                $startDate,
                $endDate,
                $idSite,
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
                    'revenue' => $formatter->getPrettyMoney($row['revenue'], $idSite),
                ]
            ]);

            // Add to summary
            $summaryRow['nb_visits'] += $row['visits'];
            $summaryRow['nb_uniq_visitors'] += (int) $row['unique_visitors'];
            $summaryRow['nb_conversions'] += $row['conversions'];
            $summaryRow['revenue'] += $row['revenue'];

        }

        return [$table, $summaryRow];
    }
}