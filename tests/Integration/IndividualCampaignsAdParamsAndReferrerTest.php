<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\tests\Integration;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Columns\AdParams;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Tracker\Visitor;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik;
use Piwik\Tests\Framework\Fixture;

/**
 * @group AOM
 * @group AOM_IndividualAdParamsAndReferrerTest
 * @group AOM_Integration
 * @group Plugins
 */
class IndividualCampaignsAdParamsAndReferrerTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    /**
     * @var AdParams
     */
    private $adParams;

    public function setUp()
    {
        parent::setUp();

        foreach ([
                     [
                         'date' => '2015-12-01',
                         'campaign' => 'Newsletter December 2015',
                         'campaignId' => '596cd4613fd8d3.96433311',
                         'paramsSubstring' => 'utm_campaign=newsletter-december-2015',
                         'referrerSubstring' => '',
                         'cost' => 10.00,
                     ],
                     [
                         'date' => '2015-12-02',
                         'campaign' => 'Newsletter December 2015',
                         'campaignId' => '596cd4613fd8d3.96433311',
                         'paramsSubstring' => 'utm_campaign=newsletter-december-2015',
                         'referrerSubstring' => '',
                         'cost' => 10.00,
                     ],
                     [
                         'date' => '2015-12-01',
                         'campaign' => 'ABC link cooperation December 2015',
                         'campaignId' => '596d960b633c14.64938983',
                         'paramsSubstring' => '',
                         'referrerSubstring' => 'www.abc.com/sponsored-article',
                         'cost' => 80.00,
                     ]
                 ] as $individualCampaign
        ) {
            Db::query(
                'INSERT INTO ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS)
                    . ' (idsite, date, campaign_id, campaign, params_substring, referrer_substring, cost, created_by, 
                         ts_created) '
                    . 'VALUES (1, ?, ?, ?, ?, ?, ?, 1, NOW())',
                [
                    $individualCampaign['date'],
                    $individualCampaign['campaignId'],
                    $individualCampaign['campaign'],
                    $individualCampaign['paramsSubstring'],
                    $individualCampaign['referrerSubstring'],
                    $individualCampaign['cost'],
                ]
            );
        }

        $this->adParams = new AdParams();
    }

    /**
     * Test exactly one match based on params substring
     */
    public function testOnNewVisitParamsSubstringMatch()
    {
        $request = new Request([
            'idsite' => 1,
            'url' => 'http://www.example.com/?utm_campaign=newsletter-december-2015',
            'urlref' => 'https://www.web.de/...',
        ]);

        // The fixtures are long ago in the past, i.e. we must create a request at that time to get a match
        $request->setCurrentTimestamp(strtotime('2015-12-01 09:00:00'));

        $visitor = new Visitor(new VisitProperties());
        /** @var Action $action */
        $action = Action::factory($request);

        $result = $this->adParams->onNewVisit($request, $visitor, $action);

        $this->assertEquals(
            '{"platform":"IndividualCampaigns","campaignId":"596cd4613fd8d3.96433311","campaignName":"Newsletter December 2015"}',
            $result
        );
    }

    /**
     * Test exactly one match based on referrer substring
     */
    public function testOnNewVisitReferrerSubstringMatch()
    {
        $request = new Request([
            'idsite' => 1,
            'url' => 'http://www.example.com/lp',
            'urlref' => 'https://www.abc.com/sponsored-article?utm_source=ABC',
        ]);

        // The fixtures are long ago in the past, i.e. we must create a request at that time to get a match
        $request->setCurrentTimestamp(strtotime('2015-12-01 09:00:00'));

        $visitor = new Visitor(new VisitProperties());
        /** @var Action $action */
        $action = Action::factory($request);

        $result = $this->adParams->onNewVisit($request, $visitor, $action);

        $this->assertEquals(
            '{"platform":"IndividualCampaigns","campaignId":"596d960b633c14.64938983","campaignName":"ABC link cooperation December 2015"}',
            $result
        );
    }

    /**
     * Test no match
     */
    public function testOnNewVisitNoMatch()
    {
        $request = new Request([
            'idsite' => 1,
            'url' => 'http://www.example.com/',
            'urlref' => 'https://www.web.de/...',
        ]);

        // The fixtures are long ago in the past, i.e. we must create a request at that time to get a match
        $request->setCurrentTimestamp(strtotime('2015-12-01 09:00:00'));

        $visitor = new Visitor(new VisitProperties());
        /** @var Action $action */
        $action = Action::factory($request);

        $result = $this->adParams->onNewVisit($request, $visitor, $action);

        $this->assertEquals('null', $result);
    }

    /**
     * Tests that other platforms are checked for matches before individual campaigns are
     */
    public function testOnNewVisitParamsSubstringMatchOtherPlatformsHaveHigherPriority()
    {
        $request = new Request([
            'idsite' => 1,
            'url' => 'http://www.example.com/?aom_platform=Taboola&aom_campaign_id=1&aom_site_id=2'
                . '&utm_campaign=newsletter-december-2015',
            'urlref' => 'https://www.web.de/...',
        ]);

        // The fixtures are long ago in the past, i.e. we must create a request at that time to get a match
        $request->setCurrentTimestamp(strtotime('2015-12-01 09:00:00'));

        $visitor = new Visitor(new VisitProperties());
        /** @var Action $action */
        $action = Action::factory($request);

        $result = $this->adParams->onNewVisit($request, $visitor, $action);

        $this->assertEquals(
            '{"platform":"Taboola","campaignId":"1","siteId":"2"}',
            $result
        );
    }
}

IndividualCampaignsAdParamsAndReferrerTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
