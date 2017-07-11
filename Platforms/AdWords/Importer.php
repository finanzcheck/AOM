<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\ReportSettingsBuilder;
use Google\AdsApi\Common\Configuration;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Monolog\Logger;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractImporter;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;
use Piwik\Plugins\AOM\SystemSettings;
use Psr\Log\NullLogger;

class Importer extends AbstractImporter implements ImporterInterface
{
    /**
     * When no period is provided, AdWords (re)imports the last 3 days unless they have been (re)imported today.
     * Today's data is always being reimported.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return mixed|void
     */
    public function setPeriod($startDate = null, $endDate = null)
    {
        // Overwrite default period
        if (null === $startDate || null === $endDate) {

            $startDate = date('Y-m-d');

            // (Re)import the last 3 days unless they have been (re)imported today
            for ($i = -3; $i <= -1; $i++) {
                if (Db::fetchOne(
                        'SELECT DATE(MAX(ts_created)) FROM '
                        . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_AD_WORDS)
                        . ' WHERE date = "' . date('Y-m-d', strtotime($i . ' day', time())) . '"'
                    ) != date('Y-m-d')
                ) {
                    $startDate = date('Y-m-d', strtotime($i . ' day', time()));
                    break;
                }
            }

            $endDate = date('Y-m-d');
            $this->log(Logger::INFO, 'Identified period from ' . $startDate . ' until ' . $endDate . ' to import.');
        }

        parent::setPeriod($startDate, $endDate);
    }

    /**
     * Imports all active accounts day by day.
     */
    public function import()
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_AD_WORDS]['accounts'] as $accountId => $account) {
            if (array_key_exists('active', $account) && true === $account['active']) {
                foreach (AOM::getPeriodAsArrayOfDates($this->startDate, $this->endDate) as $date) {
                    $this->importAccount($accountId, $account, $date);
                }
            } else {
                $this->log(Logger::INFO, 'Skipping inactive account.');
            }
        }
    }

    /**
     * @param string $accountId
     * @param array $account
     * @param string $date
     * @throws \Exception
     */
    private function importAccount($accountId, $account, $date)
    {
        $this->log(Logger::INFO, 'Starting import of AdWords account ' . $accountId . ' for date ' . $date . ' now.');

        // Delete data from "aom_adwords" and "aom_adwords_gclid"
        $this->deleteExistingData(AOM::PLATFORM_AD_WORDS, $accountId, $account, $date);

        $oauth2Info = [
            'clientId' => $account['clientId'],
            'clientSecret' => $account['clientSecret'],
        ];

        if (null != $account['refreshToken']) {
            $oauth2Info['refreshToken'] = $account['refreshToken'];
        }

        $adWordsSession = (new AdWordsSessionBuilder())
            ->from(
                new Configuration([
                    'ADWORDS' => [
                        'developerToken' => $account['developerToken'],
                        'clientCustomerId' => $account['clientCustomerId'],
                    ],
                ])
            )
            ->withOAuth2Credential((new OAuth2TokenBuilder())
                ->from(new Configuration([
                    'OAUTH2' => $oauth2Info,
                ]))
                ->build())
            ->withReportSettings((new ReportSettingsBuilder())
                ->from(new Configuration([
                ]))
                ->includeZeroImpressions(false)
                ->build())
            ->withReportDownloaderLogger(new NullLogger())
            ->withSoapLogger(new NullLogger())
            ->build();

        $criteriaPerformanceImporter = new ImporterCriteriaPerformance($this->logger);
        $criteriaPerformanceImporter->import($adWordsSession, $accountId, $account, $date);

        $clickPerformanceImporter = new ImporterClickPerformance($this->logger);
        $clickPerformanceImporter->import($adWordsSession, $accountId, $account, $date);
    }

    /**
     * Delete data from "aom_adwords" and "aom_adwords_gclid"
     *
     * @param string $platformName
     * @param string $accountId
     * @param array $account
     * @param int $date
     */
    public function deleteExistingData($platformName, $accountId, $account, $date)
    {
        parent::deleteExistingData(AOM::PLATFORM_AD_WORDS, $accountId, $account['websiteId'], $date);

        // We also need to delete data from "aom_adwords_gclid"
        $deleted = Db::deleteAllRows(
            DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_AD_WORDS) . '_gclid',
            'WHERE id_account_internal = ? AND idsite = ? AND date = ?',
            'date',
            100000,
            [
                $accountId,
                $account['websiteId'],
                $date,
            ]
        );
        $this->log(Logger::DEBUG, 'Deleted existing AdWords gclid-data (' . $deleted . ' records).');
    }

    /**
     * Convenience function for shorter logging statements
     *
     * @param string $logLevel
     * @param string $message
     * @param array $additionalContext
     */
    private function log($logLevel, $message, $additionalContext = [])
    {
        $this->logger->log(
            $logLevel,
            $message,
            array_merge(['platform' => AOM::PLATFORM_AD_WORDS, 'task' => 'import'], $additionalContext)
        );
    }
}
