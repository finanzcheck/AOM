<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Exception;

interface InstallerInterface
{
    /**
     * Sets up a platform (e.g. adds tables and indices).
     *
     * @throws Exception
     */
    public function installPlugin();

    /**
     * Cleans up platform specific stuff such as tables and indices when the plugin is being uninstalled.
     *
     * @throws Exception
     */
    public function uninstallPlugin();
}
