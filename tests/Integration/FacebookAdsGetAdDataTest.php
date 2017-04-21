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
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\AOM\Platforms\FacebookAds\FacebookAds;
use Piwik\Tests\Framework\Fixture;

/**
 * @group AOM
 * @group AOM_FacebookAdsGetAdDataTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class FacebookAdsGetAdDataTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    /**
     * @var FacebookAds
     */
    private $FacebookAds;

    public function setUp()
    {
        parent::setUp();

        $this->FacebookAds = new FacebookAds();

        // set up your test here if needed
        Db::query(
            'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS)
            . ' (idsite, date, campaign_id, campaign_name, adset_id, adset_name, ad_id, ad_name, impressions, clicks, cost) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, date('Y-m-d'), 14111, 'Campaign 1', 333, 'AdSet1', 222, "AdName", 7570, 13, 36.4]
        );

        Db::query(
            'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS)
            . ' (idsite, date, campaign_id, campaign_name, adset_id, adset_name, ad_id, ad_name, impressions, clicks, cost) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, '2015-12-28', 14113, 'Campaign 2', 334, 'AdSet2', 777, "AdName2", 800, 100, 16.4]
        );
    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testHistoricalMatch()
    {
        list($rowId, $data) = $this->FacebookAds->getAdDataFromAdParams(1, ['campaignId' => 14113, 'adsetId' => 334, 'adId' => 777]);

        $this->assertEquals('Campaign 2', $data['campaign_name']);
        $this->assertArrayNotHasKey('cost', $data);
        $this->assertEquals(null, $rowId);
    }

    public function testExactMatch()
    {
        list($rowId, $data) = $this->FacebookAds->getAdDataFromAdParams(1, ['campaignId' => 14111, 'adsetId' => 333, 'adId' => 222]);

        $this->assertEquals('Campaign 1', $data['campaign_name']);
        $this->assertEquals(36.4, $data['cost']);
        $this->assertEquals(1, $rowId);
    }
}

FacebookAdsGetAdDataTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
