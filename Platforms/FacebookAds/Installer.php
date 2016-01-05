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
            $sql = 'CREATE TABLE ' . FacebookAds::getDataTableNameStatic() . ' (
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
                        inline_link_clicks INTEGER NOT NULL,
                        spend FLOAT NOT NULL,
                        ts_created TIMESTAMP
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE INDEX index_aom_facebook ON ' . FacebookAds::getDataTableNameStatic()
                . ' (idsite, date, account_id)';  // TODO...
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
        Db::dropTables(FacebookAds::getDataTableNameStatic());
    }
}
