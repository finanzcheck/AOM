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
use Piwik\Plugins\AOM\Platforms\Bing\Bing;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\AOM\Platforms\Bing\Merger;

/**
 * @group AOM
 * @group AOM_BingMergerTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class BingMergerTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    private function addVisit($idvisit, $adParams, $platform = 'Bing')
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
        $data = DB::fetchRow('SELECT * FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?', [$id]);
        return json_decode($data['aom_ad_data'], true);
    }


    public function setUp()
    {
        parent::setUp();

        $merger = new Merger();

        Db::query(
            'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING)
            . ' (id_account_internal, idsite, date, account, campaign_id, campaign, ad_group_id, ad_group, keyword_id, '
            . 'keyword, impressions, clicks, cost, conversions, unique_hash, ts_created) '
            . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                '1000',
                '1',
                date('Y-m-d'),
                'Test Account',
                '1005',
                'Campaign 1',
                '123',
                'adGroup 1',
                '55555',
                'Wolf',
                170,
                12,
                2.57,
                1,
                1,
            ]
        );

        Db::query(
            'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING)
            . ' (id_account_internal, idsite, date, account, campaign_id, campaign, ad_group_id, ad_group, keyword_id, '
            . 'keyword, impressions, clicks, cost, conversions, unique_hash, ts_created) '
            . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                '1000',
                '1',
                '2015-05-06',
                'Test Account',
                '1006',
                'Campaign Old',
                '123',
                'adGroup 1',
                '66',
                'Cat',
                170,
                12,
                7.8,
                1,
                2,
            ]
        );


        $this->addVisit(1, '{"platform":"Bing","campaignId":"1005","adGroupId":"123","targetId":"kwd-55555"}');
        $this->addVisit(2, '{"platform":"Bing","campaignId":"1006","adGroupId":"123","targetId":""}');
        $this->addVisit(3, '{"platform":"Bing","campaignId":"9005","adGroupId":"123","targetId":""}');

        $merger->setPeriod(date('Y-m-d'), date("Ymd", strtotime("+1 day")));
        $merger->setPlatform(new Bing());
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
        $this->assertEquals(2.57, $data['cost']);
    }

    public function testHistoricalMatch()
    {
        $data = $this->getVisit(2);
        $this->assertEquals('Campaign Old', $data['campaign']);
        $this->assertArrayNotHasKey('cost', $data);
    }

    public function testNoMatch()
    {
        $data = $this->getVisit(3);
        $this->assertEquals(0, count($data));
    }
}

BingMergerTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
