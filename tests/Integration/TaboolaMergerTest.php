<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\tests\Integration;

use Piwik;
use Piwik\Db;
use Piwik\Common;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\Taboola\Merger;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Plugins\AOM\Services\PiwikVisitService;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Tests\Framework\Fixture;

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

    private function addVisit($idvisit, $adParams, $platform = AOM::PLATFORM_TABOOLA)
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

       // Add newly created visit to aom_visits table
       $piwikVisitService = new PiwikVisitService();
       $piwikVisitService->checkForNewVisit();
    }

    private function getAomVisit($id)
    {
        $data = DB::fetchRow('SELECT * FROM ' . Common::prefixTable('aom_visits') . ' WHERE piwik_idvisit = ?', [$id]);

        return [json_decode($data['platform_data'], true), $data['cost']];
    }


    public function setUp()
    {
        parent::setUp();

        $merger = new Merger();

        Db::query(
            'INSERT INTO ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA)
            . ' (idsite, date, campaign_id, campaign, site_id, site, impressions, clicks, cost, conversions) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, date('Y-m-d'), 141, 'Campaign 1', 'site-id-1', 'Site 1', 7570, 13, 36.4, 1]
        );

        // Historic match
        Db::query(
            'INSERT INTO ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA)
            . ' (idsite, date, campaign_id, campaign, site_id, site, impressions, clicks, cost, conversions) '.
            'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);',
            [1, '2015-12-28', 242, 'Campaign 2', 'site-id-2', 'Site 2', 4370, 18, 41.4, 2]
        );


        $this->addVisit(1, '{"platform":"Taboola","campaignId":"141","siteId": "site-id-1"}');
        $this->addVisit(2, '{"platform":"Taboola","campaignId":"242","siteId": "site-id-2"}');
        $this->addVisit(3, '{"platform":"Taboola","campaignId":"9999"}');

        $merger->setPeriod(date('Y-m-d'), date('Y-m-d', strtotime('+1 day')));
        $merger->merge();
    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testExactMatch()
    {
        list($platformData, $cost) = $this->getAomVisit(1);
        $this->assertEquals('Campaign 1', $platformData['campaign']);
        $this->assertEquals(36.4, $cost);
    }


    public function testHistoricalMatch()
    {
        list($platformData, $cost) = $this->getAomVisit(2);
        $this->assertEquals('Campaign 2', $platformData['campaign']);
        $this->assertEquals(null, $cost);
    }

    public function testNoMatch()
    {
        list($platformData, $cost) = $this->getAomVisit(3);
        $this->assertEquals(null, $platformData);
        $this->assertEquals(null, $cost);
    }

    // TODO: Cost allocation

    // TODO: Artificial VIsit creation

    // TODO: Only one artificial visit when reexecuted
}

TaboolaMergerTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
