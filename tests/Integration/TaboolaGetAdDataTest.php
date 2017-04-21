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
use Piwik\Plugins\AOM\Platforms\Taboola\Taboola;
use Piwik\Tests\Framework\Fixture;

/**
 * @group AOM
 * @group AOM_TaboolaGetAdDataTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class TaboolaGetAdDataTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    /**
     * @var Taboola
     */
    private $taboola;

    public function setUp()
    {
        parent::setUp();

        $this->taboola = new Taboola();

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
    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testHistoricalMatch()
    {
        list($rowId, $data) = $this->taboola->getAdDataFromAdParams(1, ['campaignId' => 242, 'siteId' => 'site-id-2',]);

        $this->assertEquals('Campaign 2', $data['campaign']);
        $this->assertArrayNotHasKey('cost', $data);
        $this->assertEquals(null, $rowId);
    }

    public function testExactMatch()
    {
        list($rowId, $data) = $this->taboola->getAdDataFromAdParams(1, ['campaignId' => 141, 'siteId' => 'site-id-2',]);

        $this->assertEquals('Campaign 1', $data['campaign']);
        $this->assertEquals(36.4, $data['cost']);
        $this->assertEquals(1, $rowId);
    }
}

TaboolaGetAdDataTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
