<?php

namespace Piwik\Plugins\AOM\Commands;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AOM\Criteo;
use Piwik\Plugins\AOM\Settings;
use SoapClient;
use SoapFault;
use SoapHeader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CriteoImport
 * @package Piwik\Plugins\AOM\Commands
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 *
 * Example:
 * ./console aom:criteo:import --startDate=2015-06-01 --endDate=2015-06-01
 *
 */
class CriteoImport extends ConsoleCommand
{

    protected function configure()
    {
        $this
            ->setName('aom:criteo:import')
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $criteo = new Criteo();
        $criteo->import($input->getOption('startDate'), $input->getOption('endDate'));
    }
}
