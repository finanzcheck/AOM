<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Db;

abstract class Importer
{
    /**
     * @var PlatformInterface
     */
    protected $platform;

    public function __construct(PlatformInterface $platform)
    {
        $this->platform = $platform;
    }
}
