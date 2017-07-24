<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\SystemSettings;

abstract class Controller extends \Piwik\Plugin\ControllerAdmin
{
    private $platform;

    /**
     * @param string $platform
     * @throws \Exception
     */
    public function __construct($platform)
    {
        // Platform supported?
        if (!in_array($platform, AOM::getPlatforms())) {
            throw new \Exception('Platform "' . $platform . '" is not supported.');
        }

        $this->platform = $platform;

        parent::__construct();
    }

    /**
     * Deletes the specified account.
     *
     * @param string $id
     * @return bool
     */
    public function deleteAccount($id)
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        if (array_key_exists($id, $configuration[$this->getPlatform()]['accounts'])) {

            Piwik::checkUserHasAdminAccess(
                $idSites = [$configuration[$this->getPlatform()]['accounts'][$id]['websiteId']]
            );

            unset($configuration[$this->getPlatform()]['accounts'][$id]);
        }

        $settings->setConfiguration($configuration);

        return true;
    }

    /**
     * @return string
     */
    protected function getPlatform()
    {
        return $this->platform;
    }
}
