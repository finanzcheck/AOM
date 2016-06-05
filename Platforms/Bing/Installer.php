<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;

class Installer implements InstallerInterface
{
    public function installPlugin()
    {
        AOM::addDatabaseTable(
            'CREATE TABLE ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING) . ' (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_account_internal VARCHAR(50) NOT NULL,
                idsite INTEGER NOT NULL,
                date DATE NOT NULL,
                account_id INTEGER NOT NULL,
                account VARCHAR(255) NOT NULL,
                campaign_id INTEGER NOT NULL,
                campaign VARCHAR(255) NOT NULL,
                ad_group_id BIGINT NOT NULL,
                ad_group VARCHAR(255) NOT NULL,
                keyword_id BIGINT NOT NULL,
                keyword VARCHAR(255) NOT NULL,
                impressions INTEGER NOT NULL,
                clicks INTEGER NOT NULL,
                cost FLOAT NOT NULL,
                conversions INTEGER NOT NULL,
                ts_created TIMESTAMP
            )  DEFAULT CHARSET=utf8');

        // Optimize for queries from MarketingPerformanceController.php
        AOM::addDatabaseIndex(
            'CREATE INDEX index_aom_bing ON ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING)
            . ' (idsite, date)');
    }

    public function uninstallPlugin()
    {
        Db::dropTables(AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_BING));
    }
}
