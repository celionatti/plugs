<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\ResponseFactory;
use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Media Controller for handling rich text editor uploads and other media tasks.
 */
class MediaController
{
    /**
     * Handle AJAX image upload from rich text editor.
     */
    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $files = $request->getUploadedFiles();

            if (empty($files['image'])) {
                return ResponseFactory::json(['error' => 'No image file uploaded.'], 400);
            }

            $file = $files['image'];

            // Plugs framework uses UploadedFile wrapper for security and validation
            if (!($file instanceof UploadedFile)) {
                $file = new UploadedFile([
                    'tmp_name' => $file->getStream()->getMetadata('uri') ?: '',
                    'name' => $file->getClientFilename(),
                    'type' => $file->getClientMediaType(),
                    'size' => $file->getSize(),
                    'error' => $file->getError()
                ]);
            }

            $uploader = FileUploader::make('public')
                ->setBasePath('editor/images')
                ->imagesOnly();

            $params = $request->getQueryParams();
            $maxWidth = isset($params['max_width']) ? (int) $params['max_width'] : null;
            $maxHeight = isset($params['max_height']) ? (int) $params['max_height'] : null;

            if ($maxWidth || $maxHeight) {
                $uploader->setMaxImageDimensions($maxWidth, $maxHeight);
            }

            $result = $uploader->upload($file);

            return ResponseFactory::json([
                'url' => $result['url'],
                'name' => $result['name'],
                'size' => $result['size']
            ]);

        } catch (\Exception $e) {
            return ResponseFactory::json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
