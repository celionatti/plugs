<?php

declare(strict_types=1);

namespace Plugs\Upload;

/*
|--------------------------------------------------------------------------
| UploadedFile Class
|--------------------------------------------------------------------------
|
| Represents an uploaded file with comprehensive validation and security features.
| Implements PSR-7 inspired interface for file handling.
|
| This class represents an uploaded file and provides methods to access
| its properties and move it to a desired location.
|--------------------------------------------------------------------------
| @package Plugs\Upload
*/

use RuntimeException;
use InvalidArgumentException;

class UploadedFile
{
    private string $name;
    private string $type;
    private string $tmpName;
    private int $error;
    private int $size;
    private ?string $actualMimeType = null;
    private bool $moved = false;

    private const DANGEROUS_EXTENSIONS = [
        'php',
        'phtml',
        'php3',
        'php4',
        'php5',
        'php7',
        'phps',
        'pht',
        'phar',
        'cgi',
        'pl',
        'py',
        'sh',
        'bash',
        'bat',
        'cmd',
        'com',
        'exe',
        'msi',
        'vbs',
        'ws',
        'wsf',
        'scr',
        'htaccess',
        'htpasswd',
        'ini',
        'config',
        'jsp',
        'asp',
        'aspx',
        'cer',
        'asa',
        'swf',
        'xap'
    ];

    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/x-ms-bmp',
        'image/svg+xml'
    ];

    public function __construct(array $file)
    {
        if (!isset($file['name'], $file['tmp_name'], $file['error'], $file['size'])) {
            throw new InvalidArgumentException('Invalid file array structure');
        }

        $this->name = (string) $file['name'];
        $this->type = (string) ($file['type'] ?? '');
        $this->tmpName = (string) $file['tmp_name'];
        $this->error = (int) $file['error'];
        $this->size = (int) $file['size'];

        if ($this->isValid() && $this->isUploaded() && is_readable($this->tmpName)) {
            $this->detectActualMimeType();
        }
    }

    private function detectActualMimeType(): void
    {
        if (!function_exists('finfo_open')) {
            return;
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return;
        }

        $mimeType = @finfo_file($finfo, $this->tmpName);
        finfo_close($finfo);

        if ($mimeType !== false && is_string($mimeType)) {
            $this->actualMimeType = strtolower($mimeType);
        }
    }

    public function getClientFilename(): string
    {
        return $this->name;
    }

    public function getClientExtension(): string
    {
        $extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
        return $extension !== '' ? $extension : '';
    }

    public function getClientMediaType(): string
    {
        return $this->type;
    }

    public function getActualMediaType(): ?string
    {
        return $this->actualMimeType;
    }

    public function getMimeType(): string
    {
        return $this->actualMimeType ?? $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getTempPath(): string
    {
        return $this->tmpName;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && !empty($this->tmpName);
    }

    public function isUploaded(): bool
    {
        return !empty($this->tmpName) && is_uploaded_file($this->tmpName);
    }

    public function isMoved(): bool
    {
        return $this->moved;
    }

    public function isImage(): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return in_array($this->getClientExtension(), $imageExtensions, true);
    }

    public function isActualImage(): bool
    {
        if (!$this->isValid() || !$this->isUploaded()) {
            return false;
        }

        if (
            $this->actualMimeType !== null &&
            !in_array($this->actualMimeType, self::IMAGE_MIME_TYPES, true)
        ) {
            return false;
        }

        if ($this->actualMimeType === 'image/svg+xml') {
            return $this->isSafeSvg();
        }

        $imageInfo = @getimagesize($this->tmpName);
        return $imageInfo !== false;
    }

    private function isSafeSvg(): bool
    {
        $content = @file_get_contents($this->tmpName, false, null, 0, 8192);
        if ($content === false) {
            return false;
        }

        $dangerousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<embed/i',
            '/<object/i',
            '/data:text\/html/i',
            '/<foreignObject/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    public function getImageDimensions(): ?array
    {
        if (!$this->isActualImage()) {
            return null;
        }

        if ($this->actualMimeType === 'image/svg+xml') {
            return $this->getSvgDimensions();
        }

        $imageInfo = @getimagesize($this->tmpName);
        if ($imageInfo === false) {
            return null;
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime'] ?? $this->actualMimeType ?? ''
        ];
    }

    private function getSvgDimensions(): ?array
    {
        $content = @file_get_contents($this->tmpName);
        if ($content === false) {
            return null;
        }

        // Try to extract width and height from SVG
        if (
            preg_match('/width=["\'](\d+)["\']/', $content, $width) &&
            preg_match('/height=["\'](\d+)["\']/', $content, $height)
        ) {
            return [
                'width' => (int) $width[1],
                'height' => (int) $height[1],
                'type' => IMAGETYPE_SVG ?? 0,
                'mime' => 'image/svg+xml'
            ];
        }

        return null;
    }

    public function getContents(): string
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot read invalid upload: ' . $this->getErrorMessage());
        }

        if (!$this->isUploaded()) {
            throw new RuntimeException('File was not uploaded via HTTP POST');
        }

        if ($this->moved) {
            throw new RuntimeException('File has already been moved');
        }

        $contents = @file_get_contents($this->tmpName);
        if ($contents === false) {
            throw new RuntimeException('Failed to read uploaded file');
        }

        return $contents;
    }

    public function moveTo(string $targetPath): bool
    {
        if ($this->moved) {
            throw new RuntimeException('File has already been moved');
        }

        if (!$this->isValid()) {
            throw new RuntimeException('Cannot move invalid upload: ' . $this->getErrorMessage());
        }

        if (!$this->isUploaded()) {
            throw new RuntimeException('Security violation: File was not uploaded via HTTP POST');
        }

        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException("Directory is not writable: {$directory}");
        }

        $filename = basename($targetPath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new RuntimeException('Invalid target filename');
        }

        // Security: Prevent directory traversal
        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            throw new RuntimeException('Invalid directory path');
        }

        $expectedPath = $realDirectory . DIRECTORY_SEPARATOR . $filename;
        $normalizedTarget = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);

        // Allow both formats but ensure it's within the directory
        if (strpos($normalizedTarget, $realDirectory) !== 0) {
            throw new RuntimeException('Security violation: Directory traversal detected');
        }

        if (!@move_uploaded_file($this->tmpName, $targetPath)) {
            $error = error_get_last();
            throw new RuntimeException(
                'Failed to move uploaded file: ' . ($error['message'] ?? 'Unknown error')
            );
        }

        @chmod($targetPath, 0644);

        $this->moved = true;
        return true;
    }

    public function getErrorMessage(): string
    {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension',
        ];

        return $errors[$this->error] ?? 'Unknown upload error (code: ' . $this->error . ')';
    }

    public function hasSuspiciousExtension(): bool
    {
        $filename = strtolower($this->name);

        foreach (self::DANGEROUS_EXTENSIONS as $ext) {
            if (preg_match('/\.' . preg_quote($ext, '/') . '(?:[\x00\.]|$)/i', $filename)) {
                return true;
            }
        }

        if (strpos($filename, "\x00") !== false) {
            return true;
        }

        // Check for multiple dots in suspicious patterns
        if (substr_count($filename, '.') > 2) {
            return true;
        }

        return false;
    }

    public function hasDangerousExtension(): bool
    {
        $extension = $this->getClientExtension();
        return in_array($extension, self::DANGEROUS_EXTENSIONS, true);
    }

    public function getSafeFilename(): string
    {
        $name = pathinfo($this->name, PATHINFO_FILENAME);
        $extension = $this->getClientExtension();

        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = trim($name, '_-');
        $name = preg_replace('/[_-]+/', '_', $name);

        if ($name === '') {
            $name = 'file_' . time() . '_' . bin2hex(random_bytes(4));
        }

        if (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }

        return $extension !== '' ? $name . '.' . $extension : $name;
    }

    public function getHash(string $algorithm = 'sha256'): ?string
    {
        if (!$this->isValid() || !$this->isUploaded() || $this->moved) {
            return null;
        }

        if (!in_array($algorithm, hash_algos(), true)) {
            return null;
        }

        return @hash_file($algorithm, $this->tmpName) ?: null;
    }
}
