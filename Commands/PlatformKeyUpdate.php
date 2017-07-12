<?php

namespace Piwik\Plugins\AOM\Commands;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Plugins\AOM\SystemSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Example:
 * ./console aom:x
 */
class PlatformKeyUpdate extends ConsoleCommand
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
        $this->logger = AOM::getLogger();

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('aom:x')
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // We might need a little more RAM
        ini_set('memory_limit','2048M');

        foreach (AOM::getPeriodAsArrayOfDates($input->getOption('startDate'), $input->getOption('endDate')) as $date) {

            foreach (Db::query('SELECT id, channel, platform_data '
                . ' FROM ' . Common::prefixTable('aom_visits')
                . ' WHERE date_website_timezone = "' . $date . '" AND platform_key IS NULL AND platform_data IS NOT NULL AND channel IN ("AdWords", "Bing", "Criteo", "Taboola")'
                . ' ORDER BY id DESC')
                     as $visit)
            {
                $platformData = @json_decode($visit['platform_data'], true);

                if ('AdWords' === $visit['channel'] && isset($platformData['network']) && isset($platformData['campaign_id']) && isset($platformData['ad_group_id']) && array_key_exists('keyword_id', $platformData)) {
                    $platformKey = $platformData['network'] . '-' . $platformData['campaign_id'] . '-' . $platformData['ad_group_id'];
                    if ('d' !== $platformData['network']) {
                        $platformKey .= '-' . $platformData['keyword_id'];
                    }

                } elseif ('Bing' === $visit['channel'] && isset($platformData['campaign_id']) && isset($platformData['ad_group_id']) && isset($platformData['keyword_id'])) {
                    $platformKey = $platformData['campaign_id'] . '-' . $platformData['ad_group_id'] . '-' . $platformData['keyword_id'];

                } elseif ('Criteo' === $visit['channel'] && isset($platformData['campaign_id'])) {
                    $platformKey = $platformData['campaign_id'];

                } elseif ('Taboola' === $visit['channel'] && isset($platformData['campaign_id']) && isset($platformData['site_id'])) {
                    $platformKey = $platformData['campaign_id'] . '-' . $platformData['site_id'];

                } else {
                    continue;
                }

                Db::query(
                    'UPDATE ' . Common::prefixTable('aom_visits') . ' SET platform_key = ? WHERE id = ?',
                    [$platformKey, $visit['id'],]
                );
                $this->logger->info('Updated ' . $visit['channel'] . ' visit with platform key: ' . $platformKey);

            }
        }
    }
}
