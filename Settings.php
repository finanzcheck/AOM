<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\NoAccessException;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Settings\SystemSetting;

class Settings extends \Piwik\Plugin\Settings
{
    /**
     * Various plugin configuration stored as a serialized array in option.Plugin_AOM_CustomSettings.
     *
     * @var string
     */
    private $configuration;

    /**
     * @var SystemSetting
     */
    public $paramPrefix;

    /**
     * @var SystemSetting
     */
    public $platformAdWordsIsActive;

    /**
     * @var SystemSetting
     */
    public $platformBingIsActive;

    /**
     * @var SystemSetting
     */
    public $platformCriteoIsActive;

    /**
     * @var SystemSetting
     */
    public $platformFacebookAdsIsActive;


    protected function init()
    {
        $this->setIntroduction(Piwik::translate('AOM_PluginSettings_Introduction'));

        // Generic settings
        $this->createParamPrefixSetting();

        // Add settings for platforms
        $this->createPlatformAdWordsIsActiveSetting();
        $this->createPlatformBingIsActiveSetting();
        $this->createPlatformCriteoIsActiveSetting();
        $this->createPlatformFacebookAdsIsActiveSetting();
    }

    private function createParamPrefixSetting()
    {
        $this->paramPrefix = new SystemSetting(
            'paramPrefix',
            Piwik::translate('AOM_PluginSettings_Setting_ParamPrefix_Title')
        );
        $this->paramPrefix->readableByCurrentUser = true;
        $this->paramPrefix->uiControlType = static::CONTROL_TEXT;
        $this->paramPrefix->defaultValue = 'aom';
        $this->paramPrefix->description = Piwik::translate('AOM_PluginSettings_Setting_ParamPrefix_Description');

        $this->addSetting($this->paramPrefix);
    }

    private function createPlatformAdWordsIsActiveSetting()
    {
        $this->platformAdWordsIsActive = new SystemSetting(
            'platformAdWordsIsActive',
            Piwik::translate('AOM_PluginSettings_Setting_EnableAdWords_Title')
        );
        $this->platformAdWordsIsActive->readableByCurrentUser = true;
        $this->platformAdWordsIsActive->type  = static::TYPE_BOOL;
        $this->platformAdWordsIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformAdWordsIsActive->defaultValue  = false;

        $this->addSetting($this->platformAdWordsIsActive);
    }

    private function createPlatformBingIsActiveSetting()
    {
        $this->platformBingIsActive = new SystemSetting(
            'platformBingIsActive',
            Piwik::translate('AOM_PluginSettings_Setting_EnableBing_Title')
        );
        $this->platformBingIsActive->readableByCurrentUser = true;
        $this->platformBingIsActive->type  = static::TYPE_BOOL;
        $this->platformBingIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformBingIsActive->defaultValue  = false;

        $this->addSetting($this->platformBingIsActive);
    }

    private function createPlatformCriteoIsActiveSetting()
    {
        $this->platformCriteoIsActive = new SystemSetting(
            'platformCriteoIsActive',
            Piwik::translate('AOM_PluginSettings_Setting_EnableCriteo_Title')
        );
        $this->platformCriteoIsActive->readableByCurrentUser = true;
        $this->platformCriteoIsActive->type  = static::TYPE_BOOL;
        $this->platformCriteoIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformCriteoIsActive->defaultValue  = false;

        $this->addSetting($this->platformCriteoIsActive);
    }

    private function createPlatformFacebookAdsIsActiveSetting()
    {
        $this->platformFacebookAdsIsActive = new SystemSetting(
            'platformFacebookAdsIsActive',
            Piwik::translate('AOM_PluginSettings_Setting_EnableFacebookAds_Title')
        );
        $this->platformFacebookAdsIsActive->readableByCurrentUser = true;
        $this->platformFacebookAdsIsActive->type  = static::TYPE_BOOL;
        $this->platformFacebookAdsIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformFacebookAdsIsActive->defaultValue  = false;

        $this->addSetting($this->platformFacebookAdsIsActive);
    }

    public function getConfiguration()
    {
        if (!$this->configuration) {

            $optionValue = Option::get('Plugin_AOM_CustomSettings');

            if ($optionValue === false) {

                // TODO: Initialize this when installing the plugin?!
                $defaultConfiguration = [];
                foreach (AOM::getPlatforms() as $platform) {
                    $defaultConfiguration[$platform] = ['accounts' => [],];
                }

                // TODO: Is autoload = 1 a good idea?!
                Option::set('Plugin_AOM_CustomSettings', serialize($defaultConfiguration), 1);
                $optionValue = serialize($defaultConfiguration);
            }

            $this->configuration = @json_decode($optionValue, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $this->configuration = unserialize($optionValue);
            }
        }

        return $this->configuration;
    }

    public function setConfiguration(array $configuration) {
        Option::set('Plugin_AOM_CustomSettings', json_encode($configuration));
        $this->configuration = $configuration;
    }

    /**
     * @param bool $validateAccessPrivileges
     * @return array
     */
    public function getAccounts($validateAccessPrivileges = true)
    {
        // Limit list of accounts to those the user is allowed to access (based on website access)
        $accounts = $this->getConfiguration();

        if ($validateAccessPrivileges) {
            foreach (AOM::getPlatforms() as $platform) {
                if (array_key_exists($platform, $accounts)) {
                    foreach ($accounts[$platform]['accounts'] as $id => $account) {
                        try {
                            Piwik::checkUserHasAdminAccess($idSites = [$account['websiteId']]);
                        } catch (NoAccessException $e) {
                            unset($accounts[$platform]['accounts'][$id]);
                        }
                    }
                }
            }
        }

        return $accounts;
    }
}
