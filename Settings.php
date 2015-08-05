<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use Piwik\Settings\SystemSetting;

class Settings extends \Piwik\Plugin\Settings
{
    /** @var SystemSetting */
    public $adId;

    /** @var SystemSetting */
    public $adWordsIsActive;

    /** @var SystemSetting */
    public $adWordsDeveloperToken;

    /** @var SystemSetting */
    public $adWordsUserAgent;

    /** @var SystemSetting */
    public $adWordsClientCustomerId;

    /** @var SystemSetting */
    public $adWordsClientId;

    /** @var SystemSetting */
    public $adWordsClientSecret;

    /** @var SystemSetting */
    public $adWordsRefreshToken;

    /** @var SystemSetting */
    public $criteoIsActive;

    /** @var SystemSetting */
    public $criteoAppToken;

    /** @var SystemSetting */
    public $criteoUsername;

    /** @var SystemSetting */
    public $criteoPassword;

    protected function init()
    {
//        $this->setIntroduction('...');

        // Add generic fields
        $this->createAdIdSetting();

        // Add fields for AdWords
        $this->createAdWordsIsActiveSetting();
        if($this->adWordsIsActive->getValue()) {
            $this->createAdWordsDeveloperTokenSetting();
            $this->createAdWordsUserAgentSetting();
            $this->createAdWordsClientCustomerIdSetting();
            $this->createAdWordsClientIdSetting();
            $this->createAdWordsClientSecretSetting();
            $this->createAdWordsRefreshTokenSetting();
        }

        // Add fields for Criteo
        $this->createCriteoIsActiveSetting();
        if($this->criteoIsActive->getValue()) {
            $this->createCriteoAppTokenSetting();
            $this->createCriteoUsernameSetting();
            $this->createCriteoPasswordSetting();
        }
    }

    private function createAdIdSetting()
    {
        $this->adId = new SystemSetting('adId', 'Ad-ID-Parameter');
        $this->adId->readableByCurrentUser = true;
        $this->adId->uiControlType = static::CONTROL_TEXT;
        $this->adId->defaultValue = 'ad_id';
        $this->adId->description = 'URL-Parameter used for tracking, e.g. "ad_id"';

        $this->addSetting($this->adId);
    }

    private function createAdWordsIsActiveSetting()
    {
        $this->adWordsIsActive = new SystemSetting('adWordsIsActive', 'Enable AdWords');
        $this->adWordsIsActive->readableByCurrentUser = true;
        $this->adWordsIsActive->type  = static::TYPE_BOOL;
        $this->adWordsIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->adWordsIsActive->description   = 'Enable AdWords';
        $this->adWordsIsActive->defaultValue  = false;


        $this->addSetting($this->adWordsIsActive);
    }

    private function createAdWordsDeveloperTokenSetting()
    {
        $this->adWordsDeveloperToken = new SystemSetting('adWordsDeveloperToken', 'Developer Token');
        $this->adWordsDeveloperToken->readableByCurrentUser = true;
        $this->adWordsDeveloperToken->uiControlType = static::CONTROL_TEXT;
        $this->adWordsDeveloperToken->description = 'Developer Token provided by AdWords, e.g. "FC3pR4t6t2euFQKVAtpkS5"';

        $this->addSetting($this->adWordsDeveloperToken);
    }

    private function createAdWordsUserAgentSetting()
    {
        $this->adWordsUserAgent = new SystemSetting('adWordsUserAgent', 'User Agent');
        $this->adWordsUserAgent->readableByCurrentUser = true;
        $this->adWordsUserAgent->uiControlType = static::CONTROL_TEXT;
        $this->adWordsUserAgent->description = 'User Agent, e.g. "Piwik for Acme Corporation"';

        $this->addSetting($this->adWordsUserAgent);
    }

    private function createAdWordsClientCustomerIdSetting()
    {
        $this->adWordsClientCustomerId = new SystemSetting('adWordsClientCustomerId', 'Client Customer ID');
        $this->adWordsClientCustomerId->readableByCurrentUser = true;
        $this->adWordsClientCustomerId->uiControlType = static::CONTROL_TEXT;
        $this->adWordsClientCustomerId->description = 'Client Customer ID, e.g. "613-741-8261"';

        $this->addSetting($this->adWordsClientCustomerId);
    }

    private function createAdWordsClientIdSetting()
    {
        $this->adWordsClientId = new SystemSetting('adWordsClientId', 'Client ID');
        $this->adWordsClientId->readableByCurrentUser = true;
        $this->adWordsClientId->uiControlType = static::CONTROL_TEXT;
        $this->adWordsClientId->description = 'Client ID';

        $this->addSetting($this->adWordsClientId);
    }

    private function createAdWordsClientSecretSetting()
    {
        $this->adWordsClientSecret = new SystemSetting('adWordsClientSecret', 'Client Secret');
        $this->adWordsClientSecret->readableByCurrentUser = true;
        $this->adWordsClientSecret->uiControlType = static::CONTROL_TEXT;
        $this->adWordsClientSecret->description = 'Client Secret';

        $this->addSetting($this->adWordsClientSecret);
    }

    private function createAdWordsRefreshTokenSetting()
    {
        $this->adWordsRefreshToken = new SystemSetting('adWordsRefreshToken', 'Refresh Token');
        $this->adWordsRefreshToken->readableByCurrentUser = true;
        $this->adWordsRefreshToken->uiControlType = static::CONTROL_TEXT;
        $this->adWordsRefreshToken->description = 'Refresh Token';

        $this->addSetting($this->adWordsRefreshToken);
    }

    private function createCriteoIsActiveSetting()
    {
        $this->criteoIsActive = new SystemSetting('criteoIsActive', 'Enable Criteo');
        $this->criteoIsActive->readableByCurrentUser = true;
        $this->criteoIsActive->type  = static::TYPE_BOOL;
        $this->criteoIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->criteoIsActive->description   = 'Enable Criteo';
        $this->criteoIsActive->defaultValue  = false;


        $this->addSetting($this->criteoIsActive);
    }

    private function createCriteoAppTokenSetting()
    {
        $this->criteoAppToken = new SystemSetting('criteoAppToken', 'App-Token');
        $this->criteoAppToken->readableByCurrentUser = true;
        $this->criteoAppToken->uiControlType = static::CONTROL_TEXT;
        $this->criteoAppToken->description = 'App-Token provided by Criteo, e.g. "1803158815927717143"';

        $this->addSetting($this->criteoAppToken);
    }

    private function createCriteoUsernameSetting()
    {
        $this->criteoUsername = new SystemSetting('criteoUsername', 'Username');
        $this->criteoUsername->readableByCurrentUser = true;
        $this->criteoUsername->uiControlType = static::CONTROL_TEXT;
        $this->criteoUsername->description = 'Username provided by Criteo, e.g. "acmecom_api"';

        $this->addSetting($this->criteoUsername);
    }

    private function createCriteoPasswordSetting()
    {
        $this->criteoPassword = new SystemSetting('criteoPassword', 'API-Password');
        $this->criteoPassword->readableByCurrentUser = true;
        $this->criteoPassword->uiControlType = static::CONTROL_PASSWORD;
        $this->criteoPassword->description = 'Password provided by Criteo, e.g. "C66u37Gi"';

        $this->addSetting($this->criteoPassword);
    }
}
