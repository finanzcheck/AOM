<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Metrics\Formatter;

abstract class MarketingPerformanceSubTables implements MarketingPerformanceSubTablesInterface
{
    /**
     * @param string $label
     * @param array $data
     * @param int $idSite
     * @return array
     */
    protected function getColumns($label, array $data, $idSite)
    {
        $formatter = new Formatter();

        $columns = [
            'label' => $label,
            'platform_impressions' => array_key_exists('impressions', $data) ? $data['impressions'] : 0,
            'platform_clicks' => array_key_exists('clicks', $data) ? $data['clicks'] : 0,
            'platform_cost' => (array_key_exists('cost', $data) && $data['cost'] > 0)
                ? $formatter->getPrettyMoney($data['cost'], $idSite) : null,
            'platform_cpc' => (array_key_exists('clicks', $data) && $data['clicks'] > 0
                && array_key_exists('cost', $data) && $data['cost'] / $data['clicks'] > 0)
                ? $formatter->getPrettyMoney($data['cost'] / $data['clicks'], $idSite) : null,
            'nb_visits' => array_key_exists('visits', $data) ? $data['visits'] : 0,
            'nb_uniq_visitors' => array_key_exists('unique_visitors', $data) ? $data['unique_visitors'] : 0,
            'conversion_rate' => (array_key_exists('visits', $data) && $data['visits'] > 0)
                ? $formatter->getPrettyPercentFromQuotient($data['conversions'] / $data['visits']) : null,
            'nb_conversions' => array_key_exists('conversions', $data) ? $data['conversions'] : 0,
            'cost_per_conversion' => (array_key_exists('cost', $data) && $data['cost'] > 0
                && array_key_exists('conversions', $data) && $data['conversions'] > 0)
                ? $formatter->getPrettyMoney($data['cost'] / $data['conversions'], $idSite) : null,
            'revenue' => (array_key_exists('revenue', $data) && $data['revenue'] > 0) ? $data['revenue'] : null,
            'return_on_ad_spend' => (array_key_exists('revenue', $data) && $data['revenue'] > 0
                && array_key_exists('cost', $data) && $data['cost'] > 0)
                ? $formatter->getPrettyPercentFromQuotient($data['revenue'] / $data['cost']) : null,
        ];

        return $columns;
    }

    /**
     * @param array $summaryRow
     * @param array $data
     * @return array
     */
    protected function addToSummary(array $summaryRow, array $data)
    {
        $summaryRow['platform_impressions'] += array_key_exists('impressions', $data) ? $data['impressions'] : 0;
        $summaryRow['platform_clicks'] += array_key_exists('clicks', $data) ? $data['clicks'] : 0;
        $summaryRow['platform_cost'] += array_key_exists('cost', $data) ? $data['cost'] : 0;
        $summaryRow['nb_visits'] += array_key_exists('visits', $data) ? $data['visits'] : 0;
        $summaryRow['nb_uniq_visitors'] += array_key_exists('unique_visitors', $data) ? (int) $data['unique_visitors'] : 0;
        $summaryRow['nb_conversions'] += array_key_exists('conversions', $data) ? $data['conversions'] : 0;
        $summaryRow['revenue'] += (array_key_exists('revenue', $data) && $data['revenue'] > 0) ? $data['revenue'] : 0;

        return $summaryRow;
    }
}
