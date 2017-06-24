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
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\Referrers\Columns\Referrer;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetMarketingPerformance extends Report
{
    protected function init()
    {
        $this->name = Piwik::translate('AOM_Report_MarketingPerformance');

        $this->categoryId = 'Referrers_Referrers';
        $this->dimension = new Referrer();
        $this->documentation = Piwik::translate('AOM_Report_MarketingPerformance_Description');

        // TODO: Current width of columns is a little too big
        // TODO: Add actions, time on site and bounce rate?!
        $this->metrics = [
            'platform_impressions',
            'platform_clicks',
            'platform_cost',
            'platform_cpc',
            'nb_visits',
            'nb_uniq_visitors',
            'conversion_rate',
            'nb_conversions',
            'cost_per_conversion',
            'revenue',
            'return_on_ad_spend',
        ];
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setCategoryId('Referrers_Referrers')
                ->setName(Piwik::translate('AOM_Report_MarketingPerformance'))
                ->setSubcategoryId(Piwik::translate('AOM_Report_MarketingPerformance'))
                ->setDefaultViewDataTable(HtmlTable::ID)
                ->setOrder(5)
        );
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
        $metrics['cost_per_conversion'] = Piwik::translate('AOM_Report_MarketingPerformance_CostPerConversion');
        $metrics['return_on_ad_spend'] = Piwik::translate('AOM_Report_MarketingPerformance_ReturnOnAdSpend');

        return $metrics;
    }
    
    public function loadSubtable()
    {
        
    }
}
