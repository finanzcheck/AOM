<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;

abstract class Base extends Report
{
    protected function init()
    {
        $this->category = 'Referrers_Referrers';
        $this->menuTitle = Piwik::translate('AOM_Report_MarketingPerformance');
    }
}
