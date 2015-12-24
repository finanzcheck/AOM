<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface ImporterInterface
{
    /**
     * Imports platform data for the specified period.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate   YYYY-MM-DD
     * @return mixed
     */
    public function import($startDate, $endDate);
}
