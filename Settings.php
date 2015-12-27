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
     * Various plugin configuration stored as a serialized array in piwik_option.Plugin_AOM_CustomSettings.
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

    /**
     * @var SystemSetting
     */
    public $proxyIsActive;

    /**
     * @var SystemSetting
     */
    public $proxyHost;

    /**
     * @var SystemSetting
     */
    public $proxyPort;
    
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

        // Add proxy settings
        $this->createProxyIsActiveSetting();
        if ($this->proxyIsActive->getValue()) {
            $this->createProxyHostSetting();
            $this->createProxyPortSetting();
        }
    }

    private function createParamPrefixSetting()
    {
        $this->paramPrefix = new SystemSetting('paramPrefix', 'Parameter-Prefix');
        $this->paramPrefix->readableByCurrentUser = true;
        $this->paramPrefix->uiControlType = static::CONTROL_TEXT;
        $this->paramPrefix->defaultValue = 'aom';
        $this->paramPrefix->description = 'Prefix of URL-parameter, e.g. "aom" for "aom_campaign_id';

        $this->addSetting($this->paramPrefix);
    }

    private function createPlatformAdWordsIsActiveSetting()
    {
        $this->platformAdWordsIsActive = new SystemSetting('platformAdWordsIsActive', 'Enable AdWords');
        $this->platformAdWordsIsActive->readableByCurrentUser = true;
        $this->platformAdWordsIsActive->type  = static::TYPE_BOOL;
        $this->platformAdWordsIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformAdWordsIsActive->description   = 'Enable AdWords';
        $this->platformAdWordsIsActive->defaultValue  = false;

        $this->addSetting($this->platformAdWordsIsActive);
    }

    private function createPlatformBingIsActiveSetting()
    {
        $this->platformBingIsActive = new SystemSetting('platformBingIsActive', 'Enable Bing');
        $this->platformBingIsActive->readableByCurrentUser = true;
        $this->platformBingIsActive->type  = static::TYPE_BOOL;
        $this->platformBingIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformBingIsActive->description   = 'Enable Bing';
        $this->platformBingIsActive->defaultValue  = false;

        $this->addSetting($this->platformBingIsActive);
    }

    private function createPlatformCriteoIsActiveSetting()
    {
        $this->platformCriteoIsActive = new SystemSetting('platformCriteoIsActive', 'Enable Criteo');
        $this->platformCriteoIsActive->readableByCurrentUser = true;
        $this->platformCriteoIsActive->type  = static::TYPE_BOOL;
        $this->platformCriteoIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformCriteoIsActive->description   = 'Enable Criteo';
        $this->platformCriteoIsActive->defaultValue  = false;

        $this->addSetting($this->platformCriteoIsActive);
    }

    private function createPlatformFacebookAdsIsActiveSetting()
    {
        $this->platformFacebookAdsIsActive = new SystemSetting('platformFacebookAdsIsActive', 'Enable Facebook Ads');
        $this->platformFacebookAdsIsActive->readableByCurrentUser = true;
        $this->platformFacebookAdsIsActive->type  = static::TYPE_BOOL;
        $this->platformFacebookAdsIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->platformFacebookAdsIsActive->description   = 'Enable Facebook Ads';
        $this->platformFacebookAdsIsActive->defaultValue  = false;

        $this->addSetting($this->platformFacebookAdsIsActive);
    }

    private function createProxyIsActiveSetting() {
        $this->proxyIsActive = new SystemSetting('proxyIsActive', 'Enable Proxy');
        $this->proxyIsActive->readableByCurrentUser = true;
        $this->proxyIsActive->type  = static::TYPE_BOOL;
        $this->proxyIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->proxyIsActive->description   = 'Enable Proxy';
        $this->proxyIsActive->defaultValue  = false;

        $this->addSetting($this->proxyIsActive);
    }

    private function createProxyHostSetting()
    {
        $this->proxyHost = new SystemSetting('proxyHost', 'Proxy Host');
        $this->proxyHost->readableByCurrentUser = true;
        $this->proxyHost->uiControlType = static::CONTROL_TEXT;
        $this->proxyHost->description = 'Proxy Host, e.g. "proxy.internal"';

        $this->addSetting($this->proxyHost);
    }

    private function createProxyPortSetting()
    {
        $this->proxyPort = new SystemSetting('proxyPort', 'Proxy Port');
        $this->proxyPort->readableByCurrentUser = true;
        $this->proxyPort->uiControlType = static::CONTROL_TEXT;
        $this->proxyPort->description = 'Proxy Port, e.g. "3128"';

        $this->addSetting($this->proxyPort);
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

            $this->configuration = unserialize($optionValue);
        }

        return $this->configuration;
    }

    public function setConfiguration(array $configuration) {
        Option::set('Plugin_AOM_CustomSettings', serialize($configuration));
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
