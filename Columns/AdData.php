<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Columns;

use Piwik\Db;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class AdData extends VisitDimension
{
    protected $columnName = 'aom_ad_data';
    protected $columnType = 'VARCHAR(1024) NULL';
}
