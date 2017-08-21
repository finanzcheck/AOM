<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Plugins\AOM\SystemSettings;
use Psr\Log\LoggerInterface;
use Piwik\Tracker\Request;

abstract class AbstractPlatform
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SystemSettings
     */
    private $settings;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->settings = new SystemSettings();

        $this->logger = (null === $logger ? AOM::getLogger() : $logger);
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Returns this plugin's settings.
     *
     * @return SystemSettings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Whether or not this platform has been activated in the plugin's configuration.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->settings->{'platform' . $this->getName() . 'IsActive'}->getValue();
    }

    /**
     * Sets up a platform (e.g. adds tables and indices).
     *
     * @throws Exception
     */
    public function installPlugin()
    {
        $this->getInstaller()->installPlugin();
    }

    /**
     * Cleans up platform specific stuff such as tables and indices when the plugin is being uninstalled.
     *
     * @throws Exception
     */
    public function uninstallPlugin()
    {
        $this->getInstaller()->uninstallPlugin();
    }

    /**
     * Returns the translated localized name of the platform.
     *
     * @return string
     */
    public function getLocalizedPlatformName()
    {
        return Piwik::translate('AOM_Platform_Name_' . $this->getName());
    }

    /**
     * Returns true if the visit is coming from this platform. False otherwise.
     *
     * TODO: Check if we should use $action->getActionUrl() instead of or in addition to $request->getParams()['url'].
     *
     * @param Request $request
     * @return bool
     */
    public function isVisitComingFromPlatform(Request $request)
    {
        // Check current URL first before referrer URL
        $urlsToCheck = [];
        if (isset($request->getParams()['url'])) {
            $urlsToCheck[] = $request->getParams()['url'];
        }

        // Only consider the referrer when it is an internal URL
        if (isset($request->getParams()['urlref']) && $request->getIdSite()
            && $this->isReferrerAnInternalUrl($request->getParams()['urlref'], $request->getIdSite())
        ) {
            $urlsToCheck[] = $request->getParams()['urlref'];
        }

        foreach ($urlsToCheck as $urlToCheck) {
            if ($this->isPlatformParamForThisPlatformInUrl($urlToCheck)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts advertisement platform specific data from the request (referrer, query params) and returns it as an
     * associative array.
     *
     * TODO: Check if we should use $action->getActionUrl() instead of or in addition to $request->getParams()['url'].
     * TODO: Rename adParams into something which also makes sense for data extracted from the referrer
     *
     * @param Request $request
     * @return null|array
     */
    public function getAdParamsFromRequest(Request $request)
    {
        $paramPrefix = $this->getSettings()->paramPrefix->getValue();
        $platform = AOM::getPlatformInstance($this->getUnqualifiedClassName());

        // Check current URL before referrer URL
        $urlsToCheck = [];
        if (isset($request->getParams()['url'])) {
            $urlsToCheck[] = $request->getParams()['url'];
        }
        if (isset($request->getParams()['urlref'])) {
            $urlsToCheck[] = $request->getParams()['urlref'];
        }

        // TODO: Should we combine the results of all the different checks instead of simply return the first match?
        $failures = [];
        foreach ($urlsToCheck as $urlToCheck) {

            $queryString = parse_url($urlToCheck, PHP_URL_QUERY);
            parse_str($queryString, $queryParams);

            // Individual campaigns return false but no missing params
            list($success, $params) = $platform->getAdParamsFromUrl($urlToCheck, $queryParams, $paramPrefix, $request);
            if ($success) {
                return $params;
            } elseif (false === $success && count($params) > 0) {
                $failures[] = ['url' => $urlToCheck, 'missingParams' => $params];
            }
        }

        if (count($failures) > 0) {
            $message = 'Visit from platform ' . $platform->getName() . ' ';
            for ($i = 0; $i < count($failures); $i++) {
                if ($i > 0) { $message .= ' and '; }
                $message .= 'without required param' . (count($failures[$i]['missingParams']) != 1 ? 's' : '')
                    . ' "' . implode('", "', $failures[$i]['missingParams']) . '" in URL "' . $failures[$i]['url']
                    . '"';
            }
            $this->logger->warning($message . '.');
        }

        return null;
    }

    /**
     * Extracts and returns advertisement platform specific data from an URL.
     * $queryParams and $paramPrefix are only passed as params for convenience reasons.
     *
     * @param string $url
     * @param array $queryParams
     * @param string $paramPrefix
     * @param Request $request
     * @return array|null
     */
    abstract protected function getAdParamsFromUrl($url, array $queryParams, $paramPrefix, Request $request);

    /**
     * Instantiates and returns the platform specific installer.
     *
     * @return InstallerInterface
     */
    private function getInstaller()
    {
        $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $this->getUnqualifiedClassName() . '\\Installer';

        /** @var InstallerInterface $installer */
        $installer = new $className($this);

        return $installer;
    }

    public function import($mergeAfterwards = false, $startDate = null, $endDate = null)
    {
        if (!$this->isActive()) {
            return;
        }

        /** @var ImporterInterface $importer */
        $importer = AOM::getPlatformInstance($this->getUnqualifiedClassName(), 'Importer');
        $importer->setPeriod($startDate, $endDate);
        $importer->import();

        if ($mergeAfterwards) {

            // We must use the importer's period, as $startDate and $endDate can be null or could have been modified
            $this->logger->debug(
                'Will merge ' .  $this->getUnqualifiedClassName() . ' for period from ' . $importer->getStartDate()
                . ' until ' . $importer->getEndDate() . ' on a daily basis now.'
            );
            // We merge on a daily basis, primarily due to performance issues
            foreach (AOM::getPeriodAsArrayOfDates($importer->getStartDate(), $importer->getEndDate()) as $date) {
                $this->merge($date, $date);
            }
        }
    }

    /**
     * Platforms can add items to the admin menu. By default, no menu items are being added.
     *
     * @return array
     */
    public function getMenuAdminItems()
    {
        return [];
    }

    /**
     * Platforms can load additional JS files in the admin view. Be default, no additional JS files are being loaded.
     * The JS file AOM/Platform/{PlatformName}javascripts/{PlatformName}.js is loaded by naming convention.
     *
     * @return array
     */
    public function getJsFiles()
    {
        return [];
    }

    public function merge($startDate, $endDate)
    {
        if (!$this->isActive()) {
            return;
        }

        /** @var MergerInterface $merger */
        $merger = AOM::getPlatformInstance($this->getUnqualifiedClassName(), 'Merger');
        $merger->setPeriod($startDate, $endDate);
        $merger->merge();
    }

    /**
     * Deletes all imported data for the given combination of platform account, website and date.
     *
     * @param string $platformName
     * @param string $accountId
     * @param int $websiteId
     * @param string $date
     * @return array
     */
    public static function deleteImportedData($platformName, $accountId, $websiteId, $date)
    {
        $timeStart = microtime(true);
        $deletedImportedDataRecords = Db::deleteAllRows(
            DatabaseHelperService::getTableNameByPlatformName($platformName),
            'WHERE id_account_internal = ? AND idsite = ? AND date = ?',
            'date',
            100000,
            [
                $accountId,
                $websiteId,
                $date,
            ]
        );
        $timeToDeleteImportedData = microtime(true) - $timeStart;

        return [$deletedImportedDataRecords, $timeToDeleteImportedData];
    }

    /**
     * Returns the platform's unqualified class name.
     *
     * @return string
     */
    protected function getUnqualifiedClassName()
    {
        return substr(strrchr(get_class($this), '\\'), 1);
    }

    /**
     * Returns the platform's name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->getUnqualifiedClassName();
    }

    /**
     * Returns the platform's data table name.
     *
     * @return string
     */
    public function getDataTableName()
    {
        return Common::prefixTable('aom_' . strtolower($this->getName()));
    }

    /**
     * Returns a platform-specific description of a specific visit optimized for being read by humans or false when no
     * platform-specific description is available.
     *
     * @param int $idVisit
     * @return false|string
     * @throws Exception
     */
    public static function getHumanReadableDescriptionForVisit($idVisit)
    {
        $platform = Db::fetchOne(
            'SELECT aom_platform FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?', [$idVisit]
        );

        if (in_array($platform, AOM::getPlatforms())) {

            $platform = AOM::getPlatformInstance($platform);

            return $platform->getHumanReadableDescriptionForVisit($idVisit);
        }

        return false;
    }

    /**
     * Returns true if the platform param exists in the given URL and if its value is the current's platform's name.
     * False otherwise.
     *
     * @param string $url
     * @return bool
     */
    protected function isPlatformParamForThisPlatformInUrl($url)
    {
        $paramPrefix = $this->getSettings()->paramPrefix->getValue();

        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        return (is_array($queryParams)
            && array_key_exists($paramPrefix . '_platform', $queryParams)
            && $this->getName() === $queryParams[$paramPrefix . '_platform']
        );
    }

    /**
     * Returns true if the given referrer is an internal URL with the same host as the Piwik site. False otherwise.
     *
     * @param string $referrerUrl
     * @param int $idSite
     * @return bool
     */
    protected function isReferrerAnInternalUrl($referrerUrl, $idSite)
    {
        // Site::getMainUrlFor($request->getIdSite()) does not work in tests
        $hostOfSiteUrl = parse_url(
            Db::fetchOne(
                'SELECT main_url FROM ' . Common::prefixTable('site') . ' WHERE idsite = ?',
                [$idSite,]
            ),
            PHP_URL_HOST
        );
        if (!$hostOfSiteUrl) {
            return false;
        }

        $hostOfSiteUrl = str_replace('www.', '', $hostOfSiteUrl);

        // TODO: Does this really cover all relevant URLs?
        $hostOfReferrer = parse_url($referrerUrl, PHP_URL_HOST);

        return ($hostOfReferrer && $hostOfSiteUrl && (false !== strpos($hostOfReferrer, $hostOfSiteUrl)));
    }
}
