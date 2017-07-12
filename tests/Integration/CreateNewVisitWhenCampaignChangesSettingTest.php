<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\tests\Integration;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\tests\Fixtures\Fixtures;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group AOM
 * @group AOM_CreateNewVisitWhenCampaignChangesSettingTest
 * @group AOM_Integration
 * @group Plugins
 */
class CreateNewVisitWhenCampaignChangesSettingTest extends IntegrationTestCase
{
    /**
     * @var Fixtures
     */
    public static $fixture = null; // initialized below class definition

    public function setUp()
    {
        parent::setUp();
    }

    /**
     * Ensures that a new visit is being created when ad params change and the "createNewVisitWhenCampaignChanges"
     * setting is enabled.
     */
    public function testNewVisitWhenCreateNewVisitWhenCampaignChangesIsEnabled()
    {
        $userId = '11111111111111111111111111111111';
        $this->createTwoPageviewsWithDifferentAdParams($userId);

        // As second action should NOT belong to first visit, the first visit's visit_total_interactions should be 1
        $this->assertEquals(
            1,
            Db::fetchOne(
                'SELECT visit_total_interactions FROM ' . Common::prefixTable('log_visit') . ' WHERE user_id = ?',
                [$userId]
            )
        );
    }

    /**
     * Ensures that no new visit is being created when ad params change but the "createNewVisitWhenCampaignChanges"
     * setting is disabled.
     *
     * TODO: We must find a way to change the basic fixtures, i.e. change the plugin configuration during runtime
     */
//    public function testNoNewVisitWhenCreateNewVisitWhenCampaignChangesIsDisabled()
//    {
//        $userId = '22222222222222222222222222222222';
//        $this->createTwoPageviewsWithDifferentAdParams($userId);
//
//        // As second action should belong to first visit, the first visit's visit_total_interactions should be 2
//        $this->assertEquals(
//            2,
//            Db::fetchOne(
//                'SELECT visit_total_interactions FROM ' . Common::prefixTable('log_visit') . ' WHERE user_id = ?',
//                [$userId]
//            )
//        );
//    }

    /**
     * @param string $userId
     */
    private function createTwoPageviewsWithDifferentAdParams($userId)
    {
        $dateTime = '2017-02-01 03:23:48';

        $fixture = CreateNewVisitWhenCampaignChangesSettingTest::$fixture;

        $tracker = $fixture->getTracker(1, $dateTime, $defaultInit = true, $useLocal = false);
        $tracker->setTokenAuth($fixture->tokenAuth);


        $tracker->setUserId($userId);

        $fixture->moveTimeForward($tracker, 0.1, $dateTime);
        $tracker->setUrl('http://www.example.com/?aom_platform=Criteo&aom_campaign_id=1234');
        $tracker->doTrackPageView('Viewing homepage');

        $fixture->moveTimeForward($tracker, 0.1, $dateTime);
        $tracker->setUrl('http://www.example.com/?aom_platform=Criteo&aom_campaign_id=9876');
        $tracker->doTrackPageView('Viewing homepage');
    }
}

CreateNewVisitWhenCampaignChangesSettingTest::$fixture = new Fixtures();
