<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\AOM\AOM;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;
use Piwik\Tracker\Action;

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
     * The onNewVisit method is triggered when a new visitor is detected.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     *
     * @return mixed The value to be saved in 'aom_platform'.
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return $this->identifyPlatformFromRequest($request);
    }

    /**
     * Extracts and returns the advertising platform name (e.g. "AdWords", "Criteo", "IndividualCampaigns") or null
     * when no platform could be identified from the request's URL from the request's referrer URL.
     *
     * @param Request $request
     * @return mixed Either the platform or null when no valid platform could be extracted.
     */
    public static function identifyPlatformFromRequest(Request $request)
    {
        // Platform names must be ordered in a way, that the last check is for individual campaigns.
        // We should prefer other platforms!
        foreach (AOM::getPlatforms() as $platformName) {
            $platform = AOM::getPlatformInstance($platformName);
            if ($platform->isActive()) {
                if ($platform->isVisitComingFromPlatform($request)) {
                    return $platformName;
                }
            }
        }

        return null;
    }
}
