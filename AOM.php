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
     * We must use install() instead of activate() to make integration tests working.
     *
     * @throws \Exception
     */
    public function install()
    {
        foreach (self::getPlatforms() as $platform) {

            $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $platform . '\\' . $platform;

            /** @var PlatformInterface $platform */
            $platform = new $className();
            $platform->installPlugin();
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

    /**
     * Extracts and returns the contents of this plugin's params from a given URL or null when no params are found.
     *
     * @param string $url
     * @return mixed Either the contents of this plugin's params or null when no params are found.
     */
    public static function getAdDataFromUrl($url)
    {
        $settings = new Settings();
        $paramPrefix = $settings->paramPrefix->getValue();

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        if (is_array($queryParams)
            && array_key_exists($paramPrefix . '_platform', $queryParams)
            && in_array($queryParams[$paramPrefix . '_platform'], AOM::getPlatforms())
        ) {
            $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $queryParams[$paramPrefix . '_platform'] . '\\'
                . $queryParams[$paramPrefix . '_platform'];

            /** @var PlatformInterface $platform */
            $platform = new $className();

            $adData = ($platform->isActive()
                ? $platform->getAdDataFromQueryParams($paramPrefix, $queryParams)
                : null
            );

            return $adData;
        }

        return null;
    }
}
