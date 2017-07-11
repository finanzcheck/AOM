<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Services;

use Piwik\Common;
use Piwik\Db;

class DatabaseHelperService
{
    /**
     * @param string $platformName
     * @return string
     */
    public static function getTableNameByPlatformName($platformName)
    {
        return Common::prefixTable('aom_' . strtolower($platformName));
    }

    /**
     * Adds a database table column unless it already exists.
     *
     * @param $sql
     * @throws \Exception
     */
    public static function addColumn($sql)
    {
        try {
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1060 code is for 'duplicate column')
            if (!Db::get()->isErrNo($e, '1060')) {
                throw $e;
            }
        }
    }

    /**
     * Adds a database table unless it already exists.
     *
     * @param $sql
     * @throws \Exception
     */
    public static function addTable($sql)
    {
        try {
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    /**
     * Adds an index to the database unless it already exists.
     *
     * @param $sql
     * @throws \Exception
     */
    public static function addIndex($sql)
    {
        try {
            Db::exec($sql);
        } catch (\Exception $e) {
            // ignore error if index already exists (1061)
            if (!Db::get()->isErrNo($e, '1061')) {
                throw $e;
            }
        }
    }
}
