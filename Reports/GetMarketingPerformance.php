<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\Referrers\Columns\Referrer;
use Piwik\View;

class GetMarketingPerformance extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('AOM_Report_MarketingPerformance');
        $this->menuTitle = $this->name;

        $this->dimension = new Referrer();
        $this->documentation = Piwik::translate('AOM_Report_MarketingPerformance_Description');

        // Place below "campaigns"
        $this->order = 999;

        // TODO: visits, visitors, costs, conversions, conversion value
        $this->metrics = [
            'platform_impressions',
            'platform_clicks',
            'platform_cost',
            'platform_cpc',
            'nb_visits',
            'nb_uniq_visitors',
            'conversion_rate',
            'nb_conversions',
            'revenue',
        ];

        // TODO: Add channel individual subtables
    }

    /**
     * @param ViewDataTable $view
     */
    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(['label' => $this->dimension->getName()]);
        }

        $view->config->columns_to_display = array_merge(['label'], $this->metrics);
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();

        $metrics['platform_impressions'] = Piwik::translate('AOM_Report_MarketingPerformance_PlatformImpressions');
        $metrics['platform_clicks'] = Piwik::translate('AOM_Report_MarketingPerformance_PlatformClicks');
        $metrics['platform_cost'] = Piwik::translate('AOM_Report_MarketingPerformance_PlatformCost');
        $metrics['platform_cpc'] = Piwik::translate('AOM_Report_MarketingPerformance_PlatformCpC');

        return $metrics;
    }
}
