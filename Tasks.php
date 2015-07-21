<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AOM;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('criteoImport');   // method will be executed once every day
    }

    public function criteoImport()
    {
        //Reimport Last 3 days
        $startDate = strftime("%Y-%m-%d", strtotime("-3 days"));
        $endDate = strftime("%Y-%m-%d");

        $criteo = new Criteo();
        $criteo->import($startDate, $endDate);
    }


}
