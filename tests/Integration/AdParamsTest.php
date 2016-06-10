<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\System;

use Piwik\Plugins\AOM\Columns\AdData;
use Piwik\Plugins\AOM\Columns\AdParams;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Tracker\Visitor;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik;
use Piwik\Db;
use Piwik\Tests\Framework\Fixture;
/**
 * @group AOM
 * @group AOM_Integration
 */
class AdParamsTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    /** @var AdData */
    private $data;

    public function setUp()
    {
        parent::setUp();
        $this->data = new AdParams();
    }

    public function testOnNewVisitUrl()
    {
        $params = ['idsite' => 1];

        $request = new Request($params);
        $visitor = new Visitor(new VisitProperties());
        $action = $this->getMockBuilder('\Piwik\Tracker\Action')
            ->disableOriginalConstructor()->getMock();
//        $action = Action::factory($request);

        $request->setParam(
            'url',
            'finanzcheck.de/lp/sofortkredit/?aom_platform=Criteo&aom_campaign_id=184747916&aom_ad_group_id=9810883196'
        );
        $request->setParam('urlref', 'google.de');

        $result = $this->data->onNewVisit($request, $visitor, $action);
        $this->assertEquals('{"platform":"Criteo","campaignId":"184747916"}', $result);
    }

    public function testOnNewVisitUrlReferer()
    {
        $referrer = "finanzcheck.de/lp/sofortkredit/?aom_order_item_id=1&aom_ad_id=12&aom_platform=Bing&aom_campaign_id=184747916&aom_ad_group_id=9810883196&aom_feed_item_id=&aom_target_id=kwd-97675593&aom_creative=48726498716&aom_placement=&aom_target=&aom_network=g&aom_device=m&aom_ad_position=1t1&aom_loc_physical=9042859&aom_loc_Interest=&amount=8000&term=84&purpose=other&bid=dc0af63330ae5b1857d7862089d3fc0d&baseline=1463090434342";
        $entryUrl = 'finanzcheck.de';
        $params = ['url' => $entryUrl, 'urlref' => $referrer, 'idsite' => 1];

        $request = new Request($params);
        $visitor = new Visitor(new VisitProperties());
        $action = Action::factory($request);

        $result = $this->data->onNewVisit($request, $visitor, $action);
        $this->assertEquals('{"platform":"Bing","campaignId":"184747916","adGroupId":"9810883196","orderItemId":"1","targetId":"kwd-97675593","adId":"12"}', $result);
    }
}

AdParamsTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
