<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\tests\Fixtures;

use Piwik\Plugins\AOM\Settings;
use Piwik\Tests\Framework\Fixture;
use Piwik;

class BasicFixtures extends Fixture
{
    public $dateTime = '2015-12-01 01:23:45';
    public $idSite = 1;

    const THIS_PAGE_VIEW_IS_GOAL_CONVERSION = 'this is a goal conversion';

    public function setUp()
    {
        $this->setUpWebsite();

        // since we're changing the list of activated plugins, we have to make sure file caches are reset
        Piwik\Cache::flushAll();

        $settings = new Settings();
        $settings->paramPrefix->setValue('aom');
        $settings->platformAdWordsIsActive->setValue(true);
        $settings->platformBingIsActive->setValue(true);
        $settings->platformCriteoIsActive->setValue(true);
        $settings->platformFacebookAdsIsActive->setValue(true);
        $settings->save();
    }

    public function tearDown()
    {
        // empty
    }

    private function setUpWebsite()
    {
        $idSite = self::createWebsite($this->dateTime, $ecommerce = 1);
        $this->assertTrue($idSite === $this->idSite);
    }

    public function provideContainerConfig()
    {
        return [
            'observers.global' => \DI\add([
                ['Environment.bootstrapped', function () {
                    $plugins = Piwik\Config::getInstance()->Plugins['Plugins'];
                    $plugins[] = 'AOM';
                    Piwik\Config::getInstance()->Plugins['Plugins'] = $plugins;
                }],
            ]),
        ];
    }
}
