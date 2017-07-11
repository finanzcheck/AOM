<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\tests\System;

use Piwik\Plugins\AOM\tests\Fixtures\Fixtures;
use Piwik\Tests\Framework\TestCase\SystemTestCase;

/**
 * @group AOM
 * @group AOM_APITest
 * @group AOM_System
 */
class APITest extends SystemTestCase
{
    /**
     * @var Fixtures
     */
    public static $fixture = null; // initialized below class definition

    public static function getOutputPrefix()
    {
        return '';
    }

    public static function getPathToTestDirectory()
    {
        return dirname(__FILE__);
    }

    /**
     * @dataProvider getApiForTesting
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    public function getApiForTesting()
    {
        $dateWithPluginEnabled = self::$fixture->dateTime;

        // ensure fundamental API works fine
        $apiToTest[] = [
            'API.get',
            [
                'idSite' => self::$fixture->idSite,
                'date' => $dateWithPluginEnabled,
                'periods' => ['day'],
                'format' => 'json',
            ]
        ];

        $api = [
            'AOM.getVisits',
        ];

        // AOM.getVisits
        $apiToTest[] = [
            $api,
            [
                'idSite' => self::$fixture->idSite,
                'date' => $dateWithPluginEnabled,
                'periods' => ['day'],
                'format' => 'json',
                'otherRequestParameters' => [
                    'expanded' => 1,
                ],
                'testSuffix' => 'expanded',
            ]
        ];

        return $apiToTest;
    }
}

APITest::$fixture = new Fixtures();
