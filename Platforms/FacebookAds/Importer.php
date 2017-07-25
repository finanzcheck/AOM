<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Exception;
use Monolog\Logger;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\AbstractImporter;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\SystemSettings;

class Importer extends AbstractImporter implements ImporterInterface
{
    /**
     * Imports all active accounts day by day
     */
    public function import()
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'] as $accountId => $account) {
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
        $this->log(Logger::INFO, 'Will import FacebookAds account ' . $accountId. ' for date ' . $date . ' now.');
        $this->deleteExistingData(AOM::PLATFORM_FACEBOOK_ADS, $accountId, $account['websiteId'], $date);

        throw new Exception('Not implemented');
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
            array_merge(['platform' => AOM::PLATFORM_FACEBOOK_ADS, 'task' => 'import'], $additionalContext)
        );
    }
}
