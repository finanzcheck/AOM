<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Taboola;

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
        return [['IMPLEMENT ME (TABOOLA)', false]];
    }
}
