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
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\AOM\Platforms\Criteo\Criteo;
use Piwik\Tests\Framework\Fixture;

/**
 * @group AOM
 * @group CriteoMergingTest
 * @group AOM_Integration
 * @group AOM_Merging
 * @group Plugins
 */
class CriteoMergingTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition


    /** @var  Criteo */
    private  $criteo;

    public function setUp()
    {
        parent::setUp();

        // TODO: Replace StaticContainer with DI
        $logger = Piwik\Container\StaticContainer::get('Psr\Log\LoggerInterface');
        $this->criteo = new Criteo($logger);


        // set up your test here if needed
        Db::exec(
            'INSERT INTO piwik_aom_criteo (`id`, `idsite`, `date`, `campaign_id`, `campaign`, `impressions`, `clicks`, `cost`, `conversions`, `conversions_value`, `conversions_post_view`, `conversions_post_view_value`)
            VALUES
	        (19,1,\'2015-12-28\',14111,\'Camp Name\',7570,13,36.4,0,0,2,37400);'
        );

        Db::exec(
            'INSERT INTO piwik_aom_criteo (`id`, `idsite`, `date`, `campaign_id`, `campaign`, `impressions`, `clicks`, `cost`, `conversions`, `conversions_value`, `conversions_post_view`, `conversions_post_view_value`)
            VALUES
	        (20,1,\''.date('Y-m-d').'\',14112,\'Camp Name2\',7570,13,36.4,0,0,2,37400);'
        );

    }

    public function tearDown()
    {
        // clean up your test here if needed

        parent::tearDown();
    }

    public function testAlternativeMatch()
    {
        $data = $this->criteo->getAdDataFromAdParams(1, ['campaignId' => 14111]);

        $this->assertEquals('Camp Name', $data['campaign']);
        $this->assertArrayNotHasKey('cost', $data);
    }

    public function testExactMatch()
    {
        $data = $this->criteo->getAdDataFromAdParams(1, ['campaignId' => 14112]);

        $this->assertEquals('Camp Name2', $data['campaign']);
        $this->assertEquals(36.4, $data['cost']);
    }

}

CriteoMergingTest::$fixture = new Piwik\Plugins\AOM\tests\Fixtures\BasicFixtures();
