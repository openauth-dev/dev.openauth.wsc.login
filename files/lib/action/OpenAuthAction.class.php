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
use wcf\data\user\User;
use wcf\data\user\UserEditor;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\NamedUserException;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\SystemException;
use wcf\system\openauth\OpenAuthAPI;
use wcf\system\request\LinkHandler;
use wcf\system\user\authentication\UserAuthenticationFactory;
use wcf\system\WCF;
use wcf\util\HeaderUtil;

use function hash_equals;

class OpenAuthAction extends AbstractAction
{
    /**
     * @inheritDoc
     */
    public $neededModules = [
        'OPENAUTH_CLIENT_ID',
        'OPENAUTH_CLIENT_SECRET'
    ];

    /**
     * @inheritDoc
     *
     * @throws IllegalLinkException
     */
    public function readParameters() {
        parent::readParameters();

        if (WCF::getSession()->spiderID) {
            throw new IllegalLinkException();
        }
    }

    /**
     * @inheritDoc
     *
     * @throws IllegalLinkException
     * @throws NamedUserException
     * @throws PermissionDeniedException
     * @throws SystemException
     * @throws Exception
     */
    public function execute()
    {
        parent::execute();

        if (isset($_REQUEST['code'])) {
            // check state
            if (
                empty($_REQUEST['state']) ||
                !WCF::getSession()->getVar('__openauthInit') ||
                !hash_equals($_REQUEST['state'], WCF::getSession()->getVar('__openauthInit'))
            ) {
                throw new IllegalLinkException();
            }

            WCF::getSession()->unregister('__openauthInit');

            // check OAuth code
            $tokenInfo = OpenAuthAPI::getInstance()->checkOAuthCode($_REQUEST['code']);

            if ($tokenInfo === null) {
                throw new NamedUserException(WCF::getLanguage()->getDynamicVariable(
                    'wcf.user.3rdparty.openauth.connect.error.configuration')
                );
            }

            if (empty($tokenInfo['access_token'])) {
                throw new NamedUserException(WCF::getLanguage()->get(
                    'wcf.user.3rdparty.openauth.connect.error.invalid')
                );
            }

            // get user data
            $userData = OpenAuthAPI::getInstance()->getUserInfo($tokenInfo['access_token']);

            if ($userData === null) {
                throw new NamedUserException(WCF::getLanguage()->getDynamicVariable(
                    'wcf.user.3rdparty.openauth.connect.error.configuration')
                );
            }

            if (empty($userData['sub'])) {
                throw new NamedUserException(WCF::getLanguage()->get(
                    'wcf.user.3rdparty.openauth.connect.error.invalid')
                );
            }

            $openAuthUserID = $userData['sub'];
            $openAuthUsername = (!empty($userData['nickname'])) ? $userData['nickname'] : '';
            $openAuthMail = (!empty($userData['email'])) ? $userData['email'] : '';
            $openAuthAvatar = (!empty($userData['picture'])) ? $userData['picture'] : null;

            $user = User::getUserByAuthData('openauth:' . $openAuthUserID);

            if (WCF::getUser()->userID) {
                // user is signed in and would connect
                if ($user->userID) {
                    // another account is connected to this openauth account
                    throw new NamedUserException(WCF::getLanguage()->getDynamicVariable(
                        'wcf.user.3rdparty.openauth.connect.error.inuse')
                    );
                }

                WCF::getSession()->register('__openAuthUsername', $openAuthUsername);
                WCF::getSession()->register('__openAuthData', $userData);

                HeaderUtil::redirect(LinkHandler::getInstance()->getLink('AccountManagement') . '#3rdParty');

                $this->executed();

                exit;
            }

            if ($user->userID) {
                $userAuthentication = UserAuthenticationFactory::getInstance()->getUserAuthentication();

                if (null === $userAuthentication) {
                    throw new SystemException('No valid authentication instance found.');
                }

                // login
                if ($userAuthentication->supportsPersistentLogins()) {
                    $password = OpenAuthAPI::getInstance()->generateRandom();
                    $updateData = [
                        'openAuthAvatar' => $openAuthAvatar,
                        'password' => $password
                    ];

                    $userEditor = new UserEditor($user);
                    $userEditor->update($updateData);

                    // reload user to retrieve salt
                    $user = new User($user->userID);

                    $userAuthentication->storeAccessData($user, $user->username, $password);
                }

                WCF::getSession()->changeUser($user);
                WCF::getSession()->update();

                HeaderUtil::redirect(LinkHandler::getInstance()->getLink());

                $this->executed();

                exit;
            }

            // register
            WCF::getSession()->register('__3rdPartyProvider', 'openauth');
            WCF::getSession()->register('__openAuthData', $userData);
            WCF::getSession()->register('__username', $openAuthUsername);
            WCF::getSession()->register('__email', $openAuthMail);

            if (REGISTER_USE_CAPTCHA) {
                WCF::getSession()->register('noRegistrationCaptcha', true);
            }

            WCF::getSession()->update();

            HeaderUtil::redirect(LinkHandler::getInstance()->getLink('Register'));

            $this->executed();

            exit;
        }

        // user declined or another error occured
        if (isset($_GET['error'])) {
            throw new NamedUserException(WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.openauth.connect.error.' . $_GET['error']));
        }

        $state = OpenAuthAPI::getInstance()->generateRandom();
        $scopes = OpenAuthAPI::getInstance()->getScopes();
        $authorizationUri = OpenAuthAPI::getInstance()->getAuthorizationURI($state, $scopes);

        WCF::getSession()->register('__openauthInit', $state);

        if ($scopes === null) {
            throw new NamedUserException(WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.openauth.connect.error.configuration'));
        }

        if ($authorizationUri === null) {
            throw new NamedUserException(WCF::getLanguage()->getDynamicVariable('wcf.user.3rdparty.openauth.connect.error.configuration'));
        }

        HeaderUtil::redirect($authorizationUri);

        $this->executed();

        exit;
    }
}
