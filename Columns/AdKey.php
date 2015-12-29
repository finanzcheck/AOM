<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Db;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class AdKey extends VisitDimension
{
    protected $columnName = 'aom_ad_key';
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

        $changes['log_visit'][] = 'ADD INDEX index_aom_ad_key (aom_ad_key)';

        return $changes;
    }

    /**
     * The onNewVisit method is triggered when a new visitor is detected.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed The value to be saved in 'aom_ad_key'. By returning boolean false no value will be saved.
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        // TODO: This entire stuff must be refactored!

//        // TODO: We call AOM::getAdParamsFromUrl() multiple times, which might be bad in terms of performance.
//        $adParams = AOM::getAdParamsFromUrl($action->getActionUrl());
//
//        if (is_array($adParams) && array_key_exists('platform', $adParams)) {
//
//            if (in_array($adParams['platform'], AOM::getPlatforms())) {
//
//                $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $adParams['platform'] . '\\' . $adParams['platform'];
//
//                /** @var PlatformInterface $platform */
//                $platform = new $className();
//                $adKey = $platform->getAdKeyFromAdParams($adParams);
//
//                return $adKey;
//            }
//        }

        return false;
    }
}
