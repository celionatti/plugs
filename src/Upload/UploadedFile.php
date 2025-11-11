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

    /**
     * Dangerous file extensions that should be blocked
     */
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
        'config'
    ];

    /**
     * Image MIME types for validation
     */
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

    /**
     * Create a new UploadedFile instance
     * 
     * @param array $file The $_FILES array element
     * @throws InvalidArgumentException If file array is invalid
     */
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

        // Get actual MIME type from file content (more secure than client-provided)
        if ($this->isValid() && $this->isUploaded() && is_readable($this->tmpName)) {
            $this->detectActualMimeType();
        }
    }

    /**
     * Detect the actual MIME type from file content
     */
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

        if ($mimeType !== false) {
            $this->actualMimeType = strtolower($mimeType);
        }
    }

    /**
     * Get the original client filename
     */
    public function getClientFilename(): string
    {
        return $this->name;
    }

    /**
     * Get file extension from original filename
     */
    public function getClientExtension(): string
    {
        $extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
        return $extension !== '' ? $extension : '';
    }

    /**
     * Get MIME type provided by the client
     */
    public function getClientMediaType(): string
    {
        return $this->type;
    }

    /**
     * Get actual MIME type detected from file content
     * More reliable than client-provided type for security
     */
    public function getActualMediaType(): ?string
    {
        return $this->actualMimeType;
    }

    /**
     * Get the best available MIME type (actual or client)
     */
    public function getMimeType(): string
    {
        return $this->actualMimeType ?? $this->type;
    }

    /**
     * Get file size in bytes
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get upload error code
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Get temporary file path
     */
    public function getTempPath(): string
    {
        return $this->tmpName;
    }

    /**
     * Check if upload was successful (no errors)
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && !empty($this->tmpName);
    }

    /**
     * Verify the file was actually uploaded via HTTP POST
     * Critical security check to prevent local file inclusion attacks
     */
    public function isUploaded(): bool
    {
        return !empty($this->tmpName) && is_uploaded_file($this->tmpName);
    }

    /**
     * Check if file has already been moved
     */
    public function isMoved(): bool
    {
        return $this->moved;
    }

    /**
     * Check if file appears to be an image based on extension
     */
    public function isImage(): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return in_array($this->getClientExtension(), $imageExtensions, true);
    }

    /**
     * Verify file is actually an image by checking content
     * More reliable and secure than extension-based check
     */
    public function isActualImage(): bool
    {
        if (!$this->isValid() || !$this->isUploaded()) {
            return false;
        }

        // Check actual MIME type
        if (
            $this->actualMimeType !== null &&
            !in_array($this->actualMimeType, self::IMAGE_MIME_TYPES, true)
        ) {
            return false;
        }

        // SVG files need special handling
        if ($this->actualMimeType === 'image/svg+xml') {
            return $this->isSafeSvg();
        }

        // Use getimagesize for additional verification
        $imageInfo = @getimagesize($this->tmpName);
        return $imageInfo !== false;
    }

    /**
     * Basic SVG safety check (looks for script tags)
     */
    private function isSafeSvg(): bool
    {
        $content = @file_get_contents($this->tmpName, false, null, 0, 8192);
        if ($content === false) {
            return false;
        }

        // Check for dangerous patterns in SVG
        $dangerousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i', // Event handlers like onclick=
            '/<iframe/i',
            '/<embed/i',
            '/<object/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get image dimensions if file is a valid image
     * 
     * @return array{width: int, height: int, type: int, mime: string}|null
     */
    public function getImageDimensions(): ?array
    {
        if (!$this->isActualImage()) {
            return null;
        }

        // SVG dimensions are not easily determined
        if ($this->actualMimeType === 'image/svg+xml') {
            return null;
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

    /**
     * Get file contents as string
     * 
     * @throws RuntimeException If file cannot be read
     */
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

    /**
     * Move uploaded file to destination
     * 
     * @param string $targetPath Absolute path where file should be moved
     * @throws RuntimeException If move operation fails
     */
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

        // Validate and prepare target directory
        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException("Directory is not writable: {$directory}");
        }

        // Validate filename
        $filename = basename($targetPath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new RuntimeException('Invalid target filename');
        }

        // Security: Check for directory traversal
        $realDirectory = realpath($directory);
        $realTarget = $realDirectory . DIRECTORY_SEPARATOR . $filename;
        if ($realTarget !== $targetPath && realpath($targetPath) !== $realTarget) {
            throw new RuntimeException('Security violation: Directory traversal detected');
        }

        // Move the file
        if (!@move_uploaded_file($this->tmpName, $targetPath)) {
            throw new RuntimeException('Failed to move uploaded file to destination');
        }

        // Set proper permissions
        @chmod($targetPath, 0644);

        $this->moved = true;
        return true;
    }

    /**
     * Get human-readable error message
     */
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

    /**
     * Check if filename has suspicious double extension
     * Example: malicious.php.jpg
     */
    public function hasSuspiciousExtension(): bool
    {
        $filename = strtolower($this->name);

        // Check for dangerous extensions anywhere in filename
        foreach (self::DANGEROUS_EXTENSIONS as $ext) {
            // Look for patterns like .php.jpg or .php%00.jpg
            if (preg_match('/\.' . preg_quote($ext, '/') . '(?:[\x00\.]|$)/i', $filename)) {
                return true;
            }
        }

        // Check for null byte injection
        if (strpos($filename, "\x00") !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if file extension is dangerous
     */
    public function hasDangerousExtension(): bool
    {
        $extension = $this->getClientExtension();
        return in_array($extension, self::DANGEROUS_EXTENSIONS, true);
    }

    /**
     * Generate a safe, sanitized filename
     * Removes dangerous characters and ensures valid format
     */
    public function getSafeFilename(): string
    {
        $name = pathinfo($this->name, PATHINFO_FILENAME);
        $extension = $this->getClientExtension();

        // Remove any non-alphanumeric characters except dash and underscore
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = trim($name, '_-');

        // Remove consecutive underscores/dashes
        $name = preg_replace('/[_-]+/', '_', $name);

        // If name is empty after sanitization, use timestamp
        if ($name === '') {
            $name = 'file_' . time();
        }

        // Limit length to prevent filesystem issues
        if (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }

        return $extension !== '' ? $name . '.' . $extension : $name;
    }

    /**
     * Get file hash for duplicate detection
     * 
     * @param string $algorithm Hashing algorithm (md5, sha1, sha256)
     */
    public function getHash(string $algorithm = 'sha256'): ?string
    {
        if (!$this->isValid() || !$this->isUploaded() || $this->moved) {
            return null;
        }

        return @hash_file($algorithm, $this->tmpName) ?: null;
    }
}
