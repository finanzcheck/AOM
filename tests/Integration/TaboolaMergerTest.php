<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\Integration;

use Piwik;
use Piwik\Db;
use Piwik\Common;
use Piwik\Plugins\AOM\AOM;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\AOM\Platforms\Taboola\Taboola;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\AOM\Platforms\Taboola\OldMerger;

/**
 * @group AOM
 * @group AOM_TaboolaMergerTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class TaboolaMergerTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    private function addVisit($idvisit, $adParams, $platform = 'Taboola')
    {
       Db::query(
            'INSERT INTO ' . Common::prefixTable('log_visit')
            . ' (idvisit, idsite, idvisitor, visit_first_action_time, aom_platform, aom_ad_params) '
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

        $merger = new OldMerger();

        Db::query(
            'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_TABOOLA)
            . ' (idsite, date, campaign_id, campaign, site_id, site, impressions, clicks, cost, conversions) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, date('Y-m-d'), 141, 'Campaign 1', 'site-id-1', 'Site 1', 7570, 13, 36.4, 1]
        );

        Db::query(
            'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_TABOOLA)
            . ' (idsite, date, campaign_id, campaign, site_id, site, impressions, clicks, cost, conversions) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, '2015-12-28', 242, 'Campaign 2', 'site-id-2', 'Site 2', 4370, 18, 41.4, 2]
        );


        $this->addVisit(1, '{"platform":"Taboola","campaignId":"141","siteId": "site-id-1"}');
        $this->addVisit(2, '{"platform":"Taboola","campaignId":"242","siteId": "site-id-2"}');
        $this->addVisit(3, '{"platform":"Taboola","campaignId":"9999"}');

        $merger->setPeriod(date('Y-m-d'), date("Ymd", strtotime("+1 day")));
        $merger->setPlatform(new Taboola());
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
        $this->assertEquals('Campaign 1', $data['campaign']);
        $this->assertEquals(36.4, $data['cost']);
    }


    public function testHistoricalMatch()
    {
        $data = $this->getVisit(2);
        $this->assertEquals('Campaign 2', $data['campaign']);
        $this->assertArrayNotHasKey('cost', $data);
    }

    public function testNoMatch()
    {
        $data = $this->getVisit(3);
        $this->assertEquals(0, count($data));
    }
}

TaboolaMergerTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
