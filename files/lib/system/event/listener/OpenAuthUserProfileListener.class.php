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

use wcf\data\user\avatar\OpenAuthAvatar;

class OpenAuthUserProfileListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if ($eventObj->enableOpenAuthAvatar) {
            $parameters['avatar'] = new OpenAuthAvatar($eventObj->userID);
        }
    }
}
