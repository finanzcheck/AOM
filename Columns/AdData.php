<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Db;
use Piwik\Plugin\Dimension\VisitDimension;

class AdData extends VisitDimension
{
    protected $columnName = 'aom_ad_data';
    protected $columnType = 'VARCHAR(1024) NULL';
}
