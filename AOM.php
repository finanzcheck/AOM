<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Common;
use Piwik\Db;

class AOM extends \Piwik\Plugin
{
    /**
     * Installs the plugin.
     *
     * @throws \Exception
     */
    public function activate()
    {
        try {
            $sql = 'CREATE TABLE ' . Common::prefixTable('aom_adwords') . ' (
                        date DATE NOT NULL,
                        account VARCHAR(255) NOT NULL,
                        campaign_id BIGINT NOT NULL,
                        campaign VARCHAR(255) NOT NULL,
                        ad_group_id BIGINT NOT NULL,
                        ad_group VARCHAR(255) NOT NULL,
                        keyword_id BIGINT NOT NULL,
                        keyword_placement VARCHAR(255) NOT NULL,
                        criteria_type VARCHAR(255) NOT NULL,
                        network CHAR(1) NOT NULL,
                        device CHAR(1) NOT NULL,
                        impressions INTEGER NOT NULL,
                        clicks INTEGER NOT NULL,
                        cost FLOAT NOT NULL,
                        conversions INTEGER NOT NULL
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE INDEX index_aom_adwords ON ' . Common::prefixTable('aom_adwords')
                . ' (date, campaign_id, ad_group_id, keyword_id)';  // TODO...
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE TABLE ' . Common::prefixTable('aom_criteo') . ' (
                        date DATE NOT NULL,
                        campaign_id INTEGER NOT NULL,
                        campaign VARCHAR(255) NOT NULL,
                        impressions INTEGER NOT NULL,
                        clicks INTEGER NOT NULL,
                        cost FLOAT NOT NULL,
                        conversions INTEGER NOT NULL,
                        conversions_value FLOAT NOT NULL,
                        conversions_post_view INTEGER NOT NULL,
                        conversions_post_view_value FLOAT NOT NULL
                    )  DEFAULT CHARSET=utf8';
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }

        try {
            $sql = 'CREATE INDEX index_aom_criteo ON ' . Common::prefixTable('aom_criteo')
                . ' (date, campaign_id)';  // TODO...
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    /**
     * Uninstalls the plugin.
     */
    public function uninstall()
    {
        Db::dropTables(Common::prefixTable('aom_adwords'));
        Db::dropTables(Common::prefixTable('aom_criteo'));
    }
}
