<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\Bing;

use Piwik\Common;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugins\AOM\AOM;
use Piwik\Plugins\AOM\SystemSettings;

class Controller extends \Piwik\Plugins\AOM\Platforms\Controller
{
    /**
     * @param int $websiteId
     * @param string $clientId
     * @param string $clientSecret
     * @param string $accountId
     * @param string $developerToken
     * @return bool
     */
    public function addAccount($websiteId, $clientId, $clientSecret, $accountId, $developerToken)
    {
        Piwik::checkUserHasAdminAccess($idSites = [$websiteId]);

        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        $configuration[AOM::PLATFORM_BING]['accounts'][uniqid('', true)] = [
            'websiteId' => $websiteId,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'accountId' => $accountId,
            'developerToken' => $developerToken,
            'accessToken' => null,
            'refreshToken' => null,
            'active' => true,
        ];

        $settings->setConfiguration($configuration);

        return true;
    }

    /**
     * Redirects to Bing to get a "code" param via Bing redirect response.
     * This "code" is used in processAccessTokenCode() to obtain both access and refresh token.
     *
     * @throws \Exception
     */
    public function getAccessToken()
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        // Does the account exist?
        $id = Common::getRequestVar('id', false);
        if (!array_key_exists($id, $configuration[AOM::PLATFORM_BING]['accounts'])) {
            throw new \Exception('Bing account "' . $id . '" does not exist.');
        }

        Piwik::checkUserHasAdminAccess(
            $idSites = [$configuration[AOM::PLATFORM_BING]['accounts'][$id]['websiteId']]
        );

        header(
            'Location: https://login.live.com/oauth20_authorize.srf?client_id='
            . $configuration[AOM::PLATFORM_BING]['accounts'][$id]['clientId'] . '&scope=bingads.manage'
            . '&response_type=code&redirect_uri=' . urlencode(rtrim(Option::get('piwikUrl'), '/')
            . '?module=AOM&action=platformAction&platform=Bing&method=processAccessTokenCode&id=' . $id
        ));
        exit;
    }

    /**
     * Bing redirects back to us with a "code" param which is used to get both access and refresh token.
     *
     * @throws \Exception
     */
    public function processAccessTokenCode()
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        // Does the account exist?
        $id = Common::getRequestVar('id', false);
        if (!array_key_exists($id, $configuration[AOM::PLATFORM_BING]['accounts'])) {
            throw new \Exception('Bing account "' . $id . '" does not exist.');
        }

        Piwik::checkUserHasAdminAccess(
            $idSites = [$configuration[AOM::PLATFORM_BING]['accounts'][$id]['websiteId']]
        );

        // Is there a "code"-param in the URI?
        $code = Common::getRequestVar('code', false);
        if (!$code) {
            throw new \Exception('No code in URI.');
        }

        // The value for the 'redirect_uri' must exactly match the redirect URI used to obtain the authorization code.
        
        $postUrl =  'https://login.live.com/oauth20_token.srf';   
        		
		$postFields = 'client_id='
            . $configuration[AOM::PLATFORM_BING]['accounts'][$id]['clientId'] . '&client_secret='
            . $configuration[AOM::PLATFORM_BING]['accounts'][$id]['clientSecret'] . '&code=' . $code
            . '&grant_type=authorization_code&redirect_uri=' . urlencode(rtrim(Option::get('piwikUrl'), '/')
            . '?module=AOM&action=platformAction&platform=Bing&method=processAccessTokenCode&id=' . $id);
            			
        $response = Bing::urlPostContents($postUrl, $postFields);
        $data = json_decode($response, true);
        $configuration[AOM::PLATFORM_BING]['accounts'][$id]['accessToken'] = $data['access_token'];
        $configuration[AOM::PLATFORM_BING]['accounts'][$id]['refreshToken'] = $data['refresh_token'];
        $settings->setConfiguration($configuration);

        header('Location: ?module=AOM&action=settings');
        exit;
    }
}