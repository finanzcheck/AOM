<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Exception;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;

class Installer implements InstallerInterface
{
    public function installPlugin()
    {
        try {
            $sql = 'CREATE TABLE ' . FacebookAds::getDataTableName() . ' (
                        idsite INTEGER NOT NULL,
                        date DATE NOT NULL,
                        account_id BIGINT NOT NULL,
                        account_name VARCHAR(255) NOT NULL,
                        campaign_group_id BIGINT NOT NULL,
                        campaign_group_name VARCHAR(255) NOT NULL,
                        campaign_id BIGINT NOT NULL,
                        campaign_name VARCHAR(255) NOT NULL,
                        adgroup_id BIGINT NOT NULL,
                        adgroup_name VARCHAR(255) NOT NULL,
                        adgroup_objective VARCHAR(255) NOT NULL,
                        spend FLOAT NOT NULL,
                        total_actions INTEGER NOT NULL
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE INDEX index_aom_facebook ON ' . FacebookAds::getDataTableName()
                . ' (date, account_id)';  // TODO...
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if index already exists (1061)
            if (!Db::get()->isErrNo($e, '1061')) {
                throw $e;
            }
        }
    }

    public function uninstallPlugin()
    {
        Db::dropTables(FacebookAds::getDataTableName());
    }
}
