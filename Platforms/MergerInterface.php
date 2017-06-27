<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface MergerInterface
{
    /**
     * Returns an array of all platform data and information about whether the visit has been merged.
     *
     * TODO: Pass needed variables instead of whole array $visit
     *
     * @param array $visit
     * @return array
     */
    public function getPlatformDataOfVisit(array $visit);
}
