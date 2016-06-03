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
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class Platform extends VisitDimension
{
    protected $columnName = 'aom_platform';
    protected $columnType = 'VARCHAR(255) NULL';

    /**
     * The installation is already implemented based on the $columnName and $columnType.
     * We overwrite this method to add indices on the new column too.
     *
     * @return array
     */
    public function install()
    {
        $changes = parent::install();

        $changes['log_visit'][] = 'ADD INDEX index_aom_platform (aom_platform)';

        // Required at least for ?module=API&method=AOM.getStatus...
        $changes['log_visit'][] =
            'ADD INDEX index_visit_first_action_time_aom_platform (visit_first_action_time, aom_platform)';

        return $changes;
    }

    /**
     * @inheritdoc
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        // There might be no action (e.g. when we track a conversion)
        if (null === $action) {
            return null;
        }
        
        return AOM::getPlatformFromUrl(AOM::getParamsUrl($request));
    }
}
