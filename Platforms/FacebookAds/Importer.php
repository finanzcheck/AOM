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
    public function import($startDate, $endDate)
    {
        $settings = new Settings();
        $configuration = $settings->getConfiguration();

        foreach ($configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'] as $id => $account) {
            if (array_key_exists('active', $account) && true === $account['active']) {
                $this->importAccount($account, $startDate, $endDate);
            } else {
                var_dump('Skipping inactive account.'); // TODO: Use better logging!
            }
        }
    }

    private function importAccount($account, $startDate, $endDate)
    {
        // Delete existing data for the specified period
        // TODO: this might be more complicated when we already merged / assigned data to visits!?!
        // TODO: There might be more than 100000 rows?!
        Db::deleteAllRows(
            FacebookAds::getDataTableName(),
            'WHERE date >= ? AND date <= ?',
            'date',
            100000,
            [$startDate, $endDate]
        );

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
                'since' => $startDate,
                'until' => $endDate,
            ],
        ]);

        // TODO: Use MySQL transaction to improve performance!
        foreach ($insights as $insight) {
            Db::query(
                'INSERT INTO ' . FacebookAds::getDataTableName()
                    . ' (idsite, date, account_id, account_name, campaign_id, campaign_name, adset_id, adset_name, '
                    . 'ad_id, ad_name, impressions, inline_link_clicks, spend) '
                    . 'VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
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
