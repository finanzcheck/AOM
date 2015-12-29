<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

interface ControllerInterface
{
    // Method arguments are dynamic
    // public function addAccount();

    /**
     * @param string $id
     * @return mixed
     */
    public function deleteAccount($id);
}
