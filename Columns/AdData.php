<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\AOM\AOM;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class AdData extends VisitDimension
{
    protected $columnName = 'aom_ad_data';
    protected $columnType = 'TEXT NULL';

    /**
     * The onNewVisit method is triggered when a new visitor is detected.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed The value to be saved in 'aom_ad_data'. By returning boolean false no value will be saved.
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return AOM::getAdIdFromUrl($action->getActionUrl());
    }

    /**
     * This hook is executed when determining if an action is the start of a new visit or part of an existing one.
     * We force the creation of a new visit when the adData of the current action is different from the visit's adData.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return bool
     */
    public function shouldForceNewVisit(Request $request, Visitor $visitor, Action $action = null)
    {
        // TODO: Identify why this happens...
        if (null === $action) {
            return false;
        }

        // Get adId of last visit
        $visitorInfo = $visitor->getVisitorInfo();
        $lastVisitAdId = (array_key_exists('idvisit', $visitorInfo) && is_numeric($visitorInfo['idvisit']))
            ? Db::fetchOne(
                'SELECT aom_ad_data FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?',
                [$visitor->getVisitorInfo()['idvisit']]
            ) : false;

        return ($lastVisitAdId != AOM::getAdIdFromUrl($action->getActionUrl()));
    }
}
