<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\IndividualCampaigns;

use Exception;
use Piwik\Db;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;

class Installer implements InstallerInterface
{
    /**
     * Sets up a platform (e.g. adds tables and indices).
     *
     * @throws Exception
     */
    public function installPlugin()
    {
        // TODO: Do we really need id_account_internal?!

        AOM::addDatabaseTable(
            'CREATE TABLE ' . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS) . ' (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_account_internal VARCHAR(50) NOT NULL,
                idsite INTEGER NOT NULL,
                date DATE NOT NULL,
                campaign VARCHAR(255) NOT NULL,
                date_campaign_group VARCHAR(255) NOT NULL,
                params_substring TEXT NOT NULL,
                referrer_substring TEXT NOT NULL,
                cost FLOAT NOT NULL,
                created_by INTEGER NOT NULL,
                ts_created TIMESTAMP
            )  DEFAULT CHARSET=utf8');

        // Optimize for queries from MarketingPerformanceController.php
        AOM::addDatabaseIndex(
            'CREATE INDEX index_aom_individiual_campaigns ON '
            . AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS) . ' (idsite, date)');
    }

    /**
     * Cleans up platform specific stuff such as tables and indices when the plugin is being uninstalled.
     *
     * @throws Exception
     */
    public function uninstallPlugin()
    {
        Db::dropTables(AOM::getPlatformDataTableNameByPlatformName(AOM::PLATFORM_INDIVIDUAL_CAMPAIGNS));
    }
}
