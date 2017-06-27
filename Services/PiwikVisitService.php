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
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\MergerInterface;

class PiwikVisitService
{
    /**
     * This method is called by the Tracker.end event.
     * It detects if a new visit has been created by the Tracker. If so, it adds the visit to the aom_visits table.
     */
    public static function checkForNewVisit()
    {
        foreach (Db::query('SELECT *, conv(hex(idvisitor), 16, 10) AS idvisitor '
            . ' FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit > '
            . (Db::fetchOne('SELECT MAX(piwik_idvisit) FROM ' . Common::prefixTable('aom_visits')))
            . ' ORDER BY idvisit ASC LIMIT 10') // Limit to distribute work (if it has queued up for whatever reason)
            as $visit)
        {
            self::addNewPiwikVisit($visit);
        }
    }

    public static function checkForNewConversion()
    {
        // TODO: Compare latest conversion against internally stored value
        // TODO: For every single conversion: Increment visit's conversion count and add revenue
    }

    /**
     * Adds a Piwik visit to the aom_visits table.
     * Conversions and revenue are added to visits by the checkForNewConversion method.
     *
     * @param array $visit
     */
    private static function addNewPiwikVisit(array $visit)
    {
        /** @var MergerInterface $platformMerger */
        $platformMerger = $visit['aom_platform'] ? AOM::getPlatformInstance($visit['aom_platform'], 'Merger') : null;

        // When the visit is coming from a platform (including individual campaigns), check if it could be merged.
        $platformData = null;
        $isMerged = false;
        if ($visit['aom_platform']) {
            list($platformData, $isMerged) = $platformMerger->getPlatformDataOfVisit($visit);

            var_dump($visit);
            var_dump($platformData);
            die();
        }


        Db::query(
            'INSERT INTO ' . Common::prefixTable('aom_visits')
                . ' (idsite, piwik_idvisit, piwik_idvisitor, unique_hash, first_action_time_utc, '
                . ' date_website_timezone, channel, campaign_data, platform_data, ts_created, ts_last_update) '
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $visit['idsite'],
                $visit['idvisit'],
                $visit['idvisitor'],
                'piwik-visit-' . $visit['idvisit'],
                $visit['visit_first_action_time'],
                substr(AOM::convertUTCToLocalDateTime($visit['visit_first_action_time'], $visit['idsite']), 0, 10),
                self::determineChannel($visit['aom_platform'], $visit['referer_type']),
                json_encode(self::getCampaignData($visit)),
                json_encode($platformData),
            ]
        );

        // As this is a new visit that has been merged with existing cost records, we do need to redistribute costs.
        if ($isMerged) {
            // TODO: Redistribute costs (this should be a more centralized method, perhaps in every single platform?)
        }

//        var_dump('ADDED!');

        // TODO: What about marketing data, if this visit is older than the latest import?

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
