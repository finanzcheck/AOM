<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Plugins\AOM\Platforms\PlatformInterface;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::isUserHasSomeAdminAccess()) {
            $menu->addSystemItem('AOM_Menu_Settings', $this->urlForAction('settings'), $orderId = 26);
        }

        // Platforms might add additional menu options
        if (Piwik::isUserHasSomeAdminAccess()) {
            foreach (AOM::getPlatforms() as $platformName) {

                /** @var PlatformInterface $platform */
                $platform = AOM::getPlatformInstance($platformName);
                if ($platform->isActive()) {
                    foreach ($platform->getMenuAdminItems() as $menuAdminItem) {
                        $menu->addSystemItem(
                            $menuAdminItem['menuName'],
                            $this->urlForAction(
                                'platformAction',
                                array_merge(['platform' => $platformName], $menuAdminItem['params'])
                            ),
                            $orderId = $menuAdminItem['orderId']
                        );
                    };
                }
            }
        }
    }
}
