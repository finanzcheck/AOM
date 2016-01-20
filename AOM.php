<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Site;
use Piwik\Tracker\Action;
use Psr\Log\LoggerInterface;

class AOM extends \Piwik\Plugin
{
    /**
     * @var LoggerInterface
     */
    private static $defaultLogger;

    /**
     * @var LoggerInterface
     */
    private static $tasksLogger;

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

        // We use our own loggers
        // TODO: Use another file when we are running tests?!
        // TODO: Disable logging to console when running tests!
        // TODO: Allow to configure path and log-level (for every logger)?!
        $formatter = new ColoredLineFormatter(
            null,
            '%level_name% [%datetime%]: %message% %context% %extra%',
            null,
            true,
            true
        );

        self::$defaultLogger = new Logger('aom');
        $defaultLoggerFileStreamHandler = new StreamHandler(PIWIK_INCLUDE_PATH . '/aom.log', Logger::DEBUG);
        $defaultLoggerFileStreamHandler->setFormatter($formatter);
        self::$defaultLogger->pushHandler($defaultLoggerFileStreamHandler);

        self::$tasksLogger = new Logger('aom-tasks');
        $tasksLoggerFileStreamHandler = new StreamHandler(PIWIK_INCLUDE_PATH . '/aom-tasks.log', Logger::DEBUG);
        $tasksLoggerFileStreamHandler->setFormatter($formatter);
        self::$tasksLogger->pushHandler($tasksLoggerFileStreamHandler);
        $tasksLoggerConsoleStreamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
        $tasksLoggerConsoleStreamHandler->setFormatter($formatter);
        self::$tasksLogger->pushHandler($tasksLoggerConsoleStreamHandler);

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

        try {
            $sql = 'CREATE TABLE ' . Common::prefixTable('aom_visits') . ' (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        idsite INTEGER NOT NULL,
                        piwik_idvisit INTEGER,
                        piwik_visit_first_action_time_utc DATETIME NOT NULL,
                        date_website_timezone DATE NOT NULL,
                        channel VARCHAR(100),
                        campaign_data VARCHAR(5000),
                        platform_data VARCHAR(5000),
                        cost FLOAT NOT NULL,
                        ts_created TIMESTAMP
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        // TODO: Create indices!

        $this->getLogger()->debug('Installed AOM.');
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

        $this->getLogger()->debug('Uninstalled AOM.');
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
     * This logger writes to aom.log only.
     *
     * @return LoggerInterface
     */
    public static function getLogger()
    {
        return self::$defaultLogger;
    }

    /**
     * This logger writes to the console and to aom-tasks.log.
     *
     * @return LoggerInterface
     */
    public static function getTasksLogger()
    {
        return self::$tasksLogger;
    }

    /**
     * Returns the required instance.
     *
     * @param string $platform
     * @param null|string $class
     * @return PlatformInterface
     * @throws \Exception
     */
    public static function getPlatformInstance($platform, $class = null)
    {
        // Validate arguments
        if (!in_array($platform, AOM::getPlatforms())) {
            throw new \Exception('Platform "' . $platform . '" not supported.');
        }
        if (!in_array($class, [null, 'Importer', 'Merger'])) {
            throw new \Exception('Class "' . $class . '" not supported. Must be either "Importer" or "Merger".');
        }

        // Find the right logger for the job
        $logger = (in_array($class, ['Importer', 'Merger']) ? self::$tasksLogger : self::$defaultLogger);

        $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $platform . '\\' . (null === $class ? $platform : $class);

        return new $className($logger);
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
     * Tries to find some ad data for this visit.
     *
     * @param Action $action
     * @return mixed
     * @throws \Piwik\Exception\UnexpectedWebsiteFoundException
     */
    public static function getAdData(Action $action)
    {
        $params = self::getAdParamsFromUrl($action->getActionUrl());
        if (!$params) {
            return [null, null];
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

    /**
     * Converts a local datetime string (Y-m-d H:i:s) into UTC and returns it as a string (Y-m-d H:i:s).
     *
     * @param string $localDateTime
     * @param string $localTimeZone
     * @return string
     */
    public static function convertLocalDateTimeToUTC($localDateTime, $localTimeZone)
    {
        $date = new \DateTime($localDateTime, new \DateTimeZone($localTimeZone));
        $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Converts a UTC datetime string (Y-m-d H:i:s) into website's local time and returns it as a string (Y-m-d H:i:s).
     *
     * @param string $dateTime
     * @param int $idsite
     * @return string
     */
    public static function convertUTCToLocalDateTime($dateTime, $idsite)
    {
        $date = new \DateTime($dateTime);
        $date->setTimezone(new \DateTimeZone(Site::getTimezoneFor($idsite)));

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Returns all dates within the period, e.g. ['2015-12-20','2015-12-21']
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return array
     */
    public static function getPeriodAsArrayOfDates($startDate, $endDate)
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $invert = $start > $end;

        $dates = [];
        $dates[] = $start->format('Y-m-d');

        while ($start != $end) {
            $start->modify(($invert ? '-' : '+') . '1 day');
            $dates[] = $start->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * @param string $platformName
     * @return string
     */
    public static function getPlatformDataTableNameByPlatformName($platformName)
    {
        return Common::prefixTable('aom_' . strtolower($platformName));
    }
}
