<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Piwik\Piwik;
use Piwik\Plugins\AOM\SystemSettings;

class Controller extends \Piwik\Plugins\AOM\Platforms\Controller
{
    /**
     * @param int $websiteId
     * @param string $appToken
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function addAccount($websiteId, $appToken, $username, $password)
    {
        Piwik::checkUserHasAdminAccess($idSites = [$websiteId]);

        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        $configuration[$this->getPlatform()]['accounts'][uniqid('', true)] = [
            'websiteId' => $websiteId,
            'appToken' => $appToken,
            'username' => $username,
            'password' => $password,
            'active' => true,
        ];

        $settings->setConfiguration($configuration);

        return true;
    }
}
