<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Plugins\AOM\Commands\ReplenishVisits;
use Piwik\Scheduler\Schedule\Schedule;
use Piwik\Scheduler\Task;
use Psr\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function schedule()
    {
        foreach (AOM::getPlatforms() as $platformName) {

            $platform = AOM::getPlatformInstance($platformName);

            if ($platform->isActive()) {

                // Although every active advertising platform's import-method is being triggered every hour,
                // data is only being imported when either it does not yet exist or the advertising platform has
                // specified additional logic (e.g. for reimporting data under specific circumstances)
                $this->custom(
                    $platform,
                    'import',
                    true,
                    Schedule::getScheduledTimeForPeriod(Schedule::PERIOD_HOUR),
                    Task::NORMAL_PRIORITY
                );

            } else {
                $this->logger->info('Skipping inactive platform "' . $platformName. '".');
            }
        }

        // Replenish visits
        foreach (AOM::getPeriodAsArrayOfDates(
            date('Y-m-d', strtotime('-3 day', time())),
            date('Y-m-d', time())
        ) as $date) {
            $this->custom(
                new ReplenishVisits(),
                'processDate',
                $date,
                Schedule::getScheduledTimeForPeriod(Schedule::PERIOD_HOUR),
                Task::LOWEST_PRIORITY
            );
        }
    }
}
