<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class PlatformRowId extends VisitDimension
{
    protected $columnName = 'aom_platform_row_id';
    protected $columnType = 'BIGINT NULL';

    /**
     * The installation is already implemented based on the $columnName and $columnType.
     * We overwrite this method to add an index on the new column too.
     *
     * @return array
     */
    public function install()
    {
        $changes = parent::install();

        $changes['log_visit'][] = 'ADD INDEX index_aom_platform_row_id (aom_platform_row_id)';

        return $changes;
    }

    /**
     * The onNewVisit method is triggered when a new visitor is detected.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed The value to be saved in 'aom_platform_row_id'.
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        // There might be no action (e.g. when we track a conversion)
        if (null === $action) {
            return false;
        }

        list($rowId, $data) = AOM::getAdData($action);

        return $rowId;
    }
}
