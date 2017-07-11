<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\SystemSettings;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class AdParams extends VisitDimension
{
    protected $columnName = 'aom_ad_params';
    protected $columnType = 'VARCHAR(1024) NULL';

    /**
     * The onNewVisit method is triggered when a new visit is detected.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed The value to be saved in 'aom_ad_params'. By returning boolean false no value will be saved.
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return json_encode($this->getAdParamsFromRequest($request));
    }

    /**
     * This hook is executed when determining if an action is the start of a new visit or part of an existing one.
     * We force the creation of a new visit when the ad data of the current action is different from the visit's
     * current ad data unless the configuration does not allow us to do so.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return bool
     */
    public function shouldForceNewVisit(Request $request, Visitor $visitor, Action $action = null)
    {
        // The plugin might be configured in a way that does not allow to split visits based on ad params.
        $settings = new SystemSettings();
        if (!$settings->createNewVisitWhenCampaignChanges->getValue()) {
            return false;
        }

        $adParams = $this->getAdParamsFromRequest($request);

        // Keep Piwik's default behaviour when we do not have any ad params
        if (!is_array($adParams) || 0 === count($adParams)) {
            return false;
        }

        // Get ad data of on-going visit
        $lastVisitAdParams = Db::fetchOne(
            'SELECT aom_ad_params FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?',
            [$visitor->visitProperties->getProperty('idvisit')]
        );

        // TODO: Overwrite pk_campaign & co. because we know better...?!

        // Force new visit when we have ad params for the first time
        if (null === $lastVisitAdParams) {
            return true;
        }

        // JSON-decode ad params (start new visit when ad params is obscure)
        $lastVisitAdParams = @json_decode($lastVisitAdParams, true);
        if (json_last_error() != JSON_ERROR_NONE
            || !is_array($lastVisitAdParams)
            || !array_key_exists('platform', $lastVisitAdParams)
        ) {
            return true;
        }

        return (count(array_diff_assoc($lastVisitAdParams, $adParams)) > 0
            || count(array_diff_assoc($adParams, $lastVisitAdParams)) > 0);
    }

    /**
     * @param Request $request
     * @return array|null
     */
    public static function getAdParamsFromRequest(Request $request)
    {
        $platformName = Platform::identifyPlatformFromRequest($request);

        if ($platformName) {
            $platform = AOM::getPlatformInstance($platformName);
            return $platform->getAdParamsFromRequest($request);
        }

        return null;
    }
}
