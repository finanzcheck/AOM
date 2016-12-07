<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;

class Installer implements InstallerInterface
{
    public function installPlugin()
    {
        AOM::addDatabaseTable(
            'CREATE TABLE ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS) . ' (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_account_internal VARCHAR(50) NOT NULL,
                idsite INTEGER NOT NULL,
                date DATE NOT NULL,
                account_id BIGINT NOT NULL,
                account_name VARCHAR(255) NOT NULL,
                campaign_id BIGINT NOT NULL,
                campaign_name VARCHAR(255) NOT NULL,
                adset_id BIGINT NOT NULL,
                adset_name VARCHAR(255) NOT NULL,
                ad_id BIGINT NOT NULL,
                ad_name VARCHAR(255) NOT NULL,
                impressions INTEGER NOT NULL,
                clicks INTEGER NOT NULL,
                cost FLOAT NOT NULL,
                ts_created TIMESTAMP
            )  DEFAULT CHARSET=utf8');

        // Avoid issues from parallel imports
        AOM::addDatabaseIndex(
            'CREATE UNIQUE INDEX index_aom_criteo_unique ON '
            . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS)
            . ' (idsite, date, account_id, campaign_id, adset_id, ad_id)'
        );

        // Optimize for queries from MarketingPerformanceController.php
        AOM::addDatabaseIndex(
            'CREATE INDEX index_aom_facebook ON '
            . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS) . ' (idsite, date)');
    }

    public function uninstallPlugin()
    {
        Db::dropTables(AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS));
    }
}
