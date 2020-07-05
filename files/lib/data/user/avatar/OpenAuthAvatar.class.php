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

namespace wcf\data\user\avatar;

use wcf\system\cache\runtime\UserRuntimeCache;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;

class OpenAuthAvatar extends DefaultAvatar
{
    /**
     * @var string
     */
    const OPENAUTH_CACHE_LOCATION = 'images/avatars/openauth/%s.%s';

    /**
     * @var int
     */
    const OPENAUTH_CACHE_EXPIRE = 7;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var int
     */
    public $userID = 0;

    /**
     * OpenAuthAvatar constructor.
     * 
     * @param $userID
     */
    public function __construct($userID)
    {
        parent::__construct();
        
        $this->userID = $userID;
    }

    /**
     * @inheritDoc
     * 
     * @throws \wcf\system\exception\SystemException
     */
    public function getURL($size = null)
    {
        if (empty($this->url)) {
            $user = UserRuntimeCache::getInstance()->getObject($this->userID);
            
            if (empty($user->openAuthAvatar)) {
                return '';
            }

            $urlParsed = parse_url($user->openAuthAvatar);
            $pathInfo = pathinfo($urlParsed['path']);
            
            if (!in_array($pathInfo['extension'], ['jpg', 'png', 'gif'])) {
                return '';
            }

            $cachedFilename = sprintf(self::OPENAUTH_CACHE_LOCATION, md5(mb_strtolower($user->openAuthAvatar)), $pathInfo['extension']);
            
            if (file_exists(WCF_DIR . $cachedFilename) && filemtime(WCF_DIR . $cachedFilename) > (TIME_NOW - (self::OPENAUTH_CACHE_EXPIRE * 86400))) {
                $this->url = WCF::getPath() . $cachedFilename;
            } else {
                $this->url = LinkHandler::getInstance()->getLink('OpenAuthAvatarDownload', [
                    'forceFrontend' => true
                ], 'userID=' . $this->userID);
            }
        }

        return $this->url;
    }
}
