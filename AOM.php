<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class AOM extends \Piwik\Plugin
{
    const PLATFORM_AD_WORDS = 'AdWords';
    const PLATFORM_BING = 'Bing';
    const PLATFORM_CRITEO = 'Criteo';
    const PLATFORM_FACEBOOK_ADS = 'FacebookAds';

    /**
     * @return array All supported platforms
     */
    public static function getPlatforms()
    {
        return [
            self::PLATFORM_AD_WORDS,
            self::PLATFORM_BING,
            self::PLATFORM_CRITEO,
            self::PLATFORM_FACEBOOK_ADS,
        ];
    }

    /**
     * Installs the plugin.
     *
     * @throws \Exception
     */
    public function activate()
    {
        foreach (self::getPlatforms() as $platform) {

            $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $platform . '\\' . $platform;

            /** @var PlatformInterface $platform */
            $platform = new $className();
            $platform->activatePlugin();
        }
    }

    /**
     * Uninstalls the plugin.
     */
    public function uninstall()
    {
        foreach (self::getPlatforms() as $platform) {

            $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $platform . '\\' . $platform;

            /** @var PlatformInterface $platform */
            $platform = new $className();
            $platform->uninstallPlugin();
        }
    }
}
