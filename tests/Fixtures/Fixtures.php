<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\Fixtures;

use Piwik\Plugins\AOM\Settings;
use Piwik\Tests\Framework\Fixture;
use Piwik;
use Piwik\Date;

class Fixtures extends Fixture
{
    public $dateTime = '2015-12-01 01:23:45';
    public $idSite = 1;

    const THIS_PAGE_VIEW_IS_GOAL_CONVERSION = 'this is a goal conversion';

    public function setUp()
    {
        $this->setUpWebsite();

        // since we're changing the list of activated plugins, we have to make sure file caches are reset
        Piwik\Cache::flushAll();

        // TODO: Write tests with plugin being disabled (see AdvancedCampaignReporting)
        $testVars = new Piwik\Tests\Framework\TestingEnvironmentVariables();
        $testVars->disableAOM = false;
        $testVars->save();

        $settings = new Settings();
        $settings->paramPrefix->setValue('aom');
        $settings->platformAdWordsIsActive->setValue(true);
        $settings->platformBingIsActive->setValue(true);
        $settings->platformCriteoIsActive->setValue(true);
        $settings->platformFacebookAdsIsActive->setValue(true);
        $settings->save();

        $this->trackCampaignVisits($this->dateTime);
    }

    public function tearDown()
    {
        // empty
    }

    private function setUpWebsite()
    {
        $idSite = self::createWebsite($this->dateTime, $ecommerce = 1);
        $this->assertTrue($idSite === $this->idSite);

//        $this->idGoal1 = \Piwik\Plugins\Goals\API::getInstance()->addGoal(
//            $this->idSite, 'title match', 'title', self::THIS_PAGE_VIEW_IS_GOAL_CONVERSION, 'contains',
//            $caseSensitive = false, $revenue = 10, $allowMultipleConversions = true
//        );
//
//        $this->idGoal2 = \Piwik\Plugins\Goals\API::getInstance()->addGoal(
//            $this->idSite, 'title match', 'manually', '', 'contains'
//        );
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
        $t->setUrl('http://example.com/?aom_platform=Criteo&aom_campaign_id=14340');
        self::checkResponse($t->doTrackPageView('Viewing homepage, will be recorded as a visit from campaign'));

        // this should be the same visit as the ad data does not change
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl('http://example.com/?aom_platform=Criteo&aom_campaign_id=14340');
        self::checkResponse($t->doTrackPageView('Second visit, should belong to existing visit'));

        // we change the order of the params but no the ad data, thus this should still be the same visit
        $this->moveTimeForward($t, 0.2, $dateTime);
        $t->setUrl('http://example.com/?aom_campaign_id=14340&aom_platform=Criteo');
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
        $t->setUrl('http://example.com/?aom_platform=Criteo&aom_campaign_id=4711');
        self::checkResponse($t->doTrackPageView('Viewing homepage, will be recorded as a visit from campaign'));

        // this should start a new visit as the ad data has changed
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl('http://example.com/?aom_platform=Criteo&aom_campaign_id=84571');
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
        $t->setUrl(
            'http://example.com/?aom_platform=AdWords&aom_campaign_id=184418636&aom_ad_group_id=9794351276'
            . '&aom_feed_item_id=&aom_target_id=kwd-118607649&aom_creative=47609133356&aom_placement=&aom_target='
            . '&aom_network=g&aom_device=m&aom_ad_position=1t2&aom_loc_physical=20228&aom_loc_Interest=1004074'
        );
        self::checkResponse($t->doTrackPageView('Visit from AdWords'));

        // Bing
        $t->setUserId('aed2f5b1f79d27951b0b309ff42919c1');
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl(
            'http://example.com/?aom_platform=Bing&aom_campaign_id=190561279&aom_ad_group_id=2029114499'
            . '&aom_order_item_id=40414589411&aom_target_id=40414589411&aom_ad_id=5222037942'
        );
        self::checkResponse($t->doTrackPageView('Visit from Bing'));

        // Criteo
        $t->setUserId('d2f5b1f79d27951b0b309ff42919c1ae');
        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://example.com/?aom_platform=Criteo&aom_campaign_id=14340');
        self::checkResponse($t->doTrackPageView('Visit from Criteo'));

        // Facebook Ads
        $t->setUserId('f5b1f79d27951b0b309ff42919c1aed2');
        $this->moveTimeForward($t, 0.2, $dateTime);
        $t->setUrl(
            'http://example.com/?aom_platform=FacebookAds&aom_campaign_id=4160286035775&aom_adset_id=6028603577541'
            . '&aom_ad_id=5760286037541'
        );
        self::checkResponse($t->doTrackPageView('Visit from Bing'));
    }

    /**
     * @param \PiwikTracker $t
     * @param $hourForward
     * @param $dateTime
     * @throws \Exception
     */
    protected function moveTimeForward(\PiwikTracker $t, $hourForward, $dateTime)
    {
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour($hourForward)->getDatetime());
    }

    public function provideContainerConfig()
    {
        $testVars = new Piwik\Tests\Framework\TestingEnvironmentVariables();

        return [
            'observers.global' => \DI\add([
                ['Environment.bootstrapped', function () use ($testVars) {
                    $plugins = Piwik\Config::getInstance()->Plugins['Plugins'];
                    $index = array_search('AOM', $plugins);

                    if ($testVars->disableAOM) {
                        if ($index !== false) {
                            unset($plugins[$index]);
                        }
                    } else {
                        if ($index === false) {
                            $plugins[] = 'AOM';
                        }
                    }
                    Piwik\Config::getInstance()->Plugins['Plugins'] = $plugins;
                }],
            ]),
        ];
    }
}
