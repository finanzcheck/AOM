<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Services;

use Piwik\Common;
use Piwik\Db;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\MergerInterface;
use Psr\Log\LoggerInterface;

class PiwikVisitService
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = (null === $logger ? AOM::getLogger() : $logger);
    }

    /**
     * This method is called by the Tracker.end event.
     * It detects if a new visit has been created by the Tracker. If so, it adds the visit to the aom_visits table.
     */
    public function checkForNewVisit()
    {
        // Limit to 10 visits to distribute work (if it has queued up for whatever reason)
        foreach (array_slice($this->getUnprocessedVisits(), 0, 10) as $visit) {
            $this->addNewPiwikVisit($visit);
        }
    }

    /**
     * @return array
     */
    private function getUnprocessedVisits()
    {
        return Db::fetchAll(
            'SELECT *, conv(hex(idvisitor), 16, 10) AS idvisitor '
                . ' FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit > '
                . (Db::fetchOne('SELECT MAX(piwik_idvisit) FROM ' . Common::prefixTable('aom_visits')))
                . ' ORDER BY idvisit ASC'
        );
    }

    /**
     * This method is called by the Tracker.end event.
     * It detects if a new conversion has been created by the Tracker. If so, it adds the conversion-related information
     * to the aom_visits table.
     *
     * TODO: Only consider e-commerce conversions?
     */
    public function checkForNewConversion()
    {
        $c = count($this->getUnprocessedVisits());
        if ($c > 0) {
            $this->logger->info('Skip checking for new conversions as there are still '. $c . ' unprocessed visits.');
            return;
        }

        $latestProcessedConversion = Option::get('Plugin_AOM_LatestProcessedConversion');
        if (false === $latestProcessedConversion) {
            $latestProcessedConversion = 0;
        }

        foreach (Db::query('SELECT idconversion, idvisit, idsite, idorder, revenue '
            . ' FROM ' . Common::prefixTable('log_conversion') . ' WHERE idconversion > ' . $latestProcessedConversion
            . ' ORDER BY idconversion ASC LIMIT 10') // Limit to distribute work (if it has queued up)
                 as $conversion)
        {
            // For every single conversion: Increment visit's conversion count and add revenue
            $result = Db::query(
                'UPDATE ' . Common::prefixTable('aom_visits') . ' SET '
                    . ' conversions = conversions + 1, revenue = revenue + ?, ts_last_update = NOW() '
                    . ' WHERE idsite = ? AND piwik_idvisit = ?',
                [
                    $conversion['revenue'],
                    $conversion['idsite'],
                    $conversion['idvisit'],
                ]
            );
            if (1 === $result->rowCount()) {
                $this->logger->debug(
                    'Added conversion ' . $conversion['idconversion'] . ' (' . $conversion['idorder'] . ') to visit '
                        . $conversion['idvisit'] . ' in aom_visits table.'
                );
            } else {
                $this->logger->error(
                    'Could not add conversion ' . $conversion['idconversion'] . ' (' . $conversion['idorder'] . ') as '
                        . 'visit ' . $conversion['idvisit'] . ' was not found in aom_visits table.'
                );
            }

            Option::set('Plugin_AOM_LatestProcessedConversion', $conversion['idconversion'], 1);
        }
    }

    /**
     * Adds a Piwik visit to the aom_visits table.
     * Conversions and revenue are added to visits by the checkForNewConversion method.
     *
     * @param array $visit
     */
    private function addNewPiwikVisit(array $visit)
    {
        $idsite = $visit['idsite'];
        $date = substr(AOM::convertUTCToLocalDateTime($visit['visit_first_action_time'], $visit['idsite']), 0, 10);

        /** @var MergerInterface $platformMerger */
        $platformMerger = $visit['aom_platform'] ? AOM::getPlatformInstance($visit['aom_platform'], 'Merger') : null;

        // When the visit is coming from a platform (including individual campaigns), check if it has an exact match.
        // An exact match is a match with cost data, i.e. the costs of that match need to be redistributed (again).
        $mergerPlatformDataOfVisit = ($visit['aom_platform'] && $visit['aom_ad_params'])
            ? $platformMerger->getPlatformDataOfVisit(
                $idsite,
                $date,
                $visit['idvisit'],
                is_array(@json_decode($visit['aom_ad_params'], true)) ? json_decode($visit['aom_ad_params'], true) : []
            )
            : null;

        // TODO: Temporarily disables so that we can use the same test data multiple times
        Db::query(
            'INSERT INTO ' . Common::prefixTable('aom_visits')
                . ' (idsite, piwik_idvisit, piwik_idvisitor, unique_hash, first_action_time_utc, '
                . ' date_website_timezone, channel, campaign_data, platform_data, platform_key, ts_created, '
                . ' ts_last_update) '
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $idsite,
                $visit['idvisit'],
                $visit['idvisitor'],
                'piwik-visit-' . $visit['idvisit'],
                $visit['visit_first_action_time'],
                $date,
                self::determineChannel($visit['aom_platform'], $visit['referer_type']),
                json_encode(self::getCampaignData($visit)),
                ($mergerPlatformDataOfVisit ? json_encode($mergerPlatformDataOfVisit->getPlatformData()) : null),
                ($mergerPlatformDataOfVisit ? $mergerPlatformDataOfVisit->getPlatformKey() : null),
            ]
        );
        $this->logger->debug('Added Piwik visit ' . $visit['idvisit'] . ' to aom_visit table.');

        // As this new visit could be directly matched with provided cost, we need to redistribute these cost.
        if ($mergerPlatformDataOfVisit && $mergerPlatformDataOfVisit->getPlatformRowId()) {

            $platformMerger->allocateCostOfPlatformRowId(
                $visit['aom_platform'],
                $mergerPlatformDataOfVisit->getPlatformRowId(),
                $mergerPlatformDataOfVisit->getPlatformKey(),
                $mergerPlatformDataOfVisit->getPlatformData()
            );
        }

        // TODO: Move this to a centralized addOrUpdateVisit-method?
        // Post an event that a visit has been added or updated
        // (other plugins might listen to this event and publish them for example to an external SNS topic)
        Piwik::postEvent('AOM.addOrUpdateVisit', []);    // TODO: Add visit as argument, e.g. [$myFirstArg, &$myRefArg]
    }

    /**
     * @param string $aomPlatform
     * @param string $refererType
     * @return null|string
     */
    private static function determineChannel($aomPlatform, $refererType)
    {
        if ($aomPlatform) {
            return $aomPlatform;
        } elseif (Common::REFERRER_TYPE_DIRECT_ENTRY == $refererType) {
            return 'direct';
        } elseif (Common::REFERRER_TYPE_SEARCH_ENGINE== $refererType) {
            return 'seo';
        } elseif (Common::REFERRER_TYPE_WEBSITE == $refererType) {
            return 'website';
        } elseif (Common::REFERRER_TYPE_CAMPAIGN == $refererType) {
            return 'campaign';
        }

        return null;
    }

    /**
     * @param array $visit
     * @return array
     */
    private static function getCampaignData(array $visit)
    {
        $campaignData = [];

        if (null !== $visit['campaign_name']) {
            $campaignData['campaignName'] = $visit['campaign_name'];
        }
        if (null !== $visit['campaign_keyword']) {
            $campaignData['campaignKeyword'] = $visit['campaign_keyword'];
        }
        if (null !== $visit['campaign_source']) {
            $campaignData['campaignSource'] = $visit['campaign_source'];
        }
        if (null !== $visit['campaign_medium']) {
            $campaignData['campaignMedium'] = $visit['campaign_medium'];
        }
        if (null !== $visit['campaign_content']) {
            $campaignData['campaignContent'] = $visit['campaign_content'];
        }
        if (null !== $visit['campaign_id']) {
            $campaignData['campaignId'] = $visit['campaign_id'];
        }
        if (null !== $visit['referer_name']) {
            $campaignData['refererName'] = $visit['referer_name'];
        }
        if (null !== $visit['referer_url']) {
            $campaignData['refererUrl'] = $visit['referer_url'];
        }

        return $campaignData;
    }
}
