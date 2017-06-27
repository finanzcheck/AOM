<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Services;

use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;

class PiwikVisitService
{
    /**
     * This method is called by the Tracker.end event.
     * It detects if a new visit has been created by the Tracker. If so, it adds the visit to the aom_visits table.
     */
    public static function checkForNewVisit()
    {
        foreach (Db::query('SELECT * FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit > '
            . (Db::fetchOne('SELECT MAX(piwik_idvisit) FROM ' . Common::prefixTable('aom_visits')))
            . ' ORDER BY idvisit ASC LIMIT 10') // Limit to distribute work (if it has queued up for whatever reason)
            as $piwikVisit)
        {
            self::addNewPiwikVisit($piwikVisit);
        }
    }

    /**
     * Adds a Piwik visit to the aom_visits table.
     *
     * @param array $piwikVisit
     */
    private static function addNewPiwikVisit(array $piwikVisit)
    {
        // TODO: Add record to aom_visits table.
        // TODO: What about marketing data, if this visit is older than the latest import?


        // TODO: Move this to a centralized addOrUpdateVisit-method?
        // Post an event that a visit has been added or updated
        // (other plugins might listen to this event and publish them for example to an external SNS topic)
        Piwik::postEvent('AOM.addOrUpdateVisit', []);    // TODO: Add visit as argument, e.g. [$myFirstArg, &$myRefArg]
    }
}
