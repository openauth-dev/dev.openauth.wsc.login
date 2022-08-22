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

use DomainException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use wcf\data\user\avatar\OpenAuthAvatar;
use wcf\data\user\avatar\UserAvatar;
use wcf\data\user\User;
use wcf\data\user\UserEditor;
use wcf\system\exception\IllegalLinkException;
use wcf\system\io\File;
use wcf\system\io\http\RedirectGuard;
use wcf\system\io\HttpFactory;
use wcf\util\FileUtil;
use wcf\util\Url;

use const IMAGETYPE_GIF;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;

class OpenAuthAvatarDownloadAction extends AbstractAction
{
    /**
     * @inheritDoc
     */
    public $neededModules = ['OPENAUTH_CLIENT_ID', 'OPENAUTH_CLIENT_SECRET'];

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
    public function readParameters(): void
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \wcf\system\exception\PermissionDeniedException
     * @throws \wcf\system\exception\SystemException
     */
    public function execute(): void
    {
        parent::execute();

        if (!empty($this->user->openAuthAvatar) && OPENAUTH_CLIENT_ID && OPENAUTH_CLIENT_SECRET) {
            $pathInfo = Url::parse($this->user->openAuthAvatar)['path'];

            if (\in_array($pathInfo['extension'], ['jpg', 'png', 'gif', 'webp'])) {
                if ($pathInfo['extension'] === 'jpg') {
                    $contentType = 'image/jpeg';
                    $extension = 'jpg';
                } elseif ($pathInfo['extension'] === 'gif') {
                    $contentType = 'image/gif';
                    $extension = 'gif';
                } elseif ($pathInfo['extension'] === 'webp') {
                    $contentType = 'image/webp';
                    $extension = 'webp';
                } else {
                    $contentType = 'image/png';
                    $extension = 'png';
                }

                $fileLocation = WCF_DIR . \sprintf(
                    OpenAuthAvatar::OPENAUTH_CACHE_LOCATION,
                    \md5(\mb_strtolower($this->user->openAuthAvatar)),
                    $extension
                );

                if (\file_exists($fileLocation)) {
                    $this->executed();

                    @\header('content-type: ' . $contentType);
                    @\readfile($fileLocation);

                    exit;
                }

                try {
                    // download image
                    $file = null;
                    $response = null;
                    $tmp = FileUtil::getTemporaryFilename('openauth_avatar_');

                    try {
                        $file = new File($tmp);
                        $client = HttpFactory::makeClient([
                            RequestOptions::TIMEOUT => 10,
                            RequestOptions::STREAM => true,
                            RequestOptions::ALLOW_REDIRECTS => [
                                'on_redirect' => new RedirectGuard(),
                            ],
                        ]);

                        $request = new Request('GET', $this->user->openAuthAvatar, ['accept' => 'image/*']);
                        $response = $client->send($request);

                        while (!$response->getBody()->eof()) {
                            try {
                                $file->write($response->getBody()->read(8192));
                            } catch (RuntimeException $e) {
                                throw new DomainException(
                                    'Failed to read response body.',
                                    0,
                                    $e
                                );
                            }
                        }

                        $file->flush();
                    } catch (TransferException $e) {
                        throw new DomainException('Failed to request', 0, $e);
                    } finally {
                        if ($response && $response->getBody()) {
                            $response->getBody()->close();
                        }

                        if ($file) {
                            $file->close();
                        }
                    }

                    // check file type
                    $imageData = @\getimagesize($tmp);

                    if (!$imageData) {
                        throw new DomainException();
                    }

                    switch ($imageData[2]) {
                        case IMAGETYPE_PNG:
                            $extension = 'png';
                            break;
                        case IMAGETYPE_GIF:
                            $extension = 'gif';
                            break;
                        case IMAGETYPE_JPEG:
                            $extension = 'jpg';
                            break;
                        default:
                            throw new DomainException();
                    }

                    $fileLocation = WCF_DIR . \sprintf(
                        OpenAuthAvatar::OPENAUTH_CACHE_LOCATION,
                        \md5(\mb_strtolower($this->user->openAuthAvatar)),
                        $extension
                    );

                    \rename($tmp, $fileLocation);

                    // update mtime for correct expiration calculation
                    @\touch($fileLocation);

                    $this->executed();

                    @\header('content-type: ' . $imageData['mime']);
                    @\readfile($fileLocation);

                    exit;
                } catch (DomainException $e) {
                    (new UserEditor($this->user))->update(['enableOpenAuthAvatar' => 0]);
                }
            }
        }

        $this->executed();

        @\header('content-type: image/svg+xml');
        @\readfile(WCF_DIR . 'images/avatars/avatar-default.svg');

        exit;
    }
}
