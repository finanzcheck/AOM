<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use FacebookAds\Api;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\Fields\InsightsFields;
use FacebookAds\Object\Values\InsightsLevels;
use Monolog\Logger;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Settings;

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
    /**
     * Imports all active accounts day by day
     */
    public function import()
    {
        $settings = new Settings();
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

        Api::init(
            $account['clientId'],
            $account['clientSecret'],
            $account['accessToken']
        );

        $adAccount = new AdAccount('act_' . $account['accountId']);
        $insights = $adAccount->getInsights([
            InsightsFields::DATE_START,
            InsightsFields::ACCOUNT_NAME,
            InsightsFields::CAMPAIGN_ID,
            InsightsFields::CAMPAIGN_NAME,
            InsightsFields::ADSET_ID,
            InsightsFields::ADSET_NAME,
            InsightsFields::AD_NAME,
            InsightsFields::AD_ID,
            InsightsFields::IMPRESSIONS,
            InsightsFields::INLINE_LINK_CLICKS,
            InsightsFields::SPEND,
        ], [
            'level' => InsightsLevels::AD,
            'time_range' => [
                'since' => $date,
                'until' => $date,
            ],
        ]);

        // TODO: Use MySQL transaction to improve performance!
        foreach ($insights as $insight) {
            Db::query(
                'INSERT INTO ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS)
                    . ' (id_account_internal, idsite, date, account_id, account_name, campaign_id, campaign_name, '
                    . 'adset_id, adset_name, ad_id, ad_name, impressions, clicks, cost, ts_created) '
                    . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $accountId,
                    $account['websiteId'],
                    $insight->getData()[InsightsFields::DATE_START],
                    $account['accountId'],
                    $insight->getData()[InsightsFields::ACCOUNT_NAME],
                    $insight->getData()[InsightsFields::CAMPAIGN_ID],
                    $insight->getData()[InsightsFields::CAMPAIGN_NAME],
                    $insight->getData()[InsightsFields::ADSET_ID],
                    $insight->getData()[InsightsFields::ADSET_NAME],
                    $insight->getData()[InsightsFields::AD_ID],
                    $insight->getData()[InsightsFields::AD_NAME],
                    $insight->getData()[InsightsFields::IMPRESSIONS],
                    $insight->getData()[InsightsFields::INLINE_LINK_CLICKS],
                    $insight->getData()[InsightsFields::SPEND],
                ]
            );
        }
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
