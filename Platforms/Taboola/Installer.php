<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Taboola;

use Exception;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;
use Piwik\Plugins\AOM\Services\DatabaseHelperService;

class Installer implements InstallerInterface
{
    /**
     * Sets up a platform (e.g. adds tables and indices).
     *
     * @throws Exception
     */
    public function installPlugin()
    {
        DatabaseHelperService::addTable(
            'CREATE TABLE ' . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA) . ' (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_account_internal VARCHAR(50) NOT NULL,
                idsite INTEGER NOT NULL,
                date DATE NOT NULL,
                campaign_id INTEGER NOT NULL,
                campaign VARCHAR(255) NOT NULL,
                site_id VARCHAR(255) NOT NULL,
                site VARCHAR(255) NOT NULL,
                impressions INTEGER NOT NULL,
                clicks INTEGER NOT NULL,
                cost FLOAT NOT NULL,
                conversions INTEGER NOT NULL,
                ts_created TIMESTAMP
            )  DEFAULT CHARSET=utf8');

        // Avoid issues from parallel imports
        DatabaseHelperService::addIndex(
            'CREATE UNIQUE INDEX index_aom_taboola_unique ON '
            . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA)
            . ' (idsite, date, campaign_id, site_id)'
        );

        // Optimize for queries from MarketingPerformanceController.php
        DatabaseHelperService::addIndex(
            'CREATE INDEX index_aom_taboola ON '
            . DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA) . ' (idsite, date)');
    }

    /**
     * Cleans up platform specific stuff such as tables and indices when the plugin is being uninstalled.
     *
     * @throws Exception
     */
    public function uninstallPlugin()
    {
        Db::dropTables(DatabaseHelperService::getTableNameByPlatformName(AOM::PLATFORM_TABOOLA));
    }
}
