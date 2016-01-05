<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface MergerInterface
{
    /**
     * Sets the period to merge.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return mixed
     */
    public function setPeriod($startDate, $endDate);

    /**
     * Merges platform data.
     *
     * @return mixed
     */
    public function merge();

    /**
     * @param Platform $platform
     */
    public function setPlatform($platform);
}
