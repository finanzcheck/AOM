<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\System;

use Piwik\Plugins\AOM\tests\Fixtures\Fixtures;
use Piwik\Tests\Framework\TestCase\SystemTestCase;

/**
 * @group AOM
 * @group AOM_APITest
 * @group AOM_Integration
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

    //TODO: Fix and enable again
//    /**
//     * @dataProvider getApiForTesting
//     */
//    public function testApi($api, $params)
//    {
//        $this->runApiTests($api, $params);
//    }

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


//        $apiToTest[] = array($api,
//                             array('idSite'                 => self::$fixture->idSite,
//                                   'date'                   => $dateWithPluginEnabled,
//                                   'periods'                => array('day'),
//                                   'testSuffix'             => 'flat',
//                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
//                             ));
//        $apiToTest[] = array($api,
//                             array('idSite'                 => self::$fixture->idSite,
//                                   'date'                   => $dateWithPluginEnabled,
//                                   'periods'                => array('day'),
//                                   'testSuffix'             => 'segmentedMatchAll',
//                                   'segment'                => 'campaignName!=test;campaignKeyword!=test;campaignSource!=test;campaignMedium!=test;campaignContent!=test;campaignId!=test',
//                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
//                             ));
//        $apiToTest[] = array($api,
//                             array('idSite'                 => self::$fixture->idSite,
//                                   'date'                   => $dateWithPluginEnabled,
//                                   'periods'                => array('day'),
//                                   'testSuffix'             => 'segmentedMatchNone',
//                                   'segment'                => 'campaignName==test,campaignKeyword==test,campaignSource==test,campaignMedium==test,campaignContent==test,campaignId==test',
//                                   'otherRequestParameters' => array('flat' => 1, 'expanded' => 0)
//                             ));
//
//        $apiToTest[] = array('AdvancedCampaignReporting', array(
//            'idSite' => 'all',
//            'date' => self::$fixture->dateTime,
//            'periods' => 'day',
//            'setDateLastN' => true,
//            'testSuffix' => 'multipleDatesSites_',
//        ));
//
//        // row evolution tests for methods that also use Referrers plugin data
//        $apiToTest[] = array('API.getRowEvolution', array(
//            'idSite' => self::$fixture->idSite,
//            'date' => self::$fixture->dateTime,
//            'testSuffix' => 'getName',
//            'otherRequestParameters' => array(
//                'date'      => '2013-01-20,2013-01-25',
//                'period'    => 'day',
//                'apiModule' => 'AdvancedCampaignReporting',
//                'apiAction' => 'getName',
//                'label'     => 'campaign_hashed',
//                'expanded'  => 0
//            )
//        ));
//
//        $apiToTest[] = array('API.getRowEvolution', array(
//            'idSite' => self::$fixture->idSite,
//            'date' => self::$fixture->dateTime,
//            'testSuffix' => 'getKeyword',
//            'otherRequestParameters' => array(
//                'date'      => '2013-01-20,2013-01-25',
//                'period'    => 'day',
//                'apiModule' => 'AdvancedCampaignReporting',
//                'apiAction' => 'getKeyword',
//                'label'     => 'mot_clé_pépère',
//                'expanded'  => 0
//            )
//        ));

        return $apiToTest;
    }

}

APITest::$fixture = new Fixtures();
