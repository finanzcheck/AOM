<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AOM\AdWords;
use ReportUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Example:
 * ./console aom:adwords:import --startDate=2015-07-01 --endDate=2015-07-01
 */
class AdWordsImport extends ConsoleCommand
{
    protected function configure()
    {
        $this
            ->setName('aom:adwords:import')
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->setDescription('Import data from Google AdWords.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $adwords = new AdWords();
        $adwords->import($input->getOption('startDate'), $input->getOption('endDate'));
    }
}
