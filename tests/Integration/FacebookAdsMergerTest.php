<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AOM\tests\Integration;

use Piwik;
use Piwik\Db;
use Piwik\Common;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\AOM\Platforms\FacebookAds\FacebookAds;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\AOM\Platforms\FacebookAds\Merger;

/**
 * @group AOM
 * @group FacebookAdsMergerTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class FacebookAdsMergerTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    private function addVisit($idvisit, $adParams, $platform = "FacebookAds")
    {
           Db::query(
            'INSERT INTO ' . Common::prefixTable('log_visit')
            . ' (idvisit, idsite, idvisitor, visit_first_action_time, '
            . 'aom_platform, aom_ad_params) '
            . 'VALUES (?, 1, 1, NOW(), ?, ?)',
            [
                $idvisit,
                $platform,
                $adParams,
            ]
        );
    }

    private function getVisit($id)
    {
        $data =  DB::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?', [$id]);
        return json_decode($data['aom_ad_data'], true);
    }


    public function setUp()
    {
        parent::setUp();

        // TODO: Replace StaticContainer with DI
        $logger = Piwik\Container\StaticContainer::get('Psr\Log\LoggerInterface');
        $merger = new Merger($logger);

        Db::query(
            'INSERT INTO ' . FacebookAds::getDataTableNameStatic() . ' (idsite, date, campaign_id, campaign_name, adset_id, adset_name, ad_id, ad_name, impressions, clicks, cost) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, date('Y-m-d'), 14111, 'Campaign 1', 333, 'AdSet1', 222, "AdName", 7570, 13, 36.4]
        );

        Db::query(
            'INSERT INTO ' . FacebookAds::getDataTableNameStatic() . ' (idsite, date, campaign_id, campaign_name, adset_id, adset_name, ad_id, ad_name, impressions, clicks, cost) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, '2015-12-28', 14113, 'Campaign 2', 334, 'AdSet2', 777, "AdName2", 800, 100, 16.4]
        );


        $this->addVisit(1, '{"platform":"FacebookAds","campaignId":"14111","adsetId": "333","adId": "222"}');
        $this->addVisit(2, '{"platform":"FacebookAds","campaignId":"14113","adsetId": "334","adId": "777"}');
        $this->addVisit(3, '{"platform":"FacebookAds","campaignId":"9999"}');

        $merger->setPeriod(date('Y-m-d'), date("Ymd", strtotime("+1 day")));
        $merger->setPlatform(new FacebookAds($logger));
        $merger->merge();
    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testExactMatch()
    {
        $data = $this->getVisit(1);
        $this->assertEquals('Campaign 1', $data['campaign_name']);
        $this->assertEquals(36.4, $data['cost']);
    }


    public function testHistoricalMatch()
    {
        $data = $this->getVisit(2);
        $this->assertEquals('Campaign 2', $data['campaign_name']);
        $this->assertArrayNotHasKey('cost', $data);
    }

    public function testNoMatch()
    {
        $data = $this->getVisit(3);
        $this->assertEquals(0, count($data));
    }
}

FacebookAdsMergerTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
