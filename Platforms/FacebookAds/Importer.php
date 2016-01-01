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
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\ImporterInterface;
use Piwik\Plugins\AOM\Settings;

class Importer extends \Piwik\Plugins\AOM\Platforms\Importer implements ImporterInterface
{
    public function import()
    {
        $settings = new Settings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'] as $id => $account) {
            if (array_key_exists('active', $account) && true === $account['active']) {
                foreach ($this->getPeriodAsArrayOfDates() as $date) {
                    $this->importAccount($id, $account, $date);
                }
            } else {
                $this->logger->info('Skipping inactive account.');
            }
        }
    }

    /**
     * @param string $id
     * @param array $account
     * @param string $date
     * @throws \Exception
     */
    private function importAccount($id, $account, $date)
    {
        $this->logger->info('Will import account ' . $id. ' for date ' . $date . ' now.');
        $this->deleteImportedData(FacebookAds::getDataTableName(), $account['websiteId'], $date);

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
                'INSERT INTO ' . FacebookAds::getDataTableName()
                    . ' (idsite, date, account_id, account_name, campaign_id, campaign_name, adset_id, adset_name, '
                    . 'ad_id, ad_name, impressions, inline_link_clicks, spend, ts_created) '
                    . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
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
}
