<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Plugins\AOM\Platforms\AbstractPlatform;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\View;

class VisitorDetails extends VisitorDetailsAbstract
{
    /**
     * @param array $action
     * @param array $previousAction
     * @param array $visitorDetails
     * @return string|void
     */
    public function renderAction($action, $previousAction, $visitorDetails)
    {
        if (false === $previousAction) {

            $additionalDescription = AbstractPlatform::getHumanReadableDescriptionForVisit($visitorDetails['idVisit']);

            if ($additionalDescription) {

                $view = new View('@AOM/_visitorProfileAomAdditionalDescription.twig');
                $view->additionalDescription = $additionalDescription;

                echo $view->render();
            }
        }

        parent::renderAction($action, $previousAction, $visitorDetails);
    }
}
