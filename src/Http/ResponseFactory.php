<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| ResponseFactory Class
|--------------------------------------------------------------------------
|
| This class is responsible for creating HTTP response instances. It provides
| methods to generate standard HTTP responses that can be returned to
| clients.
*/

use Plugs\Http\Message\Stream;
use Plugs\Http\Message\Response;

use Psr\Http\Message\ResponseInterface;

class ResponseFactory
{
    /**
     * Create a JSON response
     */
    public static function json($data, int $statusCode = 200, array $headers = []): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'w+'));
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $body->write($jsonData);
        $body->rewind();

        // Merge headers
        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Length' => strlen($jsonData)
        ], $headers);

        return new Response($statusCode, $body, $headers);
    }

    /**
     * Create a text response
     */
    public static function text(string $text, int $statusCode = 200, array $headers = []): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'w+'));
        $body->write($text);
        $body->rewind();

        // Merge headers
        $headers = array_merge([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Length' => strlen($text)
        ], $headers);

        return new Response($statusCode, $body, $headers);
    }

    /**
     * Create an HTML response
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'w+'));
        $body->write($html);
        $body->rewind();

        // Merge headers
        $headers = array_merge([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => strlen($html)
        ], $headers);

        return new Response($statusCode, $body, $headers);
    }

    /**
     * Create a no-content response
     */
    public static function noContent(int $statusCode = 204, array $headers = []): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'r'));
        return new Response($statusCode, $body, $headers);
    }

    /**
     * Create a download response
     */
    public static function download(string $filePath, ?string $fileName = null, array $headers = []): ResponseInterface
    {
        if (!file_exists($filePath)) {
            return self::text('File not found', 404);
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $fileStream = new Stream(fopen($filePath, 'r'));

        $headers = array_merge([
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => $fileSize,
            'Pragma' => 'public',
            'Cache-Control' => 'must-revalidate'
        ], $headers);

        return new Response(200, $fileStream, $headers);
    }

    /**
     * Create a file response (inline)
     */
    public static function file(string $filePath, ?string $fileName = null, array $headers = []): ResponseInterface
    {
        if (!file_exists($filePath)) {
            return self::text('File not found', 404);
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $fileStream = new Stream(fopen($filePath, 'r'));

        // Try to detect content type
        $contentType = self::getMimeType($filePath);

        $headers = array_merge([
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Content-Length' => $fileSize,
            'Cache-Control' => 'public, max-age=3600'
        ], $headers);

        return new Response(200, $fileStream, $headers);
    }

    /**
     * Get MIME type for file
     */
    private static function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'txt' => 'text/plain',
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Create an error response
     */
    public static function error(string $message, int $statusCode = 500, array $headers = []): ResponseInterface
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        // Return JSON for API requests
        if (strpos($accept, 'application/json') !== false) {
            return self::json([
                'error' => true,
                'message' => $message,
                'status' => $statusCode
            ], $statusCode, $headers);
        }

        // Return HTML for browser requests
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Error {$statusCode}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    padding: 50px; 
                    background: #f8f9fa;
                }
                .error-container { 
                    background: white; 
                    padding: 40px; 
                    border-radius: 8px; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    max-width: 500px;
                    margin: 0 auto;
                }
                .error-code { 
                    font-size: 48px; 
                    color: #dc3545; 
                    margin: 0;
                }
                .error-message { 
                    font-size: 18px; 
                    color: #6c757d; 
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1 class='error-code'>{$statusCode}</h1>
                <p class='error-message'>{$message}</p>
                <a href='/' style='color: #007bff; text-decoration: none;'>Go Home</a>
            </div>
        </body>
        </html>";

        return self::html($html, $statusCode, $headers);
    }

    /**
     * Create a success response
     */
    public static function success(string $message, $data = null, int $statusCode = 200, array $headers = []): ResponseInterface
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        // Return JSON for API requests
        if (strpos($accept, 'application/json') !== false) {
            $response = [
                'success' => true,
                'message' => $message,
                'status' => $statusCode
            ];

            if ($data !== null) {
                $response['data'] = $data;
            }

            return self::json($response, $statusCode, $headers);
        }

        // Return HTML for browser requests
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Success</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    padding: 50px; 
                    background: #f8f9fa;
                }
                .success-container { 
                    background: white; 
                    padding: 40px; 
                    border-radius: 8px; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    max-width: 500px;
                    margin: 0 auto;
                }
                .success-icon { 
                    font-size: 48px; 
                    color: #28a745; 
                    margin: 0;
                }
                .success-message { 
                    font-size: 18px; 
                    color: #6c757d; 
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class='success-container'>
                <h1 class='success-icon'>✓</h1>
                <p class='success-message'>{$message}</p>
                <a href='/' style='color: #007bff; text-decoration: none;'>Continue</a>
            </div>
        </body>
        </html>";

        return self::html($html, $statusCode, $headers);
    }
}