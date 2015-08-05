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
use Piwik\Plugins\AOM\Settings;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class AdId extends VisitDimension
{
    protected $columnName = 'aom_ad_id';
    protected $columnType = 'VARCHAR(255) NULL';

    /**
     * The installation is already implemented based on the $columnName and $columnType.
     * We overwrite this method to add an index on the new column too.
     *
     * @return array
     */
    public function install()
    {
        $changes = parent::install();

        $changes['log_visit'][] = 'ADD INDEX index_aom_ad_id (aom_ad_id)';

        return $changes;
    }

    /**
     * The onNewVisit method is triggered when a new visitor is detected.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed The value to be saved in 'aom_ad_id'. By returning boolean false no value will be saved.
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return $this->getAdIdFromUrl($action->getActionUrl());
    }

    /**
     * This hook is executed when determining if an action is the start of a new visit or part of an existing one.
     * We force the creation of a new visit when the adId of the current action is different from the visit's adId.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return bool
     */
    public function shouldForceNewVisit(Request $request, Visitor $visitor, Action $action = null)
    {
        // TODO: identify why this happens ...
        if (null === $action) {
            return false;
        }

        // Get adId of last visit
        $visitorInfo = $visitor->getVisitorInfo();
        $lastVisitAdId = (array_key_exists('idvisit', $visitorInfo) && is_numeric($visitorInfo['idvisit']))
            ? Db::fetchOne(
                'SELECT aom_ad_id FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?',
                [$visitor->getVisitorInfo()['idvisit']]
            ) : false;

        return ($lastVisitAdId != $this->getAdIdFromUrl($action->getActionUrl()));
    }

    /**
     * @param string $url
     * @return mixed Either the adId or false when no adId could be found.
     */
    private function getAdIdFromUrl($url)
    {
        $settings = new Settings();
        $parameterName = $settings->adId->getValue();

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        if (is_array($queryParams) && array_key_exists($parameterName, $queryParams)) {
            return $queryParams[$parameterName];
        }

        return false;
    }
}
