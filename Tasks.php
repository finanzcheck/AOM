<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('criteoImport');
        $this->daily('adwordsImport');
    }

    public function criteoImport()
    {
        // Reimport last 3 days
        $startDate = strftime("%Y-%m-%d", strtotime("-3 days"));
        $endDate = strftime("%Y-%m-%d");

        $criteo = new Criteo();
        $criteo->import($startDate, $endDate);
    }

    public function adwordsImport()
    {
        // Reimport last 3 days
        $startDate = strftime("%Y-%m-%d", strtotime("-3 days"));
        $endDate = strftime("%Y-%m-%d");

        $adwords = new AdWords();
        $adwords->import($startDate, $endDate);
    }
}
