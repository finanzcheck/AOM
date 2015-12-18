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

    /** @var SystemSetting */
    public $facebookAdsIsActive;

    /** @var SystemSetting */
    public $facebookAdsTimezone;

    /** @var SystemSetting */
    public $facebookAdsClientId;

    /** @var SystemSetting */
    public $facebookAdsClientSecret;

    /** @var SystemSetting */
    public $facebookAdsUserAccountId;

    /** @var SystemSetting */
    public $facebookAdsAccountId;

    /** @var SystemSetting */
    public $facebookAdsAccessToken;
    
    /** @var SystemSetting */
    public $bingIsActive;

    /** @var SystemSetting */
    public $bingClientId;

    /** @var SystemSetting */
    public $bingClientSecret;

    /** @var SystemSetting */
    public $bingGetToken;

    /** @var SystemSetting */
    public $bingAccessToken;

    /** @var SystemSetting */
    public $bingRefreshToken;

    /** @var SystemSetting */
    public $bingDeveloperToken;

    /** @var SystemSetting */
    public $bingCode;

    /** @var SystemSetting */
    public $bingAccountId;
    
    /** @var SystemSetting */
    public $proxyIsActive;

    /** @var SystemSetting */
    public $proxyHost;

    /** @var SystemSetting */
    public $proxyPort;
    
    protected function init()
    {
//        $this->setIntroduction('...');

        // Add generic fields
        $this->createAdIdSetting();

        // Add fields for Proxy
        $this->createProxyIsActiveSetting();
        if ($this->proxyIsActive->getValue()) {
            $this->createProxyHostSetting();
            $this->createProxyPortSetting();
        }

        // Add fields for AdWords
        $this->createAdWordsIsActiveSetting();
        if ($this->adWordsIsActive->getValue()) {
            $this->createAdWordsDeveloperTokenSetting();
            $this->createAdWordsUserAgentSetting();
            $this->createAdWordsClientCustomerIdSetting();
            $this->createAdWordsClientIdSetting();
            $this->createAdWordsClientSecretSetting();
            $this->createAdWordsRefreshTokenSetting();
        }

        // Add fields for Criteo
        $this->createCriteoIsActiveSetting();
        if ($this->criteoIsActive->getValue()) {
            $this->createCriteoAppTokenSetting();
            $this->createCriteoUsernameSetting();
            $this->createCriteoPasswordSetting();
        }

        // Add fields for Facebook Ads
        $this->createFacebookAdsIsActiveSetting();
        if ($this->facebookAdsIsActive->getValue()) {
            $this->createFacebookAdsTimezone();
            $this->createFacebookAdsClientIdSetting();
            $this->createFacebookAdsClientSecretSetting();
            $this->createFacebookAdsUserAccountIdSetting();
            $this->createFacebookAdsAccountIdSetting();
            $this->createFacebookAdsAccessTokenSetting();
        }
        
        // Add fields for Bing
        $this->createBingIsActiveSetting();
        if ($this->bingIsActive->getValue()) {
            $this->createBingClientIdSetting();
            $this->createBingClientSecretSetting();
            $this->createBingCodeSetting();
            $this->createBingAccessTokenSetting();
            $this->createBingRefreshTokenSetting();
            $this->createBingDeveloperTokenSetting();
            $this->createBingAccountIdSetting();
            $this->createBingGetTokenSetting();
            if($this->bingGetToken->getValue()) {
                $this->updateBingAuthToken();
            }
        }
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

    private function createFacebookAdsIsActiveSetting()
    {
        $this->facebookAdsIsActive = new SystemSetting('facebookAdsIsActive', 'Enable Facebook Ads');
        $this->facebookAdsIsActive->readableByCurrentUser = true;
        $this->facebookAdsIsActive->type  = static::TYPE_BOOL;
        $this->facebookAdsIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->facebookAdsIsActive->description   = 'Enable Facebook Ads';
        $this->facebookAdsIsActive->defaultValue  = false;

        $this->addSetting($this->facebookAdsIsActive);
    }

    private function createFacebookAdsTimezone()
    {
        $this->facebookAdsTimezone = new SystemSetting('facebookAdsTimezone', 'Timezone');
        $this->facebookAdsTimezone->readableByCurrentUser = true;
        $this->facebookAdsTimezone->uiControlType = static::CONTROL_TEXT;
        $this->facebookAdsTimezone->description = 'Timezone of Facebook Ads Account, e.g. "Europe/Berlin"';

        $this->addSetting($this->facebookAdsTimezone);
    }

    private function createFacebookAdsClientIdSetting()
    {
        $this->facebookAdsClientId = new SystemSetting('facebookAdsClientId', 'Client ID');
        $this->facebookAdsClientId->readableByCurrentUser = true;
        $this->facebookAdsClientId->uiControlType = static::CONTROL_TEXT;
        $this->facebookAdsClientId->description = 'Client ID (Facebook App ID), e.g. "384056678729432"';

        $this->addSetting($this->facebookAdsClientId);
    }

    private function createFacebookAdsClientSecretSetting()
    {
        $this->facebookAdsClientSecret = new SystemSetting('facebookAdsClientSecret', 'Client Secret');
        $this->facebookAdsClientSecret->readableByCurrentUser = true;
        $this->facebookAdsClientSecret->uiControlType = static::CONTROL_TEXT;
        $this->facebookAdsClientSecret->description = 'Client Secret (Facebook App Secret), e.g. "666e9b2d7a28ae77d64202b5ac9bedf9"';

        $this->addSetting($this->facebookAdsClientSecret);
    }

    private function createFacebookAdsUserAccountIdSetting()
    {
        $this->facebookAdsUserAccountId = new SystemSetting('facebookAdsUserAccountId', 'User Account ID');
        $this->facebookAdsUserAccountId->readableByCurrentUser = true;
        $this->facebookAdsUserAccountId->uiControlType = static::CONTROL_TEXT;
        $this->facebookAdsUserAccountId->description = 'User Account ID, e.g. "1384758171773244"';

        $this->addSetting($this->facebookAdsUserAccountId);
    }

    private function createFacebookAdsAccountIdSetting()
    {
        $this->facebookAdsAccountId = new SystemSetting('facebookAdsAccountId', 'Account ID');
        $this->facebookAdsAccountId->readableByCurrentUser = true;
        $this->facebookAdsAccountId->uiControlType = static::CONTROL_TEXT;
        $this->facebookAdsAccountId->description = 'Account ID, e.g. "918514149094443"';

        $this->addSetting($this->facebookAdsAccountId);
    }

    private function createFacebookAdsAccessTokenSetting()
    {
        $this->facebookAdsAccessToken = new SystemSetting('facebookAdsAccessToken', 'Access Token');
        $this->facebookAdsAccessToken->readableByCurrentUser = true;
        $this->facebookAdsAccessToken->uiControlType = static::CONTROL_TEXT;
        $this->facebookAdsAccessToken->description = 'Access Token provided by Facebook, e.g. "8bLaboLEVEPZA5rhqXM1D6WjHzGyFgS ..."';

        $this->addSetting($this->facebookAdsAccessToken);
    }

    private function createBingIsActiveSetting()
    {
        $this->bingIsActive = new SystemSetting('bingIsActiveSetting', 'Enable Bing');
        $this->bingIsActive->readableByCurrentUser = true;
        $this->bingIsActive->type  = static::TYPE_BOOL;
        $this->bingIsActive->uiControlType = static::CONTROL_CHECKBOX;
        $this->bingIsActive->description   = 'Enable Bing';
        $this->bingIsActive->defaultValue  = false;

        $this->addSetting($this->bingIsActive);
    }

    private function createBingClientIdSetting()
    {
        $this->bingClientId = new SystemSetting('bingClientId', 'ClientId');
        $this->bingClientId->readableByCurrentUser = true;
        $this->bingClientId->uiControlType = static::CONTROL_TEXT;
        $this->bingClientId->description = 'Get ClientId from https://account.live.com/developers/applications, e.g. "00000004CDEEF2F4". Add new Application and check the box for "Mobile or desktop client app":';

        $this->addSetting($this->bingClientId);
    }
    
    private function createBingClientSecretSetting()
    {
        $this->bingClientSecret = new SystemSetting('bingClientSecret', 'ClientSecret');
        $this->bingClientSecret->readableByCurrentUser = true;
        $this->bingClientSecret->uiControlType = static::CONTROL_TEXT;
        $this->bingClientSecret->description = 'ClientSecret, e.g. "psadfjkHKJHjsad3jkl"';

        $this->addSetting($this->bingClientSecret);
    }

    private function createBingCodeSetting()
    {
        $this->bingCode = new SystemSetting('bingCode', 'Code');
        $this->bingCode->readableByCurrentUser = true;
        $this->bingCode->uiControlType = static::CONTROL_TEXT;
        $this->bingCode->description = sprintf('Go to: https://login.live.com/oauth20_authorize.srf?client_id=%s&scope=bingads.manage&response_type=code&redirect_uri=https://login.live.com/oauth20_desktop.srf and enter the code value from URI after your login',
            $this->bingClientId->getValue()
        );
        $this->addSetting($this->bingCode);
    }

    private function createBingGetTokenSetting()
    {
        $this->bingGetToken = new SystemSetting('bingGetTokenSetting', 'Get new Tokens for Bing.');
        $this->bingGetToken->readableByCurrentUser = true;
        $this->bingGetToken->type  = static::TYPE_BOOL;
        $this->bingGetToken->uiControlType = static::CONTROL_CHECKBOX;
        $this->bingGetToken->description   = 'Get new Tokens for Bing. Code will be cleared';
        $this->bingGetToken->defaultValue  = false;

        $this->addSetting($this->bingGetToken);
    }
    
    
    private function createBingAccessTokenSetting()
    {
        $this->bingAccessToken = new SystemSetting('bingAccessToken', 'AccessToken');
        $this->bingAccessToken->readableByCurrentUser = true;
        $this->bingAccessToken->uiControlType = static::CONTROL_TEXT;
        $this->bingAccessToken->description = 'AccessToken, e.g. "EwBwAnhlBAAUxT83/QvqiAZEx5SuwyhZqHzk..."';

        $this->addSetting($this->bingAccessToken);
    }
    
    private function createBingRefreshTokenSetting()
    {
        $this->bingRefreshToken = new SystemSetting('bingRefreshToken', 'RefreshToken');
        $this->bingRefreshToken->readableByCurrentUser = true;
        $this->bingRefreshToken->uiControlType = static::CONTROL_TEXT;
        $this->bingRefreshToken->description = 'RefreshToken, e.g. "EwBwAnhlBAAUxT83/QvqiAZEx5SuwyhZqHzk..."';

        $this->addSetting($this->bingRefreshToken);
    }

    private function createBingDeveloperTokenSetting()
    {
        $this->bingDeveloperToken = new SystemSetting('bingDeveloperToken', 'Developer Token');
        $this->bingDeveloperToken->readableByCurrentUser = true;
        $this->bingDeveloperToken->uiControlType = static::CONTROL_TEXT;
        $this->bingDeveloperToken->description = 'Developer Token, e.g. "0465A564ADD"';

        $this->addSetting($this->bingDeveloperToken);
    }

    private function createBingAccountIdSetting()
    {
        $this->bingAccountId = new SystemSetting('bingAccountId', 'Account ID');
        $this->bingAccountId->readableByCurrentUser = true;
        $this->bingAccountId->uiControlType = static::CONTROL_TEXT;
        $this->bingAccountId->description = 'Account ID provided by Bing, e.g. "6457892"';

        $this->addSetting($this->bingAccountId);
    }

    private function updateBingAuthToken()
    {
        $context = null;
        if ($this->proxyIsActive->getValue()) {
            $context = stream_context_create(
                [
                    'http' => [
                        'proxy' => "tcp://" . $this->proxyHost->getValue() . ":" . $this->proxyPort->getValue(),
                        'request_fulluri' => true,
                    ]
                ]
            );
        }

        $url = sprintf("https://login.live.com/oauth20_token.srf?client_id=%s&client_secret=%s&code=%s&grant_type=authorization_code&redirect_uri=https://login.live.com/oauth20_desktop.srf",
            $this->bingClientId->getValue(),
            $this->bingClientSecret->getValue(),
            $this->bingCode->getValue()
        );

        $response = file_get_contents($url, null, $context);
        $response = json_decode($response);
        $this->bingRefreshToken->setValue($response->refresh_token);
        $this->bingAccessToken->setValue($response->access_token);
        $this->bingCode->setValue('');
        $this->bingGetToken->setValue(false);
        $this->bingGetToken->getStorage()->save();

    }
}
