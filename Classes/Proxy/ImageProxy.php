<?php

namespace Netlogix\Nxmediaproxy\Proxy;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\Exception\OnlineMediaAlreadyExistsException;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Frontend\Controller\ErrorController;

final readonly class ImageProxy
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private OnlineMediaHelperRegistry $helper,
        private ErrorController $errorController,
    ) {
    }

    public function youtube(string $content, array $conf, ServerRequestInterface $request)
    {
        $queryParams = $request->getQueryParams();
        if (!array_key_exists('video-id', $queryParams)) {
            exit;
        }

        $id = $queryParams['video-id'];
        $this->redirectToThumbnail("https://youtu.be/{$id}", $request);
    }

    public function vimeo(string $content, array $conf, ServerRequestInterface $request)
    {
        $queryParams = $request->getQueryParams();
        if (!array_key_exists('video-id', $queryParams)) {
            exit;
        }

        $id = $queryParams['video-id'];
        $this->redirectToThumbnail("https://vimeo.com/{$id}", $request);
    }

    private function redirectToThumbnail(string $url, ServerRequestInterface $request)
    {
        $storage = $this->resourceFactory->getStorageObject(0);
        if (!$storage->hasFolder('typo3temp/assets/online_media_proxy')) {
            $storage->createFolder('typo3temp/assets/online_media_proxy');
        }
        $folder = $storage->getFolder('typo3temp/assets/online_media_proxy');

        try {
            try {
                $file = $this->helper->transformUrlToFile($url, $folder);
            } catch (OnlineMediaAlreadyExistsException $e) {
                $file = $e->getOnlineMedia();
            }

            $previewImage = $this->resourceFactory->retrieveFileOrFolderObject(
                $this->helper->getOnlineMediaHelper($file)->getPreviewImage($file)
            );

            $previewThumbnail = $previewImage->process(ProcessedFile::CONTEXT_IMAGECROPSCALEMASK, [
                'width' => '1120'
            ]);
            $response = new RedirectResponse($previewThumbnail->getPublicUrl());
        } catch (Throwable) {
            $response = $this->errorController->pageNotFoundAction($request, 'Video not found');
        }

        // Cache 404 and redirect response for short amount of time to prevent DoS attacks
        if ($request->getAttribute('frontend.cache.instruction')->isCachingAllowed()) {
            $response = $response
                ->withHeader('Expires', gmdate('D, d M Y H:i:s T', $GLOBALS['EXEC_TIME'] + 60))
                ->withHeader('Cache-Control', 'max-age=0, s-maxage=60')
                ->withHeader('Pragma', 'public');
        }

        throw new ImmediateResponseException($response, 1660655670);
    }

}
