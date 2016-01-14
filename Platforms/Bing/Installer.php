<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Exception;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\InstallerInterface;

class Installer implements InstallerInterface
{
    public function installPlugin()
    {
        try {
            $sql = 'CREATE TABLE ' . Bing::getDataTableNameStatic() . ' (
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
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE INDEX index_aom_bing ON ' . Bing::getDataTableNameStatic() . ' (date, campaign_id)'; // TODO...
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
        Db::dropTables(Bing::getDataTableNameStatic());
    }
}
