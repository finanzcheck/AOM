<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Commands;

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
 * ./console aom:replenish-visits --startDate=2016-01-06 --endDate=2016-01-06
 */
class ReplenishVisits extends ConsoleCommand
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

    protected function configure()
    {
        $this
            ->setName('aom:replenish-visits')
            ->addOption('startDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->addOption('endDate', null, InputOption::VALUE_REQUIRED, 'YYYY-MM-DD')
            ->setDescription(
                'Allocates platform costs to Piwik visits and stores them in piwik_aom_visits for further processing.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!in_array('AdvancedCampaignReporting', Manager::getInstance()->getInstalledPluginsName())) {
            $this->logger->error('Plugin "AdvancedCampaignReporting" must be installed and activated.');
            exit;
        }

        foreach (AOM::getPeriodAsArrayOfDates($input->getOption('startDate'), $input->getOption('endDate')) as $date) {
            $this->processDate($date);
        }
    }

    /**
     * @param string $date YYYY-MM-DD
     * @throws \Exception
     */
    private function processDate($date)
    {
        // Clean up replenished data
        Db::deleteAllRows(Common::prefixTable('aom_visits'), 'WHERE date_website_timezone = ?', 'id', 100000, [$date,]);

        // Get visits
        $visits = $this->getVisits($date);
        $totalPiwikVisits = count($visits);


        // Process every platform
        foreach (AOM::getPlatforms() as $platformName) {

            $this->logger->info('Processing ' . $platformName . '...');

            $platform = AOM::getPlatformInstance($platformName);

            // Get Piwik visits that came from this platform and that we have assigned to this platform
            $platformVisits = array_filter($visits, function($visit) use ($platformName) {
                return ($visit['aom_platform'] == $platformName);
            });

            // Get platform costs
            $costs = $this->getPlatformCosts($platform, $date);

            $clicksAccordingToPlatform = 0;
            $clicksAccordingToPlatformUnmerged = 0;
            $totalMatchingVisits = 0;
            $totalCreatedVisits = 0;
            $totalCosts = 0;

            foreach ($costs as $cost) {

                // Get Piwik visits for this specific costs
                $matchingVisits = array_filter($platformVisits, function($visit) use ($cost) {
                    return ($visit['aom_platform_row_id'] == $cost['id']);
                });

                if (count($matchingVisits) > 0) {

                    // Add matched Piwik visits with calculated CPC and platform data to database
                    foreach ($matchingVisits as $matchingVisit) {
                        $this->addVisit(
                            $cost['idsite'],
                            $matchingVisit['idvisit'],
                            AOM::convertUTCToLocalDateTime($matchingVisit['visit_first_action_time'], $cost['idsite']),
                            $date,
                            $this->determineChannel($platformName, $matchingVisit['referer_type'], $matchingVisit['campaign_name']),
                            $matchingVisit['campaign_data'],
                            json_encode($cost),
                            ($cost['cost'] / count($matchingVisits))    // Calculate CPC based on Piwik visits
                        );

                        $totalMatchingVisits++;
                    }

                    // Remove matching visits from $visits
                    $visits = array_filter($visits, function($visit) use ($cost) {
                        return ($visit['aom_platform_row_id'] != $cost['id']);
                    });

                } else {

                    // Costs but no match -> create visit (so that total costs will match)
                    $this->addVisit(
                        $cost['idsite'],
                        null,
                        AOM::convertLocalDateTimeToUTC($date . ' 00:00:00', Site::getTimezoneFor($cost['idsite'])),
                        $date,
                        $platformName,
                        null,
                        json_encode($cost),
                        $cost['cost']
                    );

                    $totalCreatedVisits++;
                    $clicksAccordingToPlatformUnmerged += $cost['clicks'];
                }

                $clicksAccordingToPlatform += $cost['clicks'];
                $totalCosts += $cost['cost'];
            }


            // Platform stats
            $this->logger->info(
                sprintf(
                    '%d Piwik %s visits (based on aom_platform) resulted in %d %s visits.' . PHP_EOL
                        . '%s reported %d records (with at least 1 click or cost > 0) with %d clicks.' . PHP_EOL
                        . '%d Piwik visits matched (based on aom_platform_row_id), but %d visits had to be created for %d unmerged clicks.' . PHP_EOL
                        . 'Total costs reported vs. total costs of all resulting visits are %f vs. %f.' . PHP_EOL,
                    count($platformVisits),
                    $platformName,
                    ($totalMatchingVisits + $totalCreatedVisits),
                    $platformName,
                    $platformName,
                    count($costs),
                    $clicksAccordingToPlatform,
                    $totalMatchingVisits,
                    $totalCreatedVisits,
                    $clicksAccordingToPlatformUnmerged,
                    $totalCosts,
                    DB::fetchOne(
                        'SELECT SUM(cost) FROM ' . Common::prefixTable('aom_visits')
                            . ' WHERE date_website_timezone = ? AND channel = ?',
                        [
                            $date,
                            $platformName,
                        ]
                    )
                )
            );
        }

        // Add remaining Piwik visits (visits without aom_platform_row_id) to piwik_aom_visits.
        $this->logger->info(
            'Will add ' . count($visits) . ' remaining visits now (Piwik visits without aom_platform_row_id).'
        );

        foreach ($visits as $visit) {
            $this->addVisit(
                $visit['idsite'],
                $visit['idvisit'],
                $visit['visit_first_action_time'],
                $date,
                $this->determineChannel(null, $visit['referer_type'], $visit['campaign_name']),
                $visit['campaign_data']
            );
        }

        // Final stats
        $totalResultingVisits = DB::fetchOne(
            'SELECT COUNT(*) FROM ' . Common::prefixTable('aom_visits') . ' WHERE date_website_timezone = ?',
            [$date,]
        );
        $this->logger->info(
            'Replenished ' . $totalPiwikVisits . ' Piwik visits to ' . $totalResultingVisits . ' visits.'
        );
    }

    /**
     * Returns the visits of all websites that occurred at the given date based on the website's timezone.
     *
     * @param string $date YYYY-MM-DD
     * @return array
     * @throws \Exception
     */
    private function getVisits($date)
    {
        // We assume that the website's timezone matches the timezone of all advertising platforms.
        $visits = [];

        // We get visits per website to consider the website's individual timezones.
        foreach (APISitesManager::getInstance()->getAllSites() as $site) {
            $visits = array_merge(
                $visits,
                DB::fetchAll(
                    'SELECT
                            idvisit,
                            idsite,
                            visit_first_action_time,
                            CASE referer_type
                                WHEN 1 THEN "direct"
                                WHEN 2 THEN "search_engine"
                                WHEN 3 THEN "website"
                                WHEN 6 THEN "campaign"
                                ELSE ""
                            END AS referer_type,
                            referer_name,
                            referer_keyword,
                            referer_url,
                            campaign_name,
                            campaign_keyword,
                            campaign_source,
                            campaign_medium,
                            campaign_content,
                            campaign_id,
                            aom_platform,
                            aom_platform_row_id
                        FROM  ' . Common::prefixTable('log_visit') . '
                        WHERE idsite = ? AND visit_first_action_time >= ? AND visit_first_action_time <= ?',
                    [
                        $site['idsite'],
                        AOM::convertLocalDateTimeToUTC($date . ' 00:00:00', $site['timezone']),
                        AOM::convertLocalDateTimeToUTC($date . ' 23:59:59', $site['timezone']),
                    ]
                )
            );
        }

        foreach ($visits as &$visit) {
            $visit['campaign_data'] = [];
            if (null !== $visit['campaign_name']) {
                $visit['campaign_data']['campaignName'] = $visit['campaign_name'];
            }
            if (null !== $visit['campaign_keyword']) {
                $visit['campaign_data']['campaignKeyword'] = $visit['campaign_keyword'];
            }
            if (null !== $visit['campaign_source']) {
                $visit['campaign_data']['campaignSource'] = $visit['campaign_source'];
            }
            if (null !== $visit['campaign_medium']) {
                $visit['campaign_data']['campaignMedium'] = $visit['campaign_medium'];
            }
            if (null !== $visit['campaign_content']) {
                $visit['campaign_data']['campaignContent'] = $visit['campaign_content'];
            }
            if (null !== $visit['campaign_id']) {
                $visit['campaign_data']['campaignId'] = $visit['campaign_id'];
            }
            if (null !== $visit['referer_name']) {
                $visit['campaign_data']['refererName'] = $visit['referer_name'];
            }
            if (null !== $visit['referer_url']) {
                $visit['campaign_data']['refererUrl'] = $visit['referer_url'];
            }
        }

        $this->logger->info('Got ' . count($visits) . ' Piwik visits.');

        return $visits;
    }

    /**
     * Returns the platform's costs for the given date.
     *
     * @param PlatformInterface $platform
     * @param string $date YYYY-MM-DD
     * @return array
     */
    private function getPlatformCosts(PlatformInterface $platform, $date)
    {
        return DB::fetchAll(
            'SELECT * FROM ' . $platform->getDataTableName()
                . ' WHERE date = ? AND (clicks > 0 OR cost > 0)',   // Some platform's might create irrelevant rows!
            [
                $date,
            ]
        );
    }

    /**
     * Determines the channel based on various tracking information.
     *
     * @param null|string $platform
     * @param string $refererType
     * @param null|string $campaignName
     * @return null|string
     */
    private function determineChannel($platform, $refererType, $campaignName)
    {
        if (null !== $platform) {
            return $platform;
        }

        // TODO: Return white-listed campaign names when $refererType is "campaign"?!

        return $refererType;
    }

    /**
     * Stores a visit with allocated costs and various platform data in piwik_aom_visits.
     *
     * TODO: Collect queries and perform multiple queries at once to improve performance.
     *
     * @param int $siteId Website ID
     * @param int $visitId Piwik visit ID
     * @param string $firstActionTimeUTC The UTC datetime of the visit's first action
     * @param string $dateWebsiteTimezone The date in the website's timezone
     * @param string|null $channel The platform (or source) this visit came from (can be null)
     * @param string|null $campaignData
     * @param string|null $platformData JSON platform data (can be null)
     * @param float|null $cost The price paid to the advertising platform for this specific visit
     * @throws \Exception
     */
    private function addVisit(
        $siteId,
        $visitId,
        $firstActionTimeUTC,
        $dateWebsiteTimezone,
        $channel = null,
        $campaignData = null,
        $platformData = null,
        $cost = null
    )
    {
        Db::query(
            'INSERT INTO ' . Common::prefixTable('aom_visits')
                . ' (idsite, piwik_idvisit, first_action_time_utc, date_website_timezone, channel, campaign_data,
                     platform_data, cost, ts_created) '
                . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $siteId,
                $visitId,
                $firstActionTimeUTC,
                $dateWebsiteTimezone,
                $channel,
                json_encode($campaignData),
                $platformData,
                $cost,
            ]
        );
    }
}
