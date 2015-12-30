<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Tracker\Action;

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
     * @param bool|string $pluginName
     */
    public function __construct($pluginName = false)
    {
        // Add composer dependencies
        require_once PIWIK_INCLUDE_PATH . '/plugins/AOM/vendor/autoload.php';

        parent::__construct($pluginName);
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
            $platform = self::getPlatformInstance($platform);
            $platform->installPlugin();
        }
    }

    /**
     * Uninstalls the plugin.
     */
    public function uninstall()
    {
        foreach (self::getPlatforms() as $platform) {
            $platform = self::getPlatformInstance($platform);
            $platform->uninstallPlugin();
        }
    }

    /**
     * @see Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
        );
    }

    /**
     * Return list of plug-in specific JavaScript files to be imported by the asset manager.
     *
     * @see Piwik\AssetManager
     */
    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/AOM/javascripts/AOM.js';
    }

    /**
     * @param string $platform
     * @return PlatformInterface
     */
    public static function getPlatformInstance($platform)
    {
        $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $platform . '\\' . $platform;

        return new $className();
    }

    /**
     * Extracts and returns the advertising platform from a given URL or null when no platform is identified.
     *
     * @param string $url
     * @return mixed Either the platform or null when no valid platform could be extracted.
     */
    public static function getPlatformFromUrl($url)
    {
        $settings = new Settings();
        $paramPrefix = $settings->paramPrefix->getValue();

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        if (is_array($queryParams)
            && array_key_exists($paramPrefix . '_platform', $queryParams)
            && in_array($queryParams[$paramPrefix . '_platform'], AOM::getPlatforms())
        ) {
            return $queryParams[$paramPrefix . '_platform'];
        }

        return null;
    }


    /**
     * Tries to find some Ad data for this visit
     * @param Action $url
     * @return mixed
     */
    public static function getAdData(Action $action)
    {
        $params = self::getAdParamsFromUrl($action->getActionUrl());
        if(!$params) {
            return null;
        }

        $platform = self::getPlatformInstance($params['platform']);
        return $platform->getAdDataFromAdParams($action->request->getIdSite(), $params);
    }

    /**
     * Extracts and returns the contents of this plugin's params from a given URL or null when no params are found.
     *
     * @param string $url
     * @return mixed Either the contents of this plugin's params or null when no params are found.
     */
    public static function getAdParamsFromUrl($url)
    {
        $settings = new Settings();
        $paramPrefix = $settings->paramPrefix->getValue();

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        if (is_array($queryParams)
            && array_key_exists($paramPrefix . '_platform', $queryParams)
            && in_array($queryParams[$paramPrefix . '_platform'], AOM::getPlatforms())
        ) {

            $platform = self::getPlatformInstance($queryParams[$paramPrefix . '_platform']);

            $adParams = ($platform->isActive()
                ? $platform->getAdParamsFromQueryParams($paramPrefix, $queryParams)
                : null
            );

            return $adParams;
        }

        return null;
    }
}
