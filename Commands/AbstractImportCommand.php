<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Commands;


use Monolog\Logger;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugin\Manager;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Site;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Piwik\Plugins\SitesManager\API as APISitesManager;

/**
 * Example:
 * ./console aom:reimport-visits --startDate=2016-01-06 --endDate=2016-01-06
 */
abstract class AbstactImportCommand extends ConsoleCommand
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param string|null $name
     * @param LoggerInterface|null $logger
     */
    public function __construct($name = null, LoggerInterface $logger = null)
    {
        $this->logger = AOM::getTasksLogger();

        parent::__construct($name);
    }

    /**
     * Convenience function for shorter logging statements
     *
     * @param string $logLevel
     * @param string $message
     * @param array $additionalContext
     */
    protected function log($logLevel, $message, $additionalContext = [])
    {
        $this->logger->log(
            $logLevel,
            $message,
            array_merge(['task' => 'replenish-visits'], $additionalContext)
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!in_array('AdvancedCampaignReporting', Manager::getInstance()->getInstalledPluginsName())) {
            $this->log(Logger::ERROR, 'Plugin "AdvancedCampaignReporting" must be installed and activated.');
            exit;
        }

        foreach (AOM::getPeriodAsArrayOfDates($input->getOption('startDate'), $input->getOption('endDate')) as $date) {
            $this->processDate($date);
        }
    }

    /**
     * Reimports a visit specific date.
     * This method must be public so that it can be called from Tasks.php.
     *
     * @param string $date YYYY-MM-DD
     * @throws \Exception
     */
    abstract public function processDate($date);




}