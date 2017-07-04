<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\Integration;

use Piwik;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\AOM\Platforms\Bing\Bing;
use Piwik\Tests\Framework\Fixture;

/**
 * @group AOM
 * @group AOM_BingGetAdDataTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class BingGetAdDataTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition


    /** @var  Bing */
    private  $Bing;

    public function setUp()
    {
        parent::setUp();

        $this->Bing = new Bing();

        Db::query(
            'INSERT INTO ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_BING)
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
            'INSERT INTO ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_BING)
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
    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testExactMatch()
    {
        list($rowId, $data) = $this->Bing->getAdDataFromAdParams(
            1,
            ['adGroupId' => 123, 'campaignId' => 1005, 'targetId' => 'kwd-55555']
        );

        $this->assertEquals('Campaign 1', $data['campaign']);
        $this->assertEquals(2.57, $data['cost']);
        $this->assertEquals(1, $rowId);
    }

    public function testAlternativeMatch()
    {
        list($rowId, $data) = $this->Bing->getAdDataFromAdParams(
            1,
            ['adGroupId' => 123, 'campaignId' => 1006, 'targetId' => 'kwd-66']
        );

        $this->assertEquals('Campaign Old', $data['campaign']);
        $this->assertArrayNotHasKey('cost', $data);
        $this->assertEquals(null, $rowId);
    }

    public function testNoMatch()
    {
        list($rowId, $data) = $this->Bing->getAdDataFromAdParams(
            1,
            ['adGroupId' => 998, 'campaignId' => 1005, 'targetId' => 'kwd-55555']
        );

        $this->assertEquals(null, $rowId);
        $this->assertEquals(null, $data);

    }
}

BingGetAdDataTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
