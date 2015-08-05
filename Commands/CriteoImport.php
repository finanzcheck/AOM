<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Commands;

use Piwik\Db;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AOM\Criteo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Example:
 * ./console aom:criteo:import --startDate=2015-06-01 --endDate=2015-06-01
 */
class CriteoImport extends ConsoleCommand
{
    protected function configure()
    {
        $this
            ->setName('aom:criteo:import')
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->setDescription('Import data from Criteo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $criteo = new Criteo();
        $criteo->import($input->getOption('startDate'), $input->getOption('endDate'));
    }
}
