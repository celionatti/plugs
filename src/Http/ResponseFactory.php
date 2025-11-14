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
use Psr\Http\Message\StreamInterface;

class ResponseFactory
{
    /**
     * Create a basic response
     */
    public static function createResponse(int $statusCode = 200, array $headers = []): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'w+'));
        return new Response($statusCode, $body, $headers);
    }

    /**
     * Create a response with a body
     */
    public static function create(string $content = '', int $statusCode = 200, array $headers = []): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'w+'));

        if (!empty($content)) {
            $body->write($content);
            $body->rewind();
        }

        return new Response($statusCode, $body, $headers);
    }

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
     * Create a redirect response
     */
    public static function redirect(string $url, int $statusCode = 302, array $headers = []): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'r'));

        $headers = array_merge([
            'Location' => $url
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
            'Content-Length' => (string) $fileSize,
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
            'Content-Length' => (string) $fileSize,
            'Cache-Control' => 'public, max-age=3600'
        ], $headers);

        return new Response(200, $fileStream, $headers);
    }

    /**
     * Create a streamed file response (for large files)
     */
    public static function stream(string $filePath, ?string $fileName = null, array $headers = []): ResponseInterface
    {
        if (!file_exists($filePath)) {
            return self::text('File not found', 404);
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $contentType = self::getMimeType($filePath);

        $headers = array_merge([
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Content-Length' => (string) $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600'
        ], $headers);

        // Handle range requests for video/audio streaming
        $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';

        if (!empty($rangeHeader)) {
            return self::handleRangeRequest($filePath, $fileSize, $rangeHeader, $contentType, $headers);
        }

        $fileStream = new Stream(fopen($filePath, 'r'));
        return new Response(200, $fileStream, $headers);
    }

    /**
     * Handle range request for streaming
     */
    private static function handleRangeRequest(
        string $filePath,
        int $fileSize,
        string $rangeHeader,
        string $contentType,
        array $headers
    ): ResponseInterface {
        // Parse range header (e.g., "bytes=0-1023")
        preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches);

        $start = (int) ($matches[1] ?? 0);
        $end = !empty($matches[2]) ? (int) $matches[2] : $fileSize - 1;

        // Validate range
        if ($start > $end || $start >= $fileSize) {
            return self::text('Requested Range Not Satisfiable', 416);
        }

        $length = $end - $start + 1;

        // Open file and seek to start position
        $handle = fopen($filePath, 'r');
        fseek($handle, $start);

        $body = new Stream($handle);

        $headers = array_merge($headers, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) $length,
            'Content-Range' => "bytes $start-$end/$fileSize",
            'Accept-Ranges' => 'bytes'
        ]);

        return new Response(206, $body, $headers); // 206 Partial Content
    }

    /**
     * Get MIME type for file
     */
    private static function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            // Text
            'txt' => 'text/plain',
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',

            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            '7z' => 'application/x-7z-compressed',

            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',

            // Audio
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',

            // Video
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
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
        $html = self::errorTemplate($statusCode, $message);
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
        $html = self::successTemplate($message);
        return self::html($html, $statusCode, $headers);
    }

    /**
     * Create a validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed', int $statusCode = 422, array $headers = []): ResponseInterface
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (strpos($accept, 'application/json') !== false) {
            return self::json([
                'error' => true,
                'message' => $message,
                'errors' => $errors,
                'status' => $statusCode
            ], $statusCode, $headers);
        }

        // For non-JSON requests, return simple error page
        return self::error($message, $statusCode, $headers);
    }

    /**
     * Create a not found (404) response
     */
    public static function notFound(string $message = 'Page Not Found', array $headers = []): ResponseInterface
    {
        return self::error($message, 404, $headers);
    }

    /**
     * Create an unauthorized (401) response
     */
    public static function unauthorized(string $message = 'Unauthorized', array $headers = []): ResponseInterface
    {
        return self::error($message, 401, $headers);
    }

    /**
     * Create a forbidden (403) response
     */
    public static function forbidden(string $message = 'Forbidden', array $headers = []): ResponseInterface
    {
        return self::error($message, 403, $headers);
    }

    /**
     * Create a server error (500) response
     */
    public static function serverError(string $message = 'Internal Server Error', array $headers = []): ResponseInterface
    {
        return self::error($message, 500, $headers);
    }

    /**
     * Error page HTML template
     */
    private static function errorTemplate(int $code, string $message): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Error {$code}</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    text-align: center; 
                    padding: 50px 20px; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .error-container { 
                    background: white; 
                    padding: 60px 40px; 
                    border-radius: 16px; 
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 500px;
                    width: 100%;
                    animation: slideUp 0.5s ease-out;
                }
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .error-code { 
                    font-size: 72px; 
                    font-weight: bold;
                    color: #dc3545; 
                    margin: 0 0 20px 0;
                    line-height: 1;
                }
                .error-message { 
                    font-size: 20px; 
                    color: #495057; 
                    margin: 20px 0 30px 0;
                    line-height: 1.6;
                }
                .error-link {
                    display: inline-block;
                    padding: 12px 30px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    border-radius: 25px;
                    font-weight: 600;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .error-link:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
                }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1 class='error-code'>{$code}</h1>
                <p class='error-message'>{$message}</p>
                <a href='/' class='error-link'>Go Home</a>
            </div>
        </body>
        </html>";
    }

    /**
     * Success page HTML template
     */
    private static function successTemplate(string $message): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Success</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    text-align: center; 
                    padding: 50px 20px; 
                    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .success-container { 
                    background: white; 
                    padding: 60px 40px; 
                    border-radius: 16px; 
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 500px;
                    width: 100%;
                    animation: slideUp 0.5s ease-out;
                }
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .success-icon { 
                    font-size: 72px; 
                    color: #28a745; 
                    margin: 0 0 20px 0;
                    line-height: 1;
                }
                .success-message { 
                    font-size: 20px; 
                    color: #495057; 
                    margin: 20px 0 30px 0;
                    line-height: 1.6;
                }
                .success-link {
                    display: inline-block;
                    padding: 12px 30px;
                    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                    color: white;
                    text-decoration: none;
                    border-radius: 25px;
                    font-weight: 600;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .success-link:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 25px rgba(17, 153, 142, 0.4);
                }
            </style>
        </head>
        <body>
            <div class='success-container'>
                <h1 class='success-icon'>✓</h1>
                <p class='success-message'>{$message}</p>
                <a href='/' class='success-link'>Continue</a>
            </div>
        </body>
        </html>";
    }
}