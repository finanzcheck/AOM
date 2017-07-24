<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\tests\Fixtures;

use Piwik\Plugins\AOM\SystemSettings;
use Piwik\Tests\Framework\Fixture;
use Piwik;
use Piwik\Date;

class BasicFixtures extends Fixture
{
    public $tokenAuth;

    public function setUp()
    {
        $this->setUpWebsite();

        // since we're changing the list of activated plugins, we have to make sure file caches are reset
        Piwik\Cache::flushAll();

        $user = self::createSuperUser();
        $this->tokenAuth = $user['token_auth'];

        $settings = new SystemSettings();
        $settings->paramPrefix->setValue('aom');
        $settings->createNewVisitWhenCampaignChanges->setValue(true);
        $settings->platformAdWordsIsActive->setValue(true);
        $settings->platformBingIsActive->setValue(true);
        $settings->platformCriteoIsActive->setValue(true);
        $settings->platformFacebookAdsIsActive->setValue(true);
        $settings->platformIndividualCampaignsIsActive->setValue(true);
        $settings->platformTaboolaIsActive->setValue(true);
        $settings->save();

        // Make sure that VisitorRecognizer.php has not been modified (ExternalVisitId plugin)
        $code = file_get_contents(PIWIK_INCLUDE_PATH . '/core/Tracker/VisitorRecognizer.php');
        if (strlen($code) < 100
            || strpos($code, 'This method has been manually overridden by the ExternalVisitId plugin')
            || strpos($code, '$externalVisitId')
            || strpos($code, 'WHERE idsite = ? AND idvisitor = ? AND external_visit_id = ?')
        ) {
            die(
                'Piwik AOM tests do not work when Piwik\Tracker\VisitorRecognizer.findKnownVisitor() has been modified '
                    . 'by the ExternalVisitId plugin.'
            );
        }
    }

    public function tearDown()
    {
        // empty
    }

    protected function setUpWebsite()
    {
        $idSite =
            self::createWebsite('2015-12-01 01:23:45', $ecommerce = 1, 'Example Website', 'http://www.example.com');
        $this->assertTrue($idSite === 1);
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

    /**
     * @param \PiwikTracker $t
     * @param $hourForward
     * @param $dateTime
     * @throws \Exception
     */
    public function moveTimeForward(\PiwikTracker $t, $hourForward, $dateTime)
    {
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour($hourForward)->getDatetime());
    }
}
