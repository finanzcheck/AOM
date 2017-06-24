<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Plugins\AOM\AOM;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class AdData extends VisitDimension
{
    protected $columnName = 'aom_ad_data';
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
        list($rowId, $data) = $this->getAdData($request);

        return json_encode($data);
    }

    /**
     * Tries to find some ad data for this visit.
     *
     * @param Request $request
     * @return mixed
     * @throws \Piwik\Exception\UnexpectedWebsiteFoundException
     */
    public static function getAdData(Request $request)
    {
        $adParams = AdParams::getAdParamsFromRequest($request);
        if (!$adParams) {
            return [null, null];
        }

        $platformName = Platform::identifyPlatformFromRequest($request);
        if ($platformName) {
            $platform = AOM::getPlatformInstance($platformName);

            return $platform->getAdDataFromAdParams($request->getIdSite(), $adParams);
        }

        return [null, null];
    }
}
