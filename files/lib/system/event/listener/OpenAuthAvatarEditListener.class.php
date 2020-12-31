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

namespace wcf\system\event\listener;

use wcf\acp\form\UserEditForm;
use wcf\data\user\avatar\OpenAuthAvatar;
use wcf\data\user\User;
use wcf\data\user\UserAction;
use wcf\form\AbstractForm;
use wcf\form\AvatarEditForm;
use wcf\system\exception\SystemException;
use wcf\system\WCF;

use function file_exists;
use function in_array;
use function mb_strtolower;
use function md5;
use function parse_url;
use function pathinfo;
use function sprintf;
use function unlink;

class OpenAuthAvatarEditListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (OPENAUTH_CLIENT_ID === "" && OPENAUTH_CLIENT_SECRET === "") {
            return;
        }

        $this->$eventName($eventObj);
    }

    /**
     * @param AvatarEditForm|UserEditForm $eventObj
     *
     * @see AbstractPage::readData()
     */
    protected function readData($eventObj)
    {
        if (empty($_POST)) {
            if ($eventObj instanceof UserEditForm) {
                if ((int)$eventObj->user->getDecoratedObject()->enableOpenAuthAvatar === 1) {
                    $eventObj->avatarType = 'OpenAuth';
                }
            } elseif ((int)WCF::getUser()->enableOpenAuthAvatar === 1) {
                $eventObj->avatarType = 'OpenAuth';
            }
        }
    }

    /**
     * @param AvatarEditForm|UserEditForm $eventObj
     *
     * @see AbstractPage::assignVariables()
     */
    protected function assignVariables($eventObj)
    {
        $this->readData($eventObj);
    }

    /**
     * @param AvatarEditForm|UserEditForm $eventObj
     * @throws SystemException
     *
     * @see AbstractForm::save()
     */
    protected function save($eventObj)
    {
        if (isset($_POST['avatarType'])) {
            $data = [];

            if ($_POST['avatarType'] === 'OpenAuth') {
                $eventObj->avatarType = 'custom';
                $data['enableOpenAuthAvatar'] = 1;
            } else {
                $data['enableOpenAuthAvatar'] = 0;

                if ($eventObj instanceof UserEditForm) {
                    $this->resetOpenAuthAvatarCache($eventObj->user->getDecoratedObject());
                } else {
                    $this->resetOpenAuthAvatarCache(WCF::getUser());
                }
            }

            if ($eventObj instanceof UserEditForm) {
                $eventObj->user->update($data);
            } else {
                $objectAction = new UserAction([WCF::getUser()], 'update', ['data' => $data]);
                $objectAction->executeAction();
            }
        }
    }

    /**
     * @param AvatarEditForm|UserEditForm $eventObj
     *
     * @see AbstractForm::saved()
     */
    protected function saved($eventObj)
    {
        if (isset($_POST['avatarType']) && $_POST['avatarType'] === 'OpenAuth') {
            $eventObj->avatarType = 'OpenAuth';
        }
    }

    /**
     * Resets openauth avatar after disabling.
     */
    private function resetOpenAuthAvatarCache(User $user)
    {
        if (!$user->enableOpenAuthAvatar) {
            return;
        }

        $urlParsed = parse_url($user->openAuthAvatar);
        $pathInfo = pathinfo($urlParsed['path']);

        if (!in_array($pathInfo['extension'], ['jpg', 'png', 'gif'])) {
            return;
        }

        $cachedFilename = sprintf(
            OpenAuthAvatar::OPENAUTH_CACHE_LOCATION,
            md5(mb_strtolower($user->openAuthAvatar)),
            $pathInfo['extension']
        );

        if (file_exists(WCF_DIR . $cachedFilename)) {
            @unlink(WCF_DIR . $cachedFilename);
        }
    }
}
