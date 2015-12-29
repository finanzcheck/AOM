<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\System;

use Piwik\Db;
use Piwik\Plugins\AOM\tests\Fixtures\Fixtures;
use Piwik\Tests\Framework\TestCase\SystemTestCase;

/**
 * @group AOM
 * @group AOM_AdDataExtractionTest
 * @group AOM_Integration
 */
class AdDataExtractionTest extends SystemTestCase
{
    /**
     * @var Fixtures
     */
    public static $fixture = null; // initialized below class definition

    public function testAdWords()
    {
        $this->assertEquals(
            '{"platform":"AdWords","campaignId":"184418636","adGroupId":"9794351276","targetId":"kwd-118607649",'
                . '"creative":"47609133356","placement":"","network":"g","device":"m","adPosition":"1t2",'
                . '"locPhysical":"20228","locInterest":"1004074"}',
            Db::fetchOne('SELECT aom_ad_params FROM piwik_log_visit WHERE user_id = "c1aed2f5b1f79d27951b0b309ff42919"')
        );
    }

    public function testBing()
    {
        $this->assertEquals(
            '{"platform":"Bing","campaignId":"190561279","adGroupId":"2029114499","orderItemId":"40414589411",'
                . '"targetId":"40414589411","adId":"5222037942"}',
            Db::fetchOne('SELECT aom_ad_params FROM piwik_log_visit WHERE user_id = "aed2f5b1f79d27951b0b309ff42919c1"')
        );
    }

    public function testCriteo()
    {
        $this->assertEquals(
            '{"platform":"Criteo","campaignId":"14340"}',
            Db::fetchOne('SELECT aom_ad_params FROM piwik_log_visit WHERE user_id = "d2f5b1f79d27951b0b309ff42919c1ae"')
        );
    }

    public function testFacebookAds()
    {
        $this->assertEquals(
            '{"platform":"FacebookAds","campaignGroupId":"4160286035775","campaignId":"6028603577541",'
                .'"adGroupId":"5760286037541"}',
            Db::fetchOne('SELECT aom_ad_params FROM piwik_log_visit WHERE user_id = "f5b1f79d27951b0b309ff42919c1aed2"')
        );
    }
}

AdDataExtractionTest::$fixture = new Fixtures();
