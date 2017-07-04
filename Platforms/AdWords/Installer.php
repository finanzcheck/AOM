<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\AdWords;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;

class Installer implements InstallerInterface
{
    public function installPlugin()
    {
        // aom_adwords
        $tableName = DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_AD_WORDS);

        DatabaseHelperService::addTable(
            'CREATE TABLE ' . $tableName . ' (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_account_internal VARCHAR(50) NOT NULL,
                idsite INTEGER NOT NULL,
                date DATE NOT NULL,
                account VARCHAR(255) NOT NULL,
                campaign_id BIGINT NOT NULL,
                campaign VARCHAR(255) NOT NULL,
                ad_group_id BIGINT NOT NULL,
                ad_group VARCHAR(255) NOT NULL,
                keyword_id BIGINT,
                keyword_placement VARCHAR(255),
                criteria_type VARCHAR(255),
                network CHAR(1) NOT NULL,
                impressions INTEGER NOT NULL,
                clicks INTEGER NOT NULL,
                cost FLOAT NOT NULL,
                conversions INTEGER NOT NULL,
                unique_hash VARCHAR(100) NOT NULL,
                ts_created TIMESTAMP
            )  DEFAULT CHARSET=utf8');

        // Avoid issues from parallel imports
        DatabaseHelperService::addIndex(
            'CREATE UNIQUE INDEX index_aom_adwords_unique ON ' . $tableName . ' (unique_hash)'
        );

        // Optimize for queries from MarketingPerformanceController.php
        DatabaseHelperService::addIndex(
            'CREATE INDEX index_aom_adwords ON ' . $tableName . ' (idsite, date)'
        );

        // aom_adwords_gclid
        DatabaseHelperService::addTable(
            'CREATE TABLE ' .  $tableName . '_gclid (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_account_internal VARCHAR(50) NOT NULL,
                idsite INTEGER NOT NULL,
                date DATE NOT NULL,
                account VARCHAR(255) NOT NULL,
                campaign_id BIGINT NOT NULL,
                campaign VARCHAR(255) NOT NULL,
                ad_group_id BIGINT NOT NULL,
                ad_group VARCHAR(255) NOT NULL,
                keyword_id BIGINT NOT NULL,
                keyword_placement VARCHAR(255) NOT NULL,
                match_type VARCHAR(255) NOT NULL,
                ad_id BIGINT NOT NULL,
                ad_type VARCHAR(255) NOT NULL,
                network VARCHAR(255) NOT NULL,
                device VARCHAR(255) NOT NULL,
                gclid VARCHAR(255) NOT NULL,
                ts_created TIMESTAMP
            )  DEFAULT CHARSET=utf8');

        // Avoid issues from parallel imports and ensure faster queries
        DatabaseHelperService::addIndex(
            'CREATE UNIQUE INDEX index_aom_adwords_gclid ON ' . $tableName . '_gclid (idsite, date, gclid)'
        );
    }

    public function uninstallPlugin()
    {
        // aom_adwords
        $tableName = DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_AD_WORDS);

        Db::dropTables($tableName);
        Db::dropTables($tableName . '_gclid');
    }
}
