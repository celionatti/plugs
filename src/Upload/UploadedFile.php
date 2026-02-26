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

use InvalidArgumentException;
use Plugs\Facades\Storage;
use RuntimeException;

class UploadedFile
{
    private string $name;
    private string $type;
    private string $tmpName;
    private int $error;
    private int $size;
    private ?string $actualMimeType = null;
    private bool $moved = false;
    private ?int $compressionQuality = null;

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
        'xap',
        'dll',
        'so',
    ];

    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/x-ms-bmp',
        'image/svg+xml',
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

    /**
     * Create from $_FILES array entry
     */
    public static function createFromFilesArray(array $file): self
    {
        return new self($file);
    }

    /**
     * Create multiple from $_FILES array
     */
    public static function createMultipleFromFilesArray(array $files): array
    {
        $uploadedFiles = [];

        // Handle both single and multiple file uploads
        if (isset($files['name'])) {
            if (is_array($files['name'])) {
                // Multiple files
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    $uploadedFiles[] = new self([
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i] ?? '',
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ]);
                }
            } else {
                // Single file
                $uploadedFiles[] = new self($files);
            }
        }

        return $uploadedFiles;
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

        if ($mimeType !== false) {
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
        $content = @file_get_contents($this->tmpName);
        if ($content === false) {
            return false;
        }

        // Check file size - SVGs shouldn't be huge
        if (strlen($content) > 2097152) { // 2MB
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
            '/<!ENTITY/i',
            '/xlink:href\s*=/i',
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
            'mime' => $imageInfo['mime'],
        ];
    }

    private function getSvgDimensions(): ?array
    {
        $content = @file_get_contents($this->tmpName);
        if ($content === false) {
            return null;
        }

        // Try width/height attributes first
        if (
            preg_match('/width=["\']?(\d+(?:\.\d+)?)["\']?/', $content, $width) &&
            preg_match('/height=["\']?(\d+(?:\.\d+)?)["\']?/', $content, $height)
        ) {
            return [
                'width' => (int) round((float) $width[1]),
                'height' => (int) round((float) $height[1]),
                'type' => 0,
                'mime' => 'image/svg+xml',
            ];
        }

        // Try viewBox as fallback
        if (preg_match('/viewBox=["\'][\d\s]+\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)["\']/', $content, $viewBox)) {
            return [
                'width' => (int) round((float) $viewBox[1]),
                'height' => (int) round((float) $viewBox[2]),
                'type' => 0,
                'mime' => 'image/svg+xml',
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

        // Apply compression if requested and file is an image
        if ($this->compressionQuality !== null && $this->isActualImage()) {
            return $this->getCompressedContents();
        }

        $contents = @file_get_contents($this->tmpName);
        if ($contents === false) {
            throw new RuntimeException('Failed to read uploaded file');
        }

        return $contents;
    }

    /**
     * Get the compressed contents of the image.
     *
     * @return string
     */
    private function getCompressedContents(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'plugs_comp_');

        try {
            $image = new \Plugs\Image\Image();
            $image->load($this->tmpName);
            $image->quality($this->compressionQuality);

            // Determine type based on extension or mime
            $extension = $this->getClientExtension();
            $type = match ($extension) {
                'png' => IMAGETYPE_PNG,
                'gif' => IMAGETYPE_GIF,
                'webp' => IMAGETYPE_WEBP,
                default => IMAGETYPE_JPEG,
            };

            $image->save($tempFile, $type);

            $contents = @file_get_contents($tempFile);
            if ($contents === false) {
                throw new RuntimeException('Failed to read compressed image data');
            }

            return $contents;
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
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

        // Normalize path
        $targetPath = $this->normalizePath($targetPath);

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

        // Security: Check for null bytes
        if (strpos($targetPath, "\0") !== false) {
            throw new RuntimeException('Security violation: Null byte detected in path');
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

    private function normalizePath(string $path): string
    {
        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize directory separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove relative path components
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($normalized);

                continue;
            }
            $normalized[] = $part;
        }

        $result = implode(DIRECTORY_SEPARATOR, $normalized);

        // Preserve leading slash for absolute paths
        if (strpos($path, DIRECTORY_SEPARATOR) === 0) {
            $result = DIRECTORY_SEPARATOR . $result;
        }

        return $result;
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

        // Check for multiple dots in suspicious patterns (e.g., file.php.jpg)
        if (substr_count($filename, '.') > 2) {
            $parts = explode('.', $filename);
            // Check if any part before the last is a dangerous extension
            array_pop($parts); // Remove actual extension
            foreach ($parts as $part) {
                if (in_array(strtolower($part), self::DANGEROUS_EXTENSIONS, true)) {
                    return true;
                }
            }
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

    /**
     * Store the uploaded file on a filesystem disk.
     *
     * @param string $path
     * @param array|string $options
     * @return string|false
     */
    public function store(string $path = '', $options = [])
    {
        return $this->storeAs($path, $this->hashName(), $this->parseOptions($options));
    }

    /**
     * Store the uploaded file on a filesystem disk with public visibility.
     *
     * @param string $path
     * @param array|string $options
     * @return string|false
     */
    public function storePublicly(string $path = '', $options = [])
    {
        $options = $this->parseOptions($options);
        $options['visibility'] = 'public';

        return $this->storeAs($path, $this->hashName(), $options);
    }

    /**
     * Store the uploaded file on a filesystem disk with public visibility.
     *
     * @param string $path
     * @param string $name
     * @param array|string $options
     * @return string|false
     */
    public function storePubliclyAs(string $path, string $name, $options = [])
    {
        $options = $this->parseOptions($options);
        $options['visibility'] = 'public';

        return $this->storeAs($path, $name, $options);
    }

    /**
     * Store the uploaded file on a filesystem disk.
     *
     * @param string $path
     * @param string $name
     * @param array|string $options
     * @return string|false
     */
    public function storeAs(string $path, string $name, $options = [])
    {
        $options = $this->parseOptions($options);
        $disk = $options['disk'] ?? null;
        unset($options['disk']);

        $storage = Storage::disk($disk);
        $targetPath = trim($path . '/' . $name, '/');

        if ($storage->put($targetPath, $this->getContents())) {
            if (isset($options['visibility'])) {
                $storage->setVisibility($targetPath, $options['visibility']);
            }

            return $targetPath;
        }

        return false;
    }

    /**
     * Get a hash of the file name.
     *
     * @param string|null $path
     * @return string
     */
    public function hashName(?string $path = null): string
    {
        $hash = $this->getHash('sha256') ?? bin2hex(random_bytes(20));

        if ($path) {
            $path = rtrim($path, '/') . '/';
        }

        return $path . $hash . '.' . $this->getClientExtension();
    }

    /**
     * Parse and format the given options.
     *
     * @param array|string $options
     * @return array
     */
    protected function parseOptions($options): array
    {
        if (is_string($options)) {
            return ['disk' => $options];
        }

        return $options;
    }

    /**
     * Set the compression quality for the image (1-100).
     *
     * @param int $quality
     * @return $this
     */
    public function compress(int $quality = 75): self
    {
        $this->compressionQuality = max(1, min(100, $quality));

        return $this;
    }
}
