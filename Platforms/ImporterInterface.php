<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface ImporterInterface
{
    /**
     * Sets the period that should be imported.
     * Import yesterday's and today's data as default.
     *
     * TODO: Consider site timezone here?!
     *
     * @param null|string $startDate YYYY-MM-DD
     * @param null|string $endDate YYYY-MM-DD
     */
    public function setPeriod($startDate = null, $endDate = null);

    /**
     * @return null|string
     */
    public function getStartDate();

    /**
     * @return null|string
     */
    public function getEndDate();

    /**
     * Imports all active accounts of the specified platform day by day.
     *
     * @return void
     */
    public function import();
}
