<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Taboola;

use Piwik\Piwik;
use Piwik\Plugins\AOM\SystemSettings;

class Controller extends \Piwik\Plugins\AOM\Platforms\Controller
{
    /**
     * @param int $websiteId
     * @param string $accountName
     * @param string $clientId
     * @param string $clientSecret
     * @return bool
     */
    public function addAccount($websiteId, $accountName, $clientId, $clientSecret)
    {
        Piwik::checkUserHasAdminAccess($idSites = [$websiteId]);

        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        $configuration[$this->getPlatform()]['accounts'][uniqid('', true)] = [
            'websiteId' => $websiteId,
            'accountName' => $accountName,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'active' => true,
        ];

        $settings->setConfiguration($configuration);

        return true;
    }
}
