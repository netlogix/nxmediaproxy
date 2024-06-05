<?php

namespace Netlogix\Nxmediaproxy\Proxy;

use Throwable;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\Exception\OnlineMediaAlreadyExistsException;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;

class ImageProxy
{

    /**
     * @var ServerRequest
     */
    private $request;

    public function __construct()
    {
        // Inject request from globals until request will be available to cObj
        $this->request = $GLOBALS['TYPO3_REQUEST'];
    }

    public function youtube()
    {
        $queryParams = $this->request->getQueryParams();
        if (!array_key_exists('video-id', $queryParams) && !array_key_exists('id', $queryParams)) {
            exit;
        }

        $id = $queryParams['video-id'] ?? $queryParams['id'];
        $this->redirectToThumbnail("https://youtu.be/{$id}");
    }

    public function vimeo()
    {
        $queryParams = $this->request->getQueryParams();
        if (!array_key_exists('video-id', $queryParams) && !array_key_exists('id', $queryParams)) {
            exit;
        }

        $id = $queryParams['video-id'] ?? $queryParams['id'];
        $this->redirectToThumbnail("https://vimeo.com/{$id}");
    }

    private function redirectToThumbnail(string $url)
    {
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getStorageObject(0);
        if (!$storage->hasFolder('typo3temp/assets/online_media_proxy')) {
            $storage->createFolder('typo3temp/assets/online_media_proxy');
        }
        $folder = $storage->getFolder('typo3temp/assets/online_media_proxy');

        $helper = GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class);
        try {
            try {
                $file = $helper->transformUrlToFile($url, $folder);
            } catch (OnlineMediaAlreadyExistsException $e) {
                $file = $e->getOnlineMedia();
            }

            $previewImage = GeneralUtility::makeInstance(ResourceFactory::class)->retrieveFileOrFolderObject(
                $helper->getOnlineMediaHelper($file)->getPreviewImage($file)
            );

            $previewThumbnail = $previewImage->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, [
                'width' => '1120'
            ]);
            $response = new RedirectResponse($previewThumbnail->getPublicUrl());
        } catch (Throwable) {
            $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $this->request,
                'Video not found',
            );
        }

        // Cache 404 and redirect response for short amount of time to prevent DoS attacks
        if (!empty($GLOBALS['TSFE']->config['config']['sendCacheHeaders'])) {
            $response = $response->withHeader('Expires', gmdate('D, d M Y H:i:s T', $GLOBALS['EXEC_TIME'] + 60));
            $response = $response->withHeader('Cache-Control', 'public, max-age=60');
            $response = $response->withHeader('Pragma', 'public');
        }

        throw new ImmediateResponseException($response, 1660655670);
    }

}
