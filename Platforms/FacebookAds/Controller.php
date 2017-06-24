<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms\FacebookAds;

use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
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
     * @return bool
     */
    public function addAccount($websiteId, $clientId, $clientSecret, $accountId)
    {
        Piwik::checkUserHasAdminAccess($idSites = [$websiteId]);

        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        $configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'][uniqid('', true)] = [
            'websiteId' => $websiteId,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'accountId' => $accountId,
            'accessToken' => null,
            'active' => true,
        ];

        $settings->setConfiguration($configuration);

        return true;
    }

    /**
     * Redirects to Facebook to get a "code" param via Facebook redirect response.
     * This "code" is used in processAccessTokenCode() to obtain an access token.
     *
     * @throws \Exception
     */
    public function getAccessToken()
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        // Does the account exist?
        $id = Common::getRequestVar('id', false);
        if (!array_key_exists($id, $configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'])) {
            throw new \Exception('Facebook Ads account "' . $id . '" does not exist.');
        }

        Piwik::checkUserHasAdminAccess(
            $idSites = [$configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'][$id]['websiteId']]
        );

        $fb = $this->getFacebookApiClient($configuration, $id);
        $helper = $fb->getRedirectLoginHelper();
        $loginUrl = $helper->getLoginUrl(
            Option::get('piwikUrl')
                . '?module=AOM&action=platformAction&platform=FacebookAds&method=processAccessTokenCode&id=' . $id,
            ['ads_read']
        );

        header('Location: ' . $loginUrl);
        exit;
    }

    /**
     * Facebook redirects back to us with a "code" param which is used to get the access token.
     *
     * @throws \Exception
     */
    public function processAccessTokenCode()
    {
        $settings = new SystemSettings();
        $configuration = $settings->getConfiguration();

        // Does the account exist?
        $id = Common::getRequestVar('id', false);
        if (!array_key_exists($id, $configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'])) {
            throw new \Exception('Facebook Ads account "' . $id . '" does not exist.');
        }

        Piwik::checkUserHasAdminAccess(
            $idSites = [$configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'][$id]['websiteId']]
        );

        $fb = $this->getFacebookApiClient($configuration, $id);
        $helper = $fb->getRedirectLoginHelper();

        try {
            $accessToken = $helper->getAccessToken();
        } catch (FacebookResponseException $e) {
            throw new \Exception('Graph returned an error: ' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            throw new \Exception('Facebook SDK returned an error: ' . $e->getMessage());
        }

        if (isset($accessToken)) {

            $configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'][$id]['accessToken'] = $accessToken->getValue();
            $settings->setConfiguration($configuration);

            header('Location: ?module=AOM&action=settings');
            exit;

        } elseif ($helper->getError()) {
            throw new \Exception('The user denied the request.');
        }
    }

    /**
     * @param array $configuration
     * @param string $id
     * @return Facebook
     */
    private function getFacebookApiClient($configuration, $id)
    {
        $fb = new Facebook([
            'app_id' => $configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'][$id]['clientId'],
            'app_secret' => $configuration[AOM::PLATFORM_FACEBOOK_ADS]['accounts'][$id]['clientSecret'],
            'default_graph_version' => 'v2.5',
        ]);

        return $fb;
    }
}
