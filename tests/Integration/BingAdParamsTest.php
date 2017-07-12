<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\tests\System;

use Piwik\Plugins\AOM\Columns\AdParams;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Tracker\Visitor;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik;
use Piwik\Tests\Framework\Fixture;

/**
 * @group AOM
 * @group AOM_BingAdParams
 * @group AOM_Integration
 * @group Plugins
 */
class BingAdParamsTest extends IntegrationTestCase
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
        $this->adParams = new AdParams();
    }

    public function testOnNewVisitUrlWithAllRequiredParams()
    {
        $request = new Request([
            'idsite' => 1,
            'url' => 'http://www.example.com/lp/sofortkredit/?aom_platform=Bing&aom_campaign_id=14630904343'
                . '&aom_ad_group_id=9810883196&aom_target_id=kwd-9767559&amount=8000&term=84&purpose=other',
            'urlref' => 'bing.de',
        ]);

        $visitor = new Visitor(new VisitProperties());
        /** @var Action $action */
        $action = $this->getMockBuilder('\Piwik\Tracker\Action')
            ->disableOriginalConstructor()->getMock();

        $result = $this->adParams->onNewVisit($request, $visitor, $action);
        $this->assertEquals(
            '{"platform":"Bing","campaignId":"14630904343","adGroupId":"9810883196","targetId":"kwd-9767559"}',
            $result
        );
    }

    public function testOnNewVisitUrlWithMissingParamCampaignId()
    {
        $request = new Request([
            'idsite' => 1,
            'url' => 'http://www.example.com/lp/sofortkredit/?aom_platform=Bing&baseline=1463090434342747916'
                . '&aom_ad_group_id=9810883196&aom_feed_item_id=&aom_target_id=kwd-97675593&aom_creative=48726498716'
                . '&aom_placement=&aom_target=&aom_network=g&aom_device=m&aom_ad_position=1t1&aom_loc_physical=9042859'
                . '&aom_loc_Interest=&amount=8000&term=84&purpose=other&bid=dc0af63330ae5b1857d7862089d3fc0d',
            'urlref' => 'bing.de',
        ]);

        $visitor = new Visitor(new VisitProperties());
        /** @var Action $action */
        $action = $this->getMockBuilder('\Piwik\Tracker\Action')
            ->disableOriginalConstructor()->getMock();

        $result = $this->adParams->onNewVisit($request, $visitor, $action);
        $this->assertEquals('null', $result);
    }
}

BingAdParamsTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
