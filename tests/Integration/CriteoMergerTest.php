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
use Piwik\Plugins\AOM\Platforms\Criteo\Criteo;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\AOM\Platforms\Criteo\Merger;

/**
 * @group AOM
 * @group CriteoMergerTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class CriteoMergerTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    private function addVisit($idvisit, $adParams, $platform = "Criteo")
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
            'INSERT INTO ' . Criteo::getDataTableNameStatic() . ' (idsite, date, campaign_id, campaign, impressions, clicks, cost) '.
            'VALUE ( ?, ?, ?, ?, ?, ?, ?);',
            [1,'2015-12-28',14111,'Camp Name',7570,13,36.4]
        );

        Db::query(
            'INSERT INTO ' . Criteo::getDataTableNameStatic() . ' (idsite, date, campaign_id, campaign, impressions, clicks, cost) '.
            'VALUE ( ?, ?, ?, ?, ?, ?, ?);',
            [1,date('Y-m-d'),14112,'Camp Name2',7570,13,36.4]
        );


        $this->addVisit(1, '{"platform":"Criteo","campaignId":"14111"}');
        $this->addVisit(2, '{"platform":"Criteo","campaignId":"14112"}');
        $this->addVisit(3, '{"platform":"Criteo","campaignId":"9999"}');

        $merger->setPeriod(date('Y-m-d'), date("Ymd", strtotime("+1 day")));
        $merger->setPlatform(new Criteo($logger));
        $merger->merge();
    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testExactMatch()
    {
        $data = $this->getVisit(2);
        $this->assertEquals('Camp Name2', $data['campaign']);
        $this->assertEquals(36.4, $data['cost']);
    }


    public function testHistoricalMatch()
    {
        $data = $this->getVisit(1);
        $this->assertEquals('Camp Name', $data['campaign']);
        $this->assertArrayNotHasKey('cost', $data);
    }

    public function testNoMatch()
    {
        $data = $this->getVisit(3);
        print_r($data);
        $this->assertEquals(0, count($data));
    }
}

CriteoMergerTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
