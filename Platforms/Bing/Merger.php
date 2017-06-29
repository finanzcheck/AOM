<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Piwik\Plugins\AOM\Platforms\AbstractMerger;
use Piwik\Plugins\AOM\Platforms\MergerInterface;

class Merger extends AbstractMerger implements MergerInterface
{
    /**
     * Returns an array of all platform data and whether this has been an exact match.
     * An exact match means a platform record with cost, which would trigger the cost (re)distribution process.
     *
     * @param int $idsite
     * @param array $aomAdParams
     * @return array e.g. [[... some platform data ...], true]
     */
    public function getPlatformDataOfVisit($idsite, array $aomAdParams)
    {
        return [['IMPLEMENT ME (BING)', false]];
    }
}
