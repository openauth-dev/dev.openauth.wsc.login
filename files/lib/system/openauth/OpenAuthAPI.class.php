<?php
/*
 * Copyright by The OpenAuth.dev Team.
 * This file is part of dev.openauth.wsc.login.
 *
 * License: GNU Lesser General Public License v2.1
 *
 * THIS LIBRARY IS FREE SOFTWARE; YOU CAN REDISTRIBUTE IT AND/OR
 * MODIFY IT UNDER THE TERMS OF THE GNU LESSER GENERAL PUBLIC
 * LICENSE AS PUBLISHED BY THE FREE SOFTWARE FOUNDATION; EITHER
 * VERSION 2.1 OF THE LICENSE, OR (AT YOUR OPTION) ANY LATER VERSION.
 * 
 * THIS LIBRARY IS DISTRIBUTED IN THE HOPE THAT IT WILL BE USEFUL,
 * BUT WITHOUT ANY WARRANTY; WITHOUT EVEN THE IMPLIED WARRANTY OF
 * MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE.  SEE THE GNU
 * LESSER GENERAL PUBLIC LICENSE FOR MORE DETAILS.
 * 
 * YOU SHOULD HAVE RECEIVED A COPY OF THE GNU LESSER GENERAL PUBLIC
 * LICENSE ALONG WITH THIS LIBRARY; IF NOT, WRITE TO THE FREE SOFTWARE
 * FOUNDATION, INC., 51 FRANKLIN STREET, FIFTH FLOOR, BOSTON, MA  02110-1301  USA
 *
 * The above copyright notice and this disclaimer notice shall be included in all
 * copies or substantial portions of the Software.
 */

namespace wcf\system\openauth;

use wcf\system\exception\SystemException;
use wcf\system\option\user\UserOptionHandler;
use wcf\system\request\LinkHandler;
use wcf\system\SingletonFactory;
use wcf\util\HTTPRequest;
use wcf\util\JSON;
use wcf\util\StringUtil;

class OpenAuthAPI extends SingletonFactory
{
    const OPENAUTH_API_URL = 'https://openauth.dev/%s?clientID=%s';

    /**
     * @var array
     */
    protected $configuration;

    /**
     * Generates a random string, using the best available method.
     *
     * @param int $length
     *
     * @return string|null
     *
     * @throws SystemException
     * @throws \Exception
     *
     */
    public static function generateRandom($length = 32)
    {
        if (!null === $length || (int)$length <= 8) {
            $length = 32;
        }

        if (is_callable('random_bytes')) {
            /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
            return substr(bin2hex(random_bytes($length)), -$length, $length);
        }

        if (is_callable('openssl_random_pseudo_bytes')) {
            $random = openssl_random_pseudo_bytes($length, $isSourceStrong);

            if (false === $isSourceStrong || false === $random) {
                throw new SystemException('IV generation failed');
            }

            return substr(bin2hex($random), -$length, $length);
        }

        if (is_callable('random_int')) {
            $keySpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $str = '';
            $max = mb_strlen($keySpace, '8bit') - 1;

            for ($i = 0; $i < $length; ++$i) {
                /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
                $str .= $keySpace[random_int(0, $max)];
            }

            return $str;
        }

        if (is_callable('mt_rand')) {
            return substr(md5(uniqid(mt_rand(), true)), -$length, $length);
        }

        if (is_callable('mcrypt_create_iv')) {
            $randomM = mcrypt_create_iv($length, MCRYPT_DEV_RANDOM);

            if (false === $randomM) {
                throw new SystemException('IV generation failed');
            }

            return substr(bin2hex($randomM), -$length, $length);
        }

        return StringUtil::getRandomID();
    }

    /**
     * Get redirect uri.
     *
     * @return string
     *
     * @throws SystemException
     */
    public function getRedirectURI()
    {
        return LinkHandler::getInstance()->getLink('OpenAuth', [
            'forceFrontend' => true,
            'application' => 'wcf'
        ]);
    }

    /**
     * Returns a scope to user option mapping.
     *
     * @return array
     */
    public function getUserOptionNames()
    {
        return [
            'homepage' => 'website',
            'gender' => 'gender',
            'birthday' => 'birthdate',
            'aboutMe' => 'aboutMe',
            'location' => 'location',
            'occupation' => 'occupation',
            'hobbies' => 'hobbies',
            'icq' => 'icq',
            'skype' => 'skype',
            'facebook' => 'facebook',
            'twitter' => 'twitter',
        ];
    }

    /**
     * Loads the remote oAuth configuration.
     *
     * @param $configurationName
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function getOauthConfiguration($configurationName)
    {
        if (null === $this->configuration) {
            $this->configuration = $this->call(sprintf(self::OPENAUTH_API_URL, 'open-id-configuration', OPENAUTH_CLIENT_ID));
        }

        if (!empty($this->configuration[$configurationName])) {
            return $this->configuration[$configurationName];
        }

        return null;
    }

    /**
     * Returns a list of available scopes.
     *
     * @return array|null
     *
     * @throws \Exception
     */
    public function getScopes()
    {
        $scopes = $this->getOauthConfiguration('scopes_supported');

        if (null === $scopes) {
            return null;
        }

        $scopes[] = 'openid';
        $profileScopes = $this->getUserOptionNames();

        $optionHandler = new UserOptionHandler(true, '', '');
        $optionHandler->setInRegistration();
        $optionHandler->init();

        $scopesToRemove = array_intersect(array_keys($profileScopes), array_keys($optionHandler->options));

        foreach ($scopesToRemove as $scopeToRemove) {
            $scope = $profileScopes[$scopeToRemove];
            $key = array_search($scope, $scopes, false);

            if ($key !== false) {
                unset($scopes[$key]);
            }
        }

        return array_values($scopes);
    }

    /**
     * Returns the authorization Uri.
     *
     * @param $state
     * @param array $scopes
     *
     * @return string|null
     *
     * @throws \Exception
     */
    public function getAuthorizationURI($state, $scopes = [])
    {
        $authorizationEndpoint = $this->getOauthConfiguration('authorization_endpoint');

        if (null === $authorizationEndpoint) {
            return null;
        }

        $requestParameters = [
            'response_type' => 'code',
            'client_id' => OPENAUTH_CLIENT_ID,
            'state' => $state,
            'scope' => implode(' ', $scopes),
            'redirect_uri' => $this->getRedirectURI()
        ];

        return $authorizationEndpoint . '?' . http_build_query($requestParameters, null, '&');
    }

    /**
     * Checks if the given oAuth code is valid.
     *
     * @param $code
     *
     * @return array|null
     *
     * @throws \Exception
     */
    public function checkOAuthCode($code)
    {
        $tokenEndpoint = $this->getOauthConfiguration('token_endpoint');

        if (null === $tokenEndpoint) {
            return null;
        }

        $params = [
            'client_id' => OPENAUTH_CLIENT_ID,
            'client_secret' => OPENAUTH_CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getRedirectURI()
        ];

        return $this->call($tokenEndpoint, $params);
    }

    /**
     * Returns user informations for the given access token from the oAuth server.
     *
     * @param $accessToken
     *
     * @return array|null
     *
     * @throws \Exception
     */
    public function getUserInfo($accessToken)
    {
        $userinfoEndpoint = $this->getOauthConfiguration('userinfo_endpoint');

        if (null === $userinfoEndpoint) {
            return null;
        }

        return $this->call($userinfoEndpoint, [], $accessToken);
    }

    /**
     * Executes http requests.
     *
     * @param $url
     * @param array $data
     * @param null $accessToken
     *
     * @return array|null
     * @throws \Exception
     */
    protected function call($url, $data = [], $accessToken = null)
    {
        try {
            $request = new HTTPRequest($url, [], $data);

            if (null !== $accessToken) {
                $request->addHeader('Authorization', 'Bearer ' . $accessToken);
            }
            
            $request->execute();
            
            return JSON::decode($request->getReply()['body']);
        } catch (\Exception $e) {
            if (ENABLE_DEBUG_MODE) {
                throw $e;
            }

            return [];
        }
    }
}
