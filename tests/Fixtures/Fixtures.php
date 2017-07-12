<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */

namespace Piwik\Plugins\AOM\tests\Fixtures;

use Piwik;
use Piwik\Site;

class Fixtures extends BasicFixtures
{
    public $dateTime = '2015-12-01 01:23:45';
    public $idSite = 1;

    const THIS_PAGE_VIEW_IS_GOAL_CONVERSION = 'this is a goal conversion';

    public function setUp()
    {
        parent::setUp();

        $this->trackCampaignVisits($this->dateTime);
    }

    /**
     * @param string $dateTime
     */
    public function trackCampaignVisits($dateTime)
    {
        $t = self::getTracker($this->idSite, $dateTime, $defaultInit = true, $useLocal = false);

        $this->trackVisitorWithMultipleVisitsWithSameAdParams($t, $dateTime);
        $this->trackVisitorWithMultipleVisitsWithDifferentAdParams($t, $dateTime);
        $this->trackVisitorWithMultipleVisitsFromVariousPlatforms($t, $dateTime);

        // TODO: Add additional test cases for all API endpoints!
    }

    /**
     * A visitor with visits immediately following each other with consistent ad data should not create new visits.
     *
     * @param \PiwikTracker $t
     * @param $dateTime
     */
    protected function trackVisitorWithMultipleVisitsWithSameAdParams(\PiwikTracker $t, $dateTime)
    {
        $t->setUserId('d9857faa8002a8eebd0bc75b63dfacef');

        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://www.example.com/?aom_platform=Criteo&aom_campaign_id=14340');
        self::checkResponse($t->doTrackPageView('Viewing homepage, will be recorded as a visit from campaign'));

        // this should be the same visit as the ad data does not change
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl('http://www.example.com/?aom_platform=Criteo&aom_campaign_id=14340');
        self::checkResponse($t->doTrackPageView('Second visit, should belong to existing visit'));

        // We change the order of the params but no the contents, thus this should still be the same visit
        $this->moveTimeForward($t, 0.2, $dateTime);
        $t->setUrl('http://www.example.com/?aom_campaign_id=14340&aom_platform=Criteo');
        self::checkResponse($t->doTrackPageView('Third visit, should belong to existing visit'));
    }

    /**
     * A visitor with visits immediately following each other with inconsistent ad data should create new visits.
     *
     * @param \PiwikTracker $t
     * @param $dateTime
     */
    protected function trackVisitorWithMultipleVisitsWithDifferentAdParams(\PiwikTracker $t, $dateTime)
    {
        $t->setUserId('919c1aed2f5b1f79d27951b0b309ff42');

        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://www.example.com/?aom_platform=Criteo&aom_campaign_id=4711');
        self::checkResponse($t->doTrackPageView('Viewing homepage, will be recorded as a visit from campaign'));

        // this should start a new visit as the ad data has changed
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl('http://www.example.com/?aom_platform=Criteo&aom_campaign_id=84571');
        self::checkResponse($t->doTrackPageView('Second visit, should start a new visit'));
    }

    /**
     * @param \PiwikTracker $t
     * @param $dateTime
     */
    protected function trackVisitorWithMultipleVisitsFromVariousPlatforms(\PiwikTracker $t, $dateTime)
    {
        // AdWords
        $t->setUserId('c1aed2f5b1f79d27951b0b309ff42919');
        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://www.example.com/?gclid=CI2o27bV2NMCFZYK0wodip4IZA');
        self::checkResponse($t->doTrackPageView('Visit from AdWords'));

        // Bing
        $t->setUserId('aed2f5b1f79d27951b0b309ff42919c1');
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl(
            'http://www.example.com/?aom_platform=Bing&aom_campaign_id=190561279&aom_ad_group_id=2029114499'
                . '&aom_order_item_id=40414589411&aom_target_id=40414589411&aom_ad_id=5222037942'
        );
        self::checkResponse($t->doTrackPageView('Visit from Bing'));

        // Criteo
        $t->setUserId('d2f5b1f79d27951b0b309ff42919c1ae');
        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://www.example.com/?aom_platform=Criteo&aom_campaign_id=14340');
        self::checkResponse($t->doTrackPageView('Visit from Criteo'));

        // Facebook Ads
        // TODO: Facebook Ads is currently not working!
//        $t->setUserId('f5b1f79d27951b0b309ff42919c1aed2');
//        $this->moveTimeForward($t, 0.2, $dateTime);
//        $t->setUrl(
//            'http://www.example.com/?aom_platform=FacebookAds&aom_campaign_id=4160286035775&aom_adset_id=6028603577541'
//            . '&aom_ad_id=5760286037541'
//        );
//        self::checkResponse($t->doTrackPageView('Visit from FacebookAds'));

        // Taboola
        $t->setUserId('taa1fb309ff42919c1aed279d27951b0');
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl(
            'http://www.example.com/?aom_platform=Taboola&aom_campaign_id=527486&aom_site_id=stroeer-smb-gamona'
        );
        self::checkResponse($t->doTrackPageView('Visit from Taboola'));
    }
}
