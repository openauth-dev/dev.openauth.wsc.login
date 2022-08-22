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

namespace wcf\action;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;
use wcf\data\user\User;
use wcf\data\user\UserEditor;
use wcf\form\RegisterForm;
use wcf\system\exception\NamedUserException;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\openauth\OpenAuthAPI;
use wcf\system\request\LinkHandler;
use wcf\system\user\authentication\oauth\exception\StateValidationException;
use wcf\system\WCF;
use wcf\util\HeaderUtil;

use function wcf\functions\exception\logThrowable;

class OpenAuthAction extends AbstractAction
{
    /**
     * @inheritDoc
     */
    public $neededModules = ['OPENAUTH_CLIENT_ID', 'OPENAUTH_CLIENT_SECRET'];

    /**
     * @var string
     */
    private const STATE = self::class . "\0state_parameter";

    /**
     * @inheritDoc
     *
     * @throws \wcf\system\exception\PermissionDeniedException
     */
    public function readParameters(): void
    {
        parent::readParameters();

        if (WCF::getSession()->spiderID) {
            throw new PermissionDeniedException();
        }
    }

    /**
     * @inheritDoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \wcf\system\exception\NamedUserException
     * @throws \wcf\system\exception\PermissionDeniedException
     */
    public function execute(): void
    {
        parent::execute();

        try {
            if (isset($_GET['code'])) {
                $tokenInfo = OpenAuthAPI::getInstance()->checkOAuthCode($_REQUEST['code']);

                if ($tokenInfo === null) {
                    throw new NamedUserException(
                        WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.openauth.connect.error.configuration')
                    );
                }

                if (empty($tokenInfo['access_token'])) {
                    throw new NamedUserException(
                        WCF::getLanguage()->get('wcf.user.3rdparty.openauth.connect.error.invalid')
                    );
                }

                $this->processUser($this->getUser($tokenInfo['access_token']));
            } elseif (isset($_GET['error'])) {
                $this->handleError($_GET['error']);
            } else {
                $this->initiate();
            }
        } catch (NamedUserException $e) {
            throw $e;
        } catch (StateValidationException $e) {
            throw new NamedUserException(
                WCF::getLanguage()->getDynamicVariable(
                    'wcf.user.3rdparty.login.error.stateValidation'
                )
            );
        } catch (Exception $e) {
            $exceptionID = logThrowable($e);

            $type = 'genericException';
            if ($e instanceof ClientExceptionInterface) {
                $type = 'httpError';
            }

            throw new NamedUserException(
                WCF::getLanguage()->getDynamicVariable(
                    'wcf.user.3rdparty.login.error.' . $type,
                    [
                        'exceptionID' => $exceptionID,
                    ]
                )
            );
        }
    }

    /**
     * Initiates the OAuth flow.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \wcf\system\exception\NamedUserException
     * @throws \wcf\system\exception\SystemException
     */
    protected function initiate(): void
    {
        $token = OpenAuthAPI::getInstance()->generateRandom();
        $scopes = OpenAuthAPI::getInstance()->getScopes();
        $authorizationUri = OpenAuthAPI::getInstance()->getAuthorizationURI($token, $scopes);

        WCF::getSession()->register(self::STATE, $token);

        if ($scopes === null) {
            throw new NamedUserException(
                WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.openauth.connect.error.configuration')
            );
        }

        if ($authorizationUri === null) {
            throw new NamedUserException(
                WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.openauth.connect.error.configuration')
            );
        }

        HeaderUtil::redirect($authorizationUri);

        exit;
    }

    /**
     * @throws \wcf\system\exception\NamedUserException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \wcf\system\exception\SystemException
     */
    protected function getUser(string $accessToken): array
    {
        $userData = OpenAuthAPI::getInstance()->getUserInfo($accessToken);

        if ($userData === null) {
            throw new NamedUserException(
                WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.openauth.connect.error.configuration')
            );
        }

        if (empty($userData['sub'])) {
            throw new NamedUserException(
                WCF::getLanguage()->get('wcf.user.3rdparty.openauth.connect.error.invalid')
            );
        }

        return $userData;
    }

    /**
     * @throws \wcf\system\exception\NamedUserException
     * @throws \wcf\system\exception\SystemException
     */
    protected function processUser(array $userData): void
    {
        $user = User::getUserByAuthData('openauth:' . $userData['sub']);

        if ($user->userID) {
            if (WCF::getUser()->userID) {
                // This account belongs to an existing user, but we are already logged in.
                // This can't be handled.
                throw new NamedUserException(
                    WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.facebook.connect.error.inuse')
                );
            }

            // This account belongs to an existing user, we are not logged in.
            // Perform the login, and update avatar, if there's one.
            if (!empty($userData['picture'])) {
                (new UserEditor($user))->update([
                    'openAuthAvatar' => $userData['picture'],
                ]);
            }

            WCF::getSession()->changeUser($user);
            WCF::getSession()->update();
            HeaderUtil::redirect(LinkHandler::getInstance()->getLink());

            exit;
        }

        WCF::getSession()->register('__3rdPartyProvider', 'facebook');

        if (WCF::getUser()->userID) {
            // This account does not belong to anyone, and we are already logged in.
            // Thus, we want to connect this account.
            WCF::getSession()->register('__oauthUser', $userData);

            HeaderUtil::redirect(LinkHandler::getInstance()->getLink('AccountManagement') . '#3rdParty');

            exit;
        }

        // This account does not belong to anyone, and we are not logged in.
        // Thus, we want to connect this account to a newly registered user.
        WCF::getSession()->register('__oauthUser', $userData);
        WCF::getSession()->register('__username', (!empty($userData['nickname'])) ? $userData['nickname'] : '');
        WCF::getSession()->register('__email', (!empty($userData['email'])) ? $userData['email'] : '');

        // We assume that bots won't register an external account first, so
        // we skip the captcha.
        WCF::getSession()->register('noRegistrationCaptcha', true);
        WCF::getSession()->update();

        HeaderUtil::redirect(LinkHandler::getInstance()->getControllerLink(RegisterForm::class));

        exit;
    }

    /**
     * @throws \wcf\system\exception\NamedUserException
     */
    protected function handleError(string $error): void
    {
        throw new NamedUserException(WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.login.error.' . $error));
    }

    /**
     * Validates the state parameter.
     */
    protected function validateState(): void
    {
        try {
            if (!isset($_GET['state'])) {
                throw new StateValidationException('Missing state parameter');
            }

            if (!($sessionState = WCF::getSession()->getVar(self::STATE))) {
                throw new StateValidationException('Missing state in session');
            }

            if (!\hash_equals($sessionState, (string)$_GET['state'])) {
                throw new StateValidationException('Mismatching state');
            }
        } finally {
            WCF::getSession()->unregister(self::STATE);
        }
    }
}
