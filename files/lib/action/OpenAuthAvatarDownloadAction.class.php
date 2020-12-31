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

use wcf\data\user\avatar\OpenAuthAvatar;
use wcf\data\user\avatar\UserAvatar;
use wcf\data\user\User;
use wcf\data\user\UserEditor;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\SystemException;
use wcf\util\FileUtil;
use wcf\util\HTTPRequest;

use function file_exists;
use function file_put_contents;
use function header;
use function in_array;
use function mb_strtolower;
use function md5;
use function parse_url;
use function pathinfo;
use function readfile;
use function sprintf;

class OpenAuthAvatarDownloadAction extends AbstractAction
{
    /**
     * @var int
     */
    public $userID = 0;

    /**
     * @var User
     */
    public $user;

    /**
     * @var int
     */
    public $size = UserAvatar::AVATAR_SIZE;

    /**
     * @inheritDoc
     *
     * @throws IllegalLinkException
     */
    public function readParameters()
    {
        parent::readParameters();

        if (isset($_REQUEST['userID'])) {
            $this->userID = (int)$_REQUEST['userID'];
        }

        $this->user = new User($this->userID);

        if (!$this->user->userID) {
            throw new IllegalLinkException();
        }
    }

    /**
     * @inheritDoc
     *
     * @throws PermissionDeniedException
     * @throws SystemException
     */
    public function execute()
    {
        parent::execute();

        if (!empty($this->user->openAuthAvatar) && OPENAUTH_CLIENT_ID && OPENAUTH_CLIENT_SECRET) {
            $urlParsed = parse_url($this->user->openAuthAvatar);
            $pathInfo = pathinfo($urlParsed['path']);

            if (in_array($pathInfo['extension'], ['jpg', 'png', 'gif'])) {
                $contentType = 'image/png';
                $extension = 'png';

                if ($pathInfo['extension'] === 'jpg') {
                    $contentType = 'image/jpeg';
                    $extension = 'jpg';
                } elseif ($pathInfo['extension'] === 'gif') {
                    $contentType = 'image/gif';
                    $extension = 'gif';
                }

                $cachedFilename = sprintf(
                    OPENAuthAvatar::OPENAUTH_CACHE_LOCATION,
                    md5(mb_strtolower($this->user->openAuthAvatar)),
                    $extension
                );

                if (file_exists(WCF_DIR . $cachedFilename)) {
                    @header('content-type: ' . $contentType);
                    @readfile(WCF_DIR . $cachedFilename);
                    exit;
                }

                try {
                    $request = new HTTPRequest($this->user->openAuthAvatar);
                    $request->execute();
                    $reply = $request->getReply();

                    $fileExtension = 'png';
                    $mimeType = 'image/png';

                    if (isset($reply['httpHeaders']['content-type'][0])) {
                        switch ($reply['httpHeaders']['content-type'][0]) {
                            case 'image/jpeg':
                                $mimeType = 'image/jpeg';
                                $fileExtension = 'jpg';
                                break;
                            case 'image/gif':
                                $mimeType = 'image/gif';
                                $fileExtension = 'gif';
                                break;
                        }
                    }

                    $cachedFilename = sprintf(
                        OpenAuthAvatar::OPENAUTH_CACHE_LOCATION,
                        md5(mb_strtolower($this->user->openAuthAvatar)),
                        $fileExtension
                    );

                    file_put_contents(WCF_DIR . $cachedFilename, $reply['body']);
                    FileUtil::makeWritable(WCF_DIR . $cachedFilename);

                    @header('content-type: ' . $mimeType);
                    @readfile(WCF_DIR . $cachedFilename);

                    exit;
                } catch (SystemException $e) {
                    $editor = new UserEditor($this->user);
                    $editor->update([
                        'enableOpenAuthAvatar' => 0
                    ]);

                    throw $e;
                }
            }
        }

        @header('content-type: image/svg+xml');
        @readfile(WCF_DIR . 'images/avatars/avatar-default.svg');

        exit;
    }
}
