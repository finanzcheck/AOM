<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Scheduler\Schedule\Schedule;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        foreach (AOM::getPlatforms() as $platform) {

            $platform = AOM::getPlatformInstance($platform);

            if ($platform->isActive()) {

                // Although every active advertising platform's import-method is being triggered every hour,
                // data is only being imported when either it does not yet exist or the advertising platform has
                // specified additional logic (e.g. for reimporting data under specific circumstances)
                $schedule = Schedule::getScheduledTimeForPeriod(Schedule::PERIOD_HOUR);

                $this->custom($platform, 'import', null, $schedule);
            }
        }
    }
}
