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
     * Extracts and returns the contents of the adId param (it's name is configurable) from a given URL
     * or false when the param could not be found.
     *
     * @param string $url
     * @return mixed Either the contents of the adId param as a string or false when the param could not be found.
     */
    public static function getAdIdFromUrl($url)
    {
        $settings = new Settings();
        $parameterName = $settings->adId->getValue();

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        if (is_array($queryParams) && array_key_exists($parameterName, $queryParams)) {
            return $queryParams[$parameterName];
        }

        return false;
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
