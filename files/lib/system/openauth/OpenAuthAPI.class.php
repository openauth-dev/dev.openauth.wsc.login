<?php

/*
 * Copyright by The OpenAuth.dev Team.
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

use Exception;
use wcf\system\exception\SystemException;
use wcf\system\option\user\UserOptionHandler;
use wcf\system\request\LinkHandler;
use wcf\system\SingletonFactory;
use wcf\util\HTTPRequest;
use wcf\util\JSON;
use wcf\util\StringUtil;

use function array_intersect;
use function array_keys;
use function array_search;
use function array_values;
use function http_build_query;
use function implode;
use function is_callable;
use function mb_strlen;
use function md5;
use function mt_rand;
use function openssl_random_pseudo_bytes;
use function random_bytes;
use function random_int;
use function sprintf;
use function substr;
use function uniqid;

class OpenAuthAPI extends SingletonFactory
{
    /**
     * @var string
     */
    const OPENAUTH_API_URL = 'https://openauth.dev/%s?clientID=%s';

    /**
     * @var array
     */
    protected $configuration;

    /**
     * Generates a random string, using the best available method.
     *
     * @throws SystemException
     * @throws Exception
     */
    public static function generateRandom(int $length = 32)
    {
        if (class_exists('\ParagonIE\ConstantTime\Hex')) {
            $hexFn = '\ParagonIE\ConstantTime\Hex::encode';
        } else {
            $hexFn = '\bin2hex';
        }

        if (!null === $length || $length <= 8) {
            $length = 32;
        }

        if (is_callable('random_bytes')) {
            return substr($hexFn(random_bytes($length)), -$length, $length);
        }

        if (is_callable('openssl_random_pseudo_bytes')) {
            /** @noinspection CryptographicallySecureRandomnessInspection */
            $random = openssl_random_pseudo_bytes($length, $isSourceStrong);

            if (false === $isSourceStrong || false === $random) {
                throw new SystemException('IV generation failed');
            }

            return substr($hexFn($random), -$length, $length);
        }

        if (is_callable('random_int')) {
            $keySpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $str = '';
            $max = mb_strlen($keySpace, '8bit') - 1;

            for ($i = 0; $i < $length; ++$i) {
                $str .= $keySpace[random_int(0, $max)];
            }

            return $str;
        }

        if (is_callable('mt_rand')) {
            return substr(md5(uniqid(mt_rand(), true)), -$length, $length);
        }

        if (is_callable('mcrypt_create_iv')) {
            /** @noinspection CryptographicallySecureRandomnessInspection */
            $randomM = mcrypt_create_iv($length, MCRYPT_DEV_RANDOM);

            if (false === $randomM) {
                throw new SystemException('IV generation failed');
            }

            return substr($hexFn($randomM), -$length, $length);
        }

        return StringUtil::getRandomID();
    }

    /**
     * Get redirect uri.
     *
     * @throws SystemException
     */
    public function getRedirectURI(): string
    {
        return LinkHandler::getInstance()->getLink(
            'OpenAuth',
            [
                'forceFrontend' => true,
                'application' => 'wcf'
            ]
        );
    }

    /**
     * Returns a scope to user option mapping.
     */
    public function getUserOptionNames(): array
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
     * @throws Exception
     */
    public function getOauthConfiguration(string $configurationName): string
    {
        if (null === $this->configuration) {
            $this->configuration = $this->call(
                sprintf(
                    self::OPENAUTH_API_URL,
                    '.well-known/openid-configuration',
                    OPENAUTH_CLIENT_ID
                )
            );
        }

        if (!empty($this->configuration[$configurationName])) {
            return (string)$this->configuration[$configurationName];
        }

        return '';
    }

    /**
     * Returns a list of available scopes.
     *
     * @throws Exception
     */
    public function getScopes(): array
    {
        $scopes = $this->getOauthConfiguration('scopes_supported');

        if (null === $scopes) {
            return [];
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
     * @throws Exception
     */
    public function getAuthorizationURI(string $state, array $scopes = []): string
    {
        $authorizationEndpoint = $this->getOauthConfiguration('authorization_endpoint');

        if (null === $authorizationEndpoint) {
            return '';
        }

        $requestParameters = [
            'response_type' => 'code',
            'client_id' => OPENAUTH_CLIENT_ID,
            'state' => $state,
            'scope' => implode(' ', $scopes),
            'redirect_uri' => $this->getRedirectURI()
        ];

        return $authorizationEndpoint . '?' . http_build_query($requestParameters, null);
    }

    /**
     * Checks if the given oAuth code is valid.
     *
     * @throws Exception
     */
    public function checkOAuthCode(string $code): array
    {
        $tokenEndpoint = $this->getOauthConfiguration('token_endpoint');

        if (null === $tokenEndpoint) {
            return [];
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
     * Returns user information for the given access token from the oAuth server.
     *
     * @throws Exception
     */
    public function getUserInfo(string $accessToken): array
    {
        $userinfoEndpoint = $this->getOauthConfiguration('userinfo_endpoint');

        if (null === $userinfoEndpoint) {
            return [];
        }

        return $this->call($userinfoEndpoint, [], $accessToken);
    }

    /**
     * Executes http requests.
     *
     * @throws Exception
     */
    protected function call(string $url, array $data = [], string $accessToken = null): array
    {
        try {
            $request = new HTTPRequest($url, [], $data);

            if (null !== $accessToken) {
                $request->addHeader('Authorization', 'Bearer ' . $accessToken);
            }

            $request->execute();

            return JSON::decode($request->getReply()['body']);
        } catch (Exception $e) {
            if (ENABLE_DEBUG_MODE) {
                throw $e;
            }

            return [];
        }
    }
}
