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
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\AOM\Platforms\AdWords\AdWords;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\AOM\Platforms\AdWords\Merger;

/**
 * @group AOM
 * @group AOM_AdWordsMergerTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class AdWordsMergerTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    private function addVisit($idvisit, $adParams, $platform = 'AdWords')
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

        $merger = new Merger();

        Db::query(
            'INSERT INTO ' . AdWords::getDataTableNameStatic()
            . ' (id_account_internal, idsite, date, account, campaign_id, campaign, ad_group_id, ad_group, '
            . 'keyword_id, keyword_placement, criteria_type, network, impressions, clicks, cost, '
            . 'conversions, ts_created) '
            . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
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
                'keywords',
                'keyword',
                'g',
                170,
                12,
                2.57,
                1,
            ]
        );

        Db::query(
            'INSERT INTO ' . AdWords::getDataTableNameStatic()
            . ' (id_account_internal, idsite, date, account, campaign_id, campaign, ad_group_id, ad_group, '
            . 'keyword_id, keyword_placement, criteria_type, network, impressions, clicks, cost, '
            . 'conversions, ts_created) '
            . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
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
                'keywords',
                'keyword',
                'g',
                170,
                12,
                7.8,
                1,
            ]
        );


        Db::query(
            'INSERT INTO ' . AdWords::getDataTableNameStatic()
            . ' (id_account_internal, idsite, date, account, campaign_id, campaign, ad_group_id, ad_group, '
            . 'keyword_id, keyword_placement, criteria_type, network, impressions, clicks, cost, '
            . 'conversions, ts_created) '
            . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                '1000',
                '1',
                date('Y-m-d'),
                'Test Account',
                '1005',
                'Campaign 2',
                '126',
                'adGroup 2',
                '55555',
                'www.test.de',
                'placement',
                'd',
                170,
                12,
                8.90,
                1,
            ]
        );

        $this->addVisit(1, '{"platform":"AdWords","campaignId":"1005","adGroupId":"123","feedItemId":"","targetId":"","creative":"48726465596","placement":"suchen.mobile.de","target":"suchen.mobile.de","network":"d","device":"t","adPosition":"none","locPhysical":"9043992","locInterest":""}');
        $this->addVisit(2, '{"platform":"AdWords","campaignId":"1005","adGroupId":"126","feedItemId":"","targetId":"","creative":"48726465596","placement":"www.test.de","target":"suchen.mobile.de","network":"d","device":"t","adPosition":"none","locPhysical":"9043992","locInterest":""}');
        $this->addVisit(3, '{"platform":"AdWords","campaignId":"1005","adGroupId":"123","feedItemId":"","targetId":"aud-1212;kwd-55555","creative":"48726465596","placement":"","target":"suchen.mobile.de","network":"g","device":"t","adPosition":"none","locPhysical":"9043992","locInterest":""}');

        $merger->setPeriod(date('Y-m-d'), date("Ymd", strtotime("+1 day")));
        $merger->setPlatform(new AdWords());
        $merger->merge();
    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testExactMatch()
    {
        $data = $this->getVisit(3);
        $this->assertEquals(2.57, $data['cost']);
    }

    public function testExactMatchDisplay()
    {
        $data = $this->getVisit(2);
        $this->assertEquals(8.9, $data['cost']);
    }

    public function testHistoricalMatch()
    {
        $data = $this->getVisit(1);
        $this->assertEquals('Campaign 1', $data['campaign']);
        $this->assertArrayNotHasKey('cost', $data);
    }
}

AdWordsMergerTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
