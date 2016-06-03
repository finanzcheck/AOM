<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\System;

use Piwik\Plugins\AOM\Columns\Platform;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Plugins\AOM\Columns\AdData;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Tracker\Visitor;

/**
 * @group AOM
 * @group AOM_Unit
 */
class PlatformTest extends \PHPUnit_Framework_TestCase
{
    /** @var AdData */
    private $data;

    public function setUp()
    {
        $this->data = new Platform();
    }

    public function testOnNewVisitUrl()
    {
        $entryUrl = "finanzcheck.de/lp/sofortkredit/?aom_platform=AdWords&aom_campaign_id=184747916&aom_ad_group_id=9810883196&aom_feed_item_id=&aom_target_id=kwd-97675593&aom_creative=48726498716&aom_placement=&aom_target=&aom_network=g&aom_device=m&aom_ad_position=1t1&aom_loc_physical=9042859&aom_loc_Interest=&amount=8000&term=84&purpose=other&bid=dc0af63330ae5b1857d7862089d3fc0d&baseline=1463090434342";
        $referrer = 'google.de';
        $params = ['url' => $entryUrl, 'urlref' => $referrer, 'idsite' =>1];

        $request = new Request($params);
        $visitor = new Visitor(new VisitProperties());
        $action = Action::factory($request);

        $request->setParam('url', "finanzcheck.de/lp/sofortkredit/?aom_platform=AdWords&aom_campaign_id=184747916&aom_ad_group_id=9810883196&aom_feed_item_id=&aom_target_id=kwd-97675593&aom_creative=48726498716&aom_placement=&aom_target=&aom_network=g&aom_device=m&aom_ad_position=1t1&aom_loc_physical=9042859&aom_loc_Interest=&amount=8000&term=84&purpose=other&bid=dc0af63330ae5b1857d7862089d3fc0d&baseline=1463090434342");


        $result = $this->data->onNewVisit($request, $visitor, $action);
        $this->assertEquals("AdWords", $result);
    }

    public function testOnNewVisitUrlReferer()
    {
        $referrer = "finanzcheck.de/lp/sofortkredit/?aom_platform=Bing&aom_campaign_id=184747916&aom_ad_group_id=9810883196&aom_feed_item_id=&aom_target_id=kwd-97675593&aom_creative=48726498716&aom_placement=&aom_target=&aom_network=g&aom_device=m&aom_ad_position=1t1&aom_loc_physical=9042859&aom_loc_Interest=&amount=8000&term=84&purpose=other&bid=dc0af63330ae5b1857d7862089d3fc0d&baseline=1463090434342";
        $entryUrl = 'finanzcheck.de';
        $params = ['url' => $entryUrl, 'urlref' => $referrer, 'idsite' =>1];

        $request = new Request($params);
        $visitor = new Visitor(new VisitProperties());
        $action = Action::factory($request);

        $result = $this->data->onNewVisit($request, $visitor, $action);
        $this->assertEquals("Bing", $result);
    }
}
