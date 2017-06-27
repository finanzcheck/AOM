<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\AbstractMerger;
use Piwik\Plugins\AOM\Platforms\MergerInterface;

class Merger extends AbstractMerger implements MergerInterface
{
    /**
     * Returns an array of all platform data and information about whether the visit has been merged.
     *
     * @return array
     */
    public function getPlatformDataOfVisit(array $visit)
    {
        return [['IMPLEMENT ME (AdWords)', false]];


        // TODO: Validate gclid is available (log warning otherwise)
        $gclid = 123; // TODO$visit

        $gclidRow = Db::fetchOne('SELECT * FROM ' . Common::prefixTable('aom_adwords_gclid') . ' WHERE gclid = "' . $gclid . '"');

        if (!$gclidRow) {
            return [['TODO', false]]; // TODO: Fill with ad params as platform data?!
        }

        // Now we also need some costs
        $costs = Db::fetchOne('SELECT * FROM ' . Common::prefixTable('aom_adwords') . ' WHERE gclid = "' . $gclid . '"');


        // TODO: 1. Check if we have a match imported; if so: $platformData + $isMerged = true

        // TODO: Look for gclid
        // If found, update ad params for merging

        // Look based on ad params


        if ($ids['network'] == 'd') {
            return implode('-', [
                $ids['network'],
                $ids['idsite'],
                $ids['date'],
                $ids['campaign_id'],
                $ids['ad_group_id']
            ]);
        }

        return implode('-', [
            $ids['network'],
            $ids['idsite'],
            $ids['date'],
            $ids['campaign_id'],
            $ids['ad_group_id'],
            $ids['keyword_id'],
        ]);

        // TODO: Should we also look for a match in the past to get campaign names etc. without costs?



        // TODO: Implement getPlatformDataForVisit() method.



        // TODO: Return correct values
        return [$platformData, $isMerged];
    }
}
