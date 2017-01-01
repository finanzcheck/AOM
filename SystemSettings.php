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
use Piwik\Plugins\AOM\Platforms\AdWords\AdWords;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Plugin\SystemSetting;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
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
    public $platformAdWordsTrackingVariant;

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
        // Generic settings
        $this->paramPrefix = $this->createParamPrefixSetting();

        // Add settings for platforms
        $this->platformAdWordsIsActive = $this->createPlatformAdWordsIsActiveSetting();
        $this->platformAdWordsTrackingVariant = $this->createPlatformAdWordsTrackingVariantSetting();
        $this->platformBingIsActive = $this->createPlatformBingIsActiveSetting();
        $this->platformCriteoIsActive = $this->createPlatformCriteoIsActiveSetting();
        $this->platformFacebookAdsIsActive = $this->createPlatformFacebookAdsIsActiveSetting();
    }

    private function createParamPrefixSetting()
    {
        return $this->makeSetting(
            'paramPrefix',
            $default = 'aom',
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) {
                $field->title = Piwik::translate('AOM_PluginSettings_Setting_ParamPrefix_Title');
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = Piwik::translate('AOM_PluginSettings_Setting_ParamPrefix_Description');
            }
        );
    }

    private function createPlatformAdWordsIsActiveSetting()
    {
        return $this->makeSetting(
            'platformAdWordsIsActive',
            $default = false,
            FieldConfig::TYPE_BOOL,
            function (FieldConfig $field) {
                $field->title = Piwik::translate('AOM_PluginSettings_Setting_EnableAdWords_Title');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            }
        );
    }

    private function createPlatformAdWordsTrackingVariantSetting()
    {
        return $this->makeSetting(
            'platformAdWordsTrackingVariant',
            $default = AdWords::TRACKING_VARIANT_GCLID,
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) {
                $field->title = Piwik::translate('AOM_PluginSettings_Setting_AdWordsTrackingVariant_Title');
                $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
                $field->availableValues = [
                    AdWords::TRACKING_VARIANT_GCLID =>
                        Piwik::translate('AOM_PluginSettings_Setting_AdWordsTrackingVariant_OptionGclid'),
                    AdWords::TRACKING_VARIANT_REGULAR_PARAMS =>
                        Piwik::translate('AOM_PluginSettings_Setting_AdWordsTrackingVariant_OptionRegularParams'),
                ];
                $field->description = Piwik::translate('AOM_PluginSettings_Setting_AdWordsTrackingVariant_Description');
            }
        );
    }

    private function createPlatformBingIsActiveSetting()
    {
        return $this->makeSetting(
            'platformBingIsActive',
            $default = false,
            FieldConfig::TYPE_BOOL,
            function (FieldConfig $field) {
                $field->title = Piwik::translate('AOM_PluginSettings_Setting_EnableBing_Title');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            }
        );
    }

    private function createPlatformCriteoIsActiveSetting()
    {
        return $this->makeSetting(
            'platformCriteoIsActive',
            $default = false,
            FieldConfig::TYPE_BOOL,
            function (FieldConfig $field) {
                $field->title = Piwik::translate('AOM_PluginSettings_Setting_EnableCriteo_Title');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            }
        );
    }

    private function createPlatformFacebookAdsIsActiveSetting()
    {
        return $this->makeSetting(
            'platformFacebookAdsIsActive',
            $default = false,
            FieldConfig::TYPE_BOOL,
            function (FieldConfig $field) {
                $field->title = Piwik::translate('AOM_PluginSettings_Setting_EnableFacebookAds_Title');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            }
        );
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
