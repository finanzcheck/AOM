<?php

namespace Piwik\Plugins\AOM\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AOM\AdWords;
use Piwik\Plugins\AOM\Settings;
use ReportUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CriteoImport
 * @package Piwik\Plugins\AOM\Commands
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 *
 * Example:
 * ./console aom:adwords:import --startDate=2015-07-01 --endDate=2015-07-01
 *
 */
class AdWordsImport extends ConsoleCommand
{
    private $settings;

    protected function configure()
    {
        $this
            ->setName('aom:adwords:import')
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->setDescription('Import data from Google AdWords.');

        $this->settings = new Settings();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $adwords = new AdWords();
        $adwords->import($input->getOption('startDate'), $input->getOption('endDate'));
    }
}
