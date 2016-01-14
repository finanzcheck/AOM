<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Criteo;

use Piwik\Piwik;
use Piwik\Plugins\AOM\Platforms\ControllerInterface;
use Piwik\Plugins\AOM\Settings;

class Controller extends \Piwik\Plugins\AOM\Platforms\Controller implements ControllerInterface
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

        $settings = new Settings();
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
