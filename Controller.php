<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Common;
use Piwik\Piwik;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    /**
     * @return string
     */
    public function settings()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $settings = new SystemSettings();

        $viewVariables = [
            'accounts' => $settings->getAccounts(),
        ];

        foreach (AOM::getPlatforms() as $platform) {
            $viewVariables[lcfirst($platform) . 'IsActive'] =
                $settings->{'platform' . $platform . 'IsActive'}->getValue();
        }

        return $this->renderTemplate('settings', $viewVariables);
    }

    /**
     * This method routes method calls to the advertising platform's individual controllers.
     *
     * @throws \Exception
     */
    public function platformAction()
    {
        Piwik::checkUserHasSomeAdminAccess();

        // Platform supported?
        $platform = Common::getRequestVar('platform', false);
        if (!in_array($platform, AOM::getPlatforms())) {
            throw new \Exception('Platform "' . $platform . '" is not supported.');
        }

        $className = 'Piwik\\Plugins\\AOM\\Platforms\\' . $platform . '\\Controller';

        $controller = new $className($platform);

        // Does method exist?
        $method = Common::getRequestVar('method', false);
        if (!method_exists($controller, $method)) {
            throw new \Exception('Method "' . $method . '" does not exist in platform "' . $platform . '".');
        }

        // Params must be JSON
        $params = $platform = Common::getRequestVar('params', false);
        if (false != $params) {

            $params = str_replace('&quot;', '"', $params);

            $params = @json_decode($params, true);
            if (json_last_error() != JSON_ERROR_NONE || !is_array($params)) {
                throw new \Exception('Invalid JSON passed to Controller->platformAction().');
            }
        }

        // Call controller method
        if (is_array($params)) {
            return call_user_func_array([$controller, $method], $params);
        } else {
            return $controller->{$method}();
        }
    }
}
