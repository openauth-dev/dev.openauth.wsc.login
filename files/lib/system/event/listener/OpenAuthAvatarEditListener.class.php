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

namespace wcf\system\event\listener;

use wcf\acp\form\UserEditForm;
use wcf\data\user\avatar\OpenAuthAvatar;
use wcf\data\user\User;
use wcf\data\user\UserAction;
use wcf\form\AbstractForm;
use wcf\form\AvatarEditForm;
use wcf\system\WCF;

class OpenAuthAvatarEditListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     *
     * @return void
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (OPENAUTH_CLIENT_ID === "" && OPENAUTH_CLIENT_SECRET === "") {
            return;
        }

        $this->$eventName($eventObj);
    }

    /**
     * @param AvatarEditForm $eventObj
     * @return void
     * @see AbstractPage::readData()
     *
     */
    protected function readData(AvatarEditForm $eventObj)
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
     * @param AvatarEditForm $eventObj
     * @return void
     * @see AbstractPage::assignVariables()
     */
    protected function assignVariables(AvatarEditForm $eventObj)
    {
        $this->readData($eventObj);
    }

    /**
     * @param AvatarEditForm $eventObj
     * @return void
     * @throws \wcf\system\exception\SystemException
     * @see AbstractForm::save()
     */
    protected function save(AvatarEditForm $eventObj)
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
     * @param AvatarEditForm $eventObj
     * @return void
     * @see AbstractForm::saved()
     */
    protected function saved(AvatarEditForm $eventObj)
    {
        if (isset($_POST['avatarType']) && $_POST['avatarType'] === 'OpenAuth') {
            $eventObj->avatarType = 'OpenAuth';
        }
    }

    /**
     * Resets openauth avatar after disabling.
     *
     * @param User $user
     * @return void
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

        $cachedFilename = sprintf(OpenAuthAvatar::OPENAUTH_CACHE_LOCATION, md5(mb_strtolower($user->openAuthAvatar)), $pathInfo['extension']);

        if (file_exists(WCF_DIR . $cachedFilename)) {
            @unlink(WCF_DIR . $cachedFilename);
        }
    }
}
