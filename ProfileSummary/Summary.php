<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\ProfileSummary;

use Piwik\Common;
use Piwik\DataTable\Row;
use Piwik\Db;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\Live\ProfileSummary\ProfileSummaryAbstract;
use Piwik\View;

class Summary extends ProfileSummaryAbstract
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return Piwik::translate('General_Summary');
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $row = $this->profile['lastVisits'][0];
        if (!($row instanceof Row)) {
            return '';
        }

        $idSite = $row->getColumn('idSite');

        $totals = Db::fetchRow(
            'SELECT 
                COUNT(*) AS totalVisits, 
                SUM(cost) AS totalCost, 
                SUM(revenue) AS totalRevenue, 
                SUM(conversions) AS totalConversions
             FROM ' . Common::prefixTable('aom_visits') . '
             WHERE idsite = ? AND piwik_idvisitor = ?',
            [
                $idSite,
                hexdec($this->profile['visitorId']),
            ]
        );

        if ($totals['totalCost'] > 0) {

            $formatter = new Formatter();

            $view = new View('@AOM/_visitorProfileAomSummary.twig');
            $view->totalVisits = $totals['totalVisits'];
            $view->totalCost = $formatter->getPrettyMoney($totals['totalCost'], $idSite);
            $view->totalRevenue = $formatter->getPrettyMoney($totals['totalRevenue'], $idSite);
            $view->totalConversions = $totals['totalConversions'];
            $view->ratio = $formatter->getPrettyPercentFromQuotient(
                (number_format(($totals['totalRevenue'] / $totals['totalCost']), 2))
            );

            return $view->render();
        }
    }

    /**
     * @inheritdoc
     */
    public function getOrder()
    {
        return 1;
    }
}