<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface MarketingPerformanceSubTablesInterface
{
    /**
     * Returns the name of the first level sub table, e.g. 'Campaigns'
     *
     * @return string
     */
    public static function getMainSubTableId();

    /**
     * Returns the names of all supported sub tables, e.g. ['Campaigns','AdGroups','Keywords']
     *
     * @return string[]
     */
    public static function getSubTableIds();
}
