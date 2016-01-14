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
     * Sets the period to import.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return mixed
     */
    public function setPeriod($startDate, $endDate);

    /**
     * @return null|string
     */
    public function getStartDate();

    /**
     * @return null|string
     */
    public function getEndDate();

    /**
     * Imports platform data.
     *
     * @return mixed
     */
    public function import();
}
