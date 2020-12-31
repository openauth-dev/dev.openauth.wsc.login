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

use wcf\form\AbstractForm;
use wcf\form\AccountManagementForm;
use wcf\page\AbstractPage;
use wcf\system\exception\SystemException;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;
use wcf\util\HeaderUtil;
use wcf\util\StringUtil;

class OpenAuthAccountManagementListener implements IParameterizedEventListener
{
    /**
     * @var int
     */
    public $openAuthConnect = 0;

    /**
     * @var int
     */
    public $openAuthDisconnect = 0;

    /**
     * @var string
     */
    public $success = '';

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
     * @see AbstractForm::readFormParameters()
     */
    protected function readFormParameters()
    {
        if (isset($_POST['openauthDisconnect'])) {
            $this->openAuthDisconnect = (int)$_POST['openauthDisconnect'];
        }

        if (isset($_POST['openAuthConnect']) && !WCF::getUser()->hasAdministrativeAccess()) {
            $this->openAuthConnect = (int)$_POST['openAuthConnect'];
        }
    }

    /**
     * @see AbstractPage::assignVariables()
     */
    protected function assignVariables()
    {
        WCF::getTPL()->assign([
            'openAuthConnect' => $this->openAuthConnect,
            'openAuthDisconnect' => $this->openAuthDisconnect
        ]);
    }

    /**
     * @see AbstractForm::save()
     */
    protected function save(AccountManagementForm $eventObj)
    {
        if ($this->openAuthConnect && WCF::getSession()->getVar('__openAuthData')) {
            $userData = WCF::getSession()->getVar('__openAuthData');

            $eventObj->additionalFields['authData'] = 'openauth:' . $userData['sub'];
            $eventObj->additionalFields['openAuthID'] = $userData['sub'];
            $this->success = 'wcf.user.3rdparty.openauth.connect.success';

            WCF::getSession()->unregister('__openAuthData');
            WCF::getSession()->unregister('__openAuthUsername');
        } elseif ($this->openAuthDisconnect && StringUtil::startsWith(WCF::getUser()->authData, 'openauth:')) {
            $eventObj->additionalFields['authData'] = '';
            $eventObj->additionalFields['openAuthID'] = null;
            $eventObj->additionalFields['openAuthAvatar'] = null;
            $eventObj->additionalFields['enableOpenAuthAvatar'] = 0;
            $this->success = 'wcf.user.3rdparty.openauth.disconnect.success';
        }
    }

    /**
     * @throws SystemException
     *
     * @see AbstractForm::saved()
     */
    protected function saved()
    {
        if (!empty($this->success)) {
            HeaderUtil::delayedRedirect(
                LinkHandler::getInstance()->getLink('AccountManagement'),
                WCF::getLanguage()->getDynamicVariable($this->success),
                15
            );

            exit;
        }
    }
}
