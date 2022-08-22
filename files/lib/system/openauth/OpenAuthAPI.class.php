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

namespace wcf\system\openAuth;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use wcf\system\exception\SystemException;
use wcf\system\io\HttpFactory;
use wcf\system\option\user\UserOptionHandler;
use wcf\system\request\LinkHandler;
use wcf\system\SingletonFactory;
use wcf\util\JSON;

use function wcf\functions\exception\logThrowable;

class OpenAuthAPI extends SingletonFactory
{
    protected const OPENAUTH_API_URL = 'https://openauth.dev/%s?clientID=%s';

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->client = HttpFactory::makeClient(['timeout' => 10]);
    }

    /**
     * Generates a random string, using the best available method.
     *
     * @throws SystemException
     * @throws Exception
     *
     */
    public static function generateRandom(?int $length = 32): ?string
    {
        if (!null === $length || (int)$length <= 8) {
            $length = 32;
        }

        $keySpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        $max = \mb_strlen($keySpace, '8bit') - 1;

        for ($i = 0; $i < $length; $i++) {
            $str .= $keySpace[\random_int(0, $max)];
        }

        return $str;
    }

    /**
     * Get redirect uri.
     *
     * @throws SystemException
     */
    public function getRedirectURI(): string
    {
        return LinkHandler::getInstance()->getLink('OpenAuth', [
            'forceFrontend' => true,
            'application' => 'wcf',
        ]);
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getOauthConfiguration(string $configurationName)
    {
        if (null === $this->configuration) {
            $this->configuration = $this->call(
                \sprintf(self::OPENAUTH_API_URL, 'open-id-configuration', OPENAUTH_CLIENT_ID)
            );
        }

        if (!empty($this->configuration[$configurationName])) {
            return $this->configuration[$configurationName];
        }

        return null;
    }

    /**
     * Returns a list of available scopes.
     *
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getScopes(): ?array
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

        $scopesToRemove = \array_intersect(\array_keys($profileScopes), \array_keys($optionHandler->options));

        foreach ($scopesToRemove as $scopeToRemove) {
            $scope = $profileScopes[$scopeToRemove];
            $key = \array_search($scope, $scopes, true);

            if ($key !== false) {
                unset($scopes[$key]);
            }
        }

        return \array_values($scopes);
    }

    /**
     * Returns the authorization Uri.
     *
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAuthorizationURI(string $state, array $scopes = []): ?string
    {
        $authorizationEndpoint = $this->getOauthConfiguration('authorization_endpoint');

        if (null === $authorizationEndpoint) {
            return null;
        }

        $requestParameters = [
            'response_type' => 'code',
            'client_id' => OPENAUTH_CLIENT_ID,
            'state' => $state,
            'scope' => \implode(' ', $scopes),
            'redirect_uri' => $this->getRedirectURI(),
        ];

        return $authorizationEndpoint . '?' . \http_build_query($requestParameters, null, '&');
    }

    /**
     * Checks if the given oAuth code is valid.
     *
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkOAuthCode($code): ?array
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
            'redirect_uri' => $this->getRedirectURI(),
        ];

        return $this->call($tokenEndpoint, $params);
    }

    /**
     * Returns user information for the given access token from the oAuth server.
     *
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUserInfo(string $accessToken): ?array
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
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function call(string $url, array $data = [], ?string $accessToken = null): ?array
    {
        try {
            $headers = [
                'accept-encoding' => 'gzip',
                'accept' => 'application/json',
            ];

            if (null !== $accessToken) {
                $headers['authorization'] = 'Bearer ' . $accessToken;
            }

            $response = $this->client->send(new Request('POST', $url, $headers, $data));

            if ($response->getStatusCode() === 200) {
                return JSON::decode((string)$response->getBody());
            }
        } catch (GuzzleException $e) {
            if (ENABLE_DEBUG_MODE) {
                throw $e;
            }

            logThrowable($e);
        }

        return [];
    }
}
