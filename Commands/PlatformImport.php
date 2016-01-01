<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Example:
 * ./console aom:import --platform=AdWords --startDate=2015-12-20 --endDate=2015-12-20
 */
class PlatformImport extends ConsoleCommand
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
        // TODO: Replace StaticContainer with DI
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('aom:import')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED)
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->setDescription('Import an advertising platform\'s data for a specific period.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!in_array($input->getOption('platform'), AOM::getPlatforms())) {
            $this->logger->warning('Platform "' . $input->getOption('platform') . '" is not supported.');
            $this->logger->warning('Platform must be one of: ' . implode(', ', AOM::getPlatforms()));
            return;
        }

        // Is platform active?
        $settings = new Settings();
        if (!$settings->{'platform' . $input->getOption('platform') . 'IsActive'}->getValue()) {
            $this->logger->warning('Platform "' . $input->getOption('platform') . '" is not active.');
            return;
        }

        $platform = AOM::getPlatformInstance($input->getOption('platform'), null, $this->logger);
        $platform->import($input->getOption('startDate'), $input->getOption('endDate'));

        $this->logger->info($input->getOption('platform') . '-import successful.');
    }
}
