<?php

namespace Piwik\Plugins\AOM;

use Piwik\Settings\SystemSetting;
use Piwik\Settings\UserSetting;

class Settings extends \Piwik\Plugin\Settings
{
    /** @var SystemSetting */
    public $adId;

    /** @var SystemSetting */
    public $criteoAppToken;

    /** @var SystemSetting */
    public $criteoIsActive;

    /** @var SystemSetting */
    public $criteoUsername;

    /** @var SystemSetting */
    public $criteoPassword;

    protected function init()
    {
//        $this->setIntroduction('...');

        // Add generic fields
        $this->createAdIdSetting();

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
