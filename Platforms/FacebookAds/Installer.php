<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;

class Installer implements InstallerInterface
{
    public function installPlugin()
    {
        DatabaseHelperService::addTable(
            'CREATE TABLE ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS) . ' (
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
        DatabaseHelperService::addIndex(
            'CREATE UNIQUE INDEX index_aom_facebook_unique ON '
            . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS)
            . ' (idsite, date, account_id, campaign_id, adset_id, ad_id)'
        );

        // Optimize for queries from MarketingPerformanceController.php
        DatabaseHelperService::addIndex(
            'CREATE INDEX index_aom_facebook ON '
            . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS) . ' (idsite, date)');
    }

    public function uninstallPlugin()
    {
        Db::dropTables(DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_FACEBOOK_ADS));
    }
}
