<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Settings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Example:
 * ./console aom:merge --platform=AdWords --startDate=2015-12-20 --endDate=2015-12-20
 */
class PlatformMerge extends ConsoleCommand
{
    protected function configure()
    {
        $this
            ->setName('aom:merge')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED)
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->setDescription('Import an advertising platform\'s data for a specific period.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!in_array($input->getOption('platform'), AOM::getPlatforms())) {
            $output->writeln('Platform "' . $input->getOption('platform') . '" is not supported.');
            $output->writeln('Platform must be one of: ' . implode(', ', AOM::getPlatforms()));
            return;
        }

        // Is platform active?
        $settings = new Settings();
        if (!$settings->{'platform' . $input->getOption('platform') . 'IsActive'}->getValue()) {
            $output->writeln('Platform "' . $input->getOption('platform') . '" is not active.');
            return;
        }

        $platform = AOM::getPlatformInstance($input->getOption('platform'));
        $platform->merge($input->getOption('startDate'), $input->getOption('endDate'));

        $output->writeln($input->getOption('platform') . '-merge for period from '
            . $input->getOption('startDate') . ' until ' . $input->getOption('endDate') . ' successful.');
    }
}
