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

use wcf\data\user\option\UserOption;
use wcf\form\RegisterForm;
use wcf\page\AbstractPage;
use wcf\system\openauth\OpenAuthAPI;
use wcf\system\WCF;

class OpenAuthRegisterListener implements IParameterizedEventListener
{
    /**
     * @var array
     */
    protected $userData;

    /**
     * @var bool
     */
    protected $isInitialized = false;

    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (OPENAUTH_CLIENT_ID === "" && OPENAUTH_CLIENT_SECRET === "") {
            return;
        }

        $this->$eventName($eventObj, $parameters);
    }

    /**
     * @param RegisterForm $eventObj
     * @return void
     * @throws \wcf\system\exception\SystemException
     * @see AbstractForm::submit()
     */
    protected function submit($eventObj)
    {
        $this->readData($eventObj);
    }

    /**
     * @param RegisterForm $eventObj
     * @return void
     * @throws \wcf\system\exception\SystemException
     * @see AbstractPage::readData
     */
    protected function readData($eventObj)
    {
        if ($this->isInitialized) {
            return;
        }
        $this->isInitialized = true;

        if (WCF::getSession()->getVar('__3rdPartyProvider') !== 'openauth') {
            return;
        }

        if (empty(WCF::getSession()->getVar('__openAuthData'))) {
            return;
        }

        $this->userData = WCF::getSession()->getVar('__openAuthData');

        foreach (OpenAuthAPI::getInstance()->getUserOptionNames() as $optionName => $dataName) {
            if (empty($this->userData[$dataName])) {
                continue;
            }
            if (empty($eventObj->optionHandler->cachedOptions[$optionName])) {
                continue;
            }

            $optionData = $eventObj->optionHandler->cachedOptions[$optionName]->getData();
            if ($optionData['askDuringRegistration']) {
                continue;
            }

            $eventObj->optionHandler->optionValues[$optionName] = $this->userData[$dataName];

            $optionData['askDuringRegistration'] = 1;

            $userOption = new UserOption(null, $optionData);

            $eventObj->optionHandler->options[$optionName] = $userOption;
        }
    }

    /**
     * @param RegisterForm $eventObj
     * @return void
     * @see AbstractForm::save()
     *
     */
    protected function save($eventObj)
    {
        if (empty($this->userData)) {
            return;
        }

        $eventObj->additionalFields['authData'] = 'openauth:' . $this->userData['sub'];
        $eventObj->additionalFields['openAuthID'] = $this->userData['sub'];

        if (!empty($this->userData['picture'])) {
            $eventObj->additionalFields['openAuthAvatar'] = $this->userData['picture'];
            $eventObj->additionalFields['enableOpenAuthAvatar'] = 1;
        }
    }

    /**
     * registerVia3rdParty
     *
     * @param RegisterForm $eventObj
     * @param array $parameters
     * @return void
     */
    protected function registerVia3rdParty($eventObj, array &$parameters)
    {
        if (empty($this->userData['email_verified'])) {
            return;
        }

        if ($this->userData['email'] !== $eventObj->email) {
            return;
        }

        if ($this->userData['email'] !== $this->userData['email_verified']) {
            return;
        }

        $parameters['registerVia3rdParty'] = true;
    }

    /**
     * @return void
     * @see RegisterForm::saved
     *
     */
    protected function saved()
    {
        WCF::getSession()->unregister('__openAuthData');
    }
}
