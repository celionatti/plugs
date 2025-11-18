<?php

declare(strict_types=1);

namespace Plugs\Upload;

/*
|--------------------------------------------------------------------------
| FileUploader Class
|--------------------------------------------------------------------------
|
| Handles secure file uploads with comprehensive validation, storage management,
| and error handling. Production-ready with PSR coding standards.
|---------------------------------------------------------------------------
|
| @package Plugs\Upload
*/

use RuntimeException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class FileUploader
{
    private string $uploadPath;
    private array $allowedExtensions = [];
    private array $allowedMimeTypes = [];
    private int $maxSize = 10485760; // 10MB default
    private int $minSize = 0;
    private bool $generateUniqueName = true;
    private bool $checkActualMimeType = true;
    private bool $validateImageContent = true;
    private ?int $maxImageWidth = null;
    private ?int $maxImageHeight = null;
    private ?int $minImageWidth = null;
    private ?int $minImageHeight = null;
    private bool $organizeByDate = true;
    private bool $preventDuplicates = false;
    private array $uploadedHashes = [];
    private bool $blockDangerousExtensions = true;
    private bool $blockDoubleExtensions = true;
    private bool $allowSvg = false; // SVG disabled by default for security
    private LoggerInterface $logger;
    private int $maxRetries = 10; // For unique filename generation
    private ?string $quarantinePath = null; // Optional quarantine directory

    // Rate limiting
    private array $uploadCounts = [];
    private int $maxUploadsPerMinute = 10;

    /**
     * Create a new FileUploader instance
     */
    public function __construct(?string $uploadPath = null, ?LoggerInterface $logger = null)
    {
        $this->uploadPath = $uploadPath ?? $this->getDefaultUploadPath();
        $this->logger = $logger ?? new NullLogger();
        $this->ensureDirectoryExists($this->uploadPath);

        // Set up quarantine directory if available
        $this->quarantinePath = dirname($this->uploadPath) . '/quarantine';
        if (!is_dir($this->quarantinePath)) {
            @mkdir($this->quarantinePath, 0755, true);
        }
    }

    private function getDefaultUploadPath(): string
    {
        if (defined('BASE_PATH')) {
            return rtrim(BASE_PATH, '/') . '/storage/uploads';
        }
        return dirname(__DIR__, 2) . '/storage/uploads';
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true)) {
                throw new RuntimeException("Failed to create upload directory: {$path}");
            }
            $this->logger->info("Created upload directory", ['path' => $path]);
        }

        if (!is_writable($path)) {
            throw new RuntimeException("Upload directory is not writable: {$path}");
        }

        $this->createSecurityFiles($path);
    }

    private function createSecurityFiles(string $path): void
    {
        // .htaccess for Apache
        $htaccess = $path . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Prevent PHP execution and directory listing\n";
            $content .= "Options -Indexes\n";
            $content .= "php_flag engine off\n";
            $content .= "<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|phps|pht|phar|pl|py|cgi|sh)$\">\n";
            $content .= "    Require all denied\n";
            $content .= "</FilesMatch>\n";
            $content .= "# Security headers\n";
            $content .= "<IfModule mod_headers.c>\n";
            $content .= "    Header set X-Content-Type-Options \"nosniff\"\n";
            $content .= "    Header set Content-Security-Policy \"default-src 'none'; style-src 'unsafe-inline';\"\n";
            $content .= "</IfModule>\n";
            @file_put_contents($htaccess, $content);
        }

        // web.config for IIS
        $webConfig = $path . '/web.config';
        if (!file_exists($webConfig)) {
            $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $content .= '<configuration><system.webServer><handlers><clear />';
            $content .= '<add name="StaticFile" path="*" verb="*" modules="StaticFileModule" />';
            $content .= '</handlers></system.webServer></configuration>';
            @file_put_contents($webConfig, $content);
        }

        // index.html
        $index = $path . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory access is forbidden.</h1></body></html>');
        }
    }

    /**
     * Set rate limiting
     */
    public function setRateLimit(int $maxUploadsPerMinute): self
    {
        $this->maxUploadsPerMinute = $maxUploadsPerMinute;
        return $this;
    }

    /**
     * Enable/disable SVG uploads (disabled by default for security)
     */
    public function allowSvg(bool $allow = true): self
    {
        $this->allowSvg = $allow;
        if ($allow) {
            $this->logger->warning('SVG uploads enabled - ensure strict validation');
        }
        return $this;
    }

    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    public function setAllowedMimeTypes(array $mimeTypes): self
    {
        $this->allowedMimeTypes = array_map('strtolower', $mimeTypes);
        return $this;
    }

    public function setMaxSize(int $bytes): self
    {
        if ($bytes <= 0) {
            throw new InvalidArgumentException('Max size must be positive');
        }
        $this->maxSize = $bytes;
        return $this;
    }

    public function setMinSize(int $bytes): self
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Min size cannot be negative');
        }
        $this->minSize = $bytes;
        return $this;
    }

    public function setUploadPath(string $path): self
    {
        $this->uploadPath = rtrim($path, '/');
        $this->ensureDirectoryExists($this->uploadPath);
        return $this;
    }

    public function generateUniqueName(bool $generate = true): self
    {
        $this->generateUniqueName = $generate;
        return $this;
    }

    public function checkActualMimeType(bool $check = true): self
    {
        $this->checkActualMimeType = $check;
        return $this;
    }

    public function organizeByDate(bool $organize = true): self
    {
        $this->organizeByDate = $organize;
        return $this;
    }

    public function preventDuplicates(bool $prevent = true): self
    {
        $this->preventDuplicates = $prevent;
        return $this;
    }

    public function setImageDimensions(
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?int $minWidth = null,
        ?int $minHeight = null
    ): self {
        $this->maxImageWidth = $maxWidth;
        $this->maxImageHeight = $maxHeight;
        $this->minImageWidth = $minWidth;
        $this->minImageHeight = $minHeight;
        return $this;
    }

    public function imagesOnly(int $maxSize = 5242880): self
    {
        $this->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $this->setAllowedMimeTypes([
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        ]);
        $this->setMaxSize($maxSize);
        $this->validateImageContent = true;
        $this->allowSvg = false;
        return $this;
    }

    public function documentsOnly(int $maxSize = 10485760): self
    {
        $this->setAllowedExtensions(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']);
        $this->setAllowedMimeTypes([
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv'
        ]);
        $this->setMaxSize($maxSize);
        $this->validateImageContent = false;
        return $this;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(string $identifier): void
    {
        $minute = (int) (time() / 60);
        $key = $identifier . '_' . $minute;

        if (!isset($this->uploadCounts[$key])) {
            $this->uploadCounts[$key] = 0;
            // Cleanup old entries
            foreach ($this->uploadCounts as $k => $v) {
                if (!str_ends_with($k, '_' . $minute)) {
                    unset($this->uploadCounts[$k]);
                }
            }
        }

        $this->uploadCounts[$key]++;

        if ($this->uploadCounts[$key] > $this->maxUploadsPerMinute) {
            $this->logger->warning('Rate limit exceeded', ['identifier' => $identifier]);
            throw new RuntimeException('Upload rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Upload a single file with quarantine step
     */
    public function upload(UploadedFile $file, ?string $customName = null, ?string $userIdentifier = null): array
    {
        $startTime = microtime(true);

        try {
            // Rate limiting
            if ($userIdentifier) {
                $this->checkRateLimit($userIdentifier);
            }

            // Validate file
            $this->validate($file);

            // Check for duplicates
            if ($this->preventDuplicates && $existingFile = $this->checkDuplicate($file)) {
                $this->logger->info('Duplicate file detected', [
                    'hash' => $existingFile['hash'] ?? 'unknown',
                    'original' => $file->getClientFilename()
                ]);
                return array_merge($existingFile, ['duplicate' => true]);
            }

            // Generate filename with cryptographically secure randomness
            $filename = $this->generateFilename($file, $customName);

            // Determine target directory
            $targetDir = $this->organizeByDate
                ? $this->uploadPath . '/' . date('Y/m/d')
                : $this->uploadPath;

            $this->ensureDirectoryExists($targetDir);

            // ATOMIC filename generation - use lock file to prevent race condition
            $targetPath = $this->generateUniquePathAtomic($targetDir, $filename);
            $filename = basename($targetPath);

            // Move file
            try {
                $file->moveTo($targetPath);
            } catch (\Exception $e) {
                $this->logger->error('Failed to move file', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientFilename()
                ]);
                throw new RuntimeException('Failed to move uploaded file: ' . $e->getMessage());
            }

            // Verify file was saved correctly
            if (!file_exists($targetPath)) {
                throw new RuntimeException('File was not saved correctly');
            }

            // Additional post-upload security check
            $this->postUploadSecurityCheck($targetPath);

            // Get final file information
            $result = $this->buildFileInfo($file, $targetPath, $filename);

            // Store hash for duplicate detection
            if ($this->preventDuplicates && isset($result['hash'])) {
                $this->uploadedHashes[$result['hash']] = $result;
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('File uploaded successfully', [
                'filename' => $filename,
                'size' => $result['size'],
                'duration_ms' => $duration
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientFilename()
            ]);
            throw $e;
        }
    }

    /**
     * Atomic unique path generation to prevent race conditions
     */
    private function generateUniquePathAtomic(string $directory, string $filename): string
    {
        $info = pathinfo($filename);
        $name = $info['filename'];
        $extension = $info['extension'] ?? '';

        // Try with original name first
        $targetPath = $directory . '/' . $filename;
        $lockFile = $targetPath . '.lock';

        $attempts = 0;
        while ($attempts < $this->maxRetries) {
            // Try to create lock file atomically
            $fp = @fopen($lockFile, 'x');
            if ($fp !== false) {
                fclose($fp);

                // Check if target file exists
                if (!file_exists($targetPath)) {
                    // Success - we have the lock and file doesn't exist
                    @unlink($lockFile);
                    return $targetPath;
                }

                @unlink($lockFile);
            }

            // File exists or lock failed, generate new name
            $randomSuffix = bin2hex(random_bytes(8));
            $filename = sprintf(
                '%s_%s%s',
                $name,
                $randomSuffix,
                $extension ? '.' . $extension : ''
            );
            $targetPath = $directory . '/' . $filename;
            $lockFile = $targetPath . '.lock';

            $attempts++;

            // Small delay to prevent tight loop
            if ($attempts > 3) {
                usleep(10000); // 10ms
            }
        }

        // Fallback to timestamp + random
        $filename = sprintf(
            'file_%d_%s%s',
            time(),
            bin2hex(random_bytes(16)),
            $extension ? '.' . $extension : ''
        );

        return $directory . '/' . $filename;
    }

    /**
     * Post-upload security check
     */
    private function postUploadSecurityCheck(string $filepath): void
    {
        // Verify file still has safe permissions
        $perms = fileperms($filepath);
        if ($perms === false || ($perms & 0111)) {
            // File has execute permissions - dangerous!
            @unlink($filepath);
            throw new RuntimeException('Security violation: Uploaded file has execute permissions');
        }

        // Re-verify MIME type hasn't changed
        if ($this->checkActualMimeType && function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = @finfo_file($finfo, $filepath);
                finfo_close($finfo);

                if ($mimeType && !empty($this->allowedMimeTypes)) {
                    if (!in_array(strtolower($mimeType), $this->allowedMimeTypes, true)) {
                        @unlink($filepath);
                        throw new RuntimeException('Security violation: File MIME type changed after upload');
                    }
                }
            }
        }
    }

    public function uploadMultiple(array $files, ?string $userIdentifier = null): array
    {
        $uploaded = [];
        $errors = [];

        foreach ($files as $key => $file) {
            try {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $uploaded[] = $this->upload($file, null, $userIdentifier);
                } else {
                    $errors[$key] = [
                        'file' => $file instanceof UploadedFile ? $file->getClientFilename() : 'Unknown',
                        'error' => $file instanceof UploadedFile ? $file->getErrorMessage() : 'Invalid file object'
                    ];
                }
            } catch (\Exception $e) {
                $errors[$key] = [
                    'file' => $file instanceof UploadedFile ? $file->getClientFilename() : 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
            'success_count' => count($uploaded),
            'error_count' => count($errors),
            'total_size' => array_sum(array_column($uploaded, 'size'))
        ];
    }

    private function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new RuntimeException('Invalid file upload: ' . $file->getErrorMessage());
        }

        if (!$file->isUploaded()) {
            throw new RuntimeException('Security violation: File was not uploaded via HTTP POST');
        }

        // Check for SVG if not allowed
        if (!$this->allowSvg && $file->getClientExtension() === 'svg') {
            throw new RuntimeException('SVG uploads are disabled for security reasons');
        }

        if ($this->blockDangerousExtensions && $file->hasDangerousExtension()) {
            throw new RuntimeException('Security violation: Dangerous file extension detected');
        }

        if ($this->blockDoubleExtensions && $file->hasSuspiciousExtension()) {
            throw new RuntimeException('Security violation: Suspicious double extension detected');
        }

        if ($file->getSize() > $this->maxSize) {
            throw new RuntimeException(
                sprintf(
                    'File size (%s) exceeds maximum allowed: %s',
                    $this->formatBytes($file->getSize()),
                    $this->formatBytes($this->maxSize)
                )
            );
        }

        if ($file->getSize() < $this->minSize) {
            throw new RuntimeException(
                sprintf(
                    'File size is below minimum required: %s',
                    $this->formatBytes($this->minSize)
                )
            );
        }

        if ($file->getSize() === 0) {
            throw new RuntimeException('File is empty (0 bytes)');
        }

        if (!empty($this->allowedExtensions)) {
            $extension = $file->getClientExtension();
            if (!in_array($extension, $this->allowedExtensions, true)) {
                throw new RuntimeException(
                    'File type not allowed. Allowed types: ' . implode(', ', $this->allowedExtensions)
                );
            }
        }

        if ($this->checkActualMimeType && !empty($this->allowedMimeTypes)) {
            $actualMimeType = $file->getActualMediaType();
            if ($actualMimeType && !in_array($actualMimeType, $this->allowedMimeTypes, true)) {
                throw new RuntimeException(
                    'File MIME type not allowed. File appears to be: ' . $actualMimeType
                );
            }
        }

        if ($file->isImage() && $this->validateImageContent) {
            $this->validateImage($file);
        }
    }

    private function validateImage(UploadedFile $file): void
    {
        if (!$file->isActualImage()) {
            throw new RuntimeException('File is not a valid image despite having image extension');
        }

        $dimensions = $file->getImageDimensions();
        if ($dimensions === null) {
            return;
        }

        if ($this->maxImageWidth && $dimensions['width'] > $this->maxImageWidth) {
            throw new RuntimeException(
                sprintf(
                    'Image width (%dpx) exceeds maximum allowed: %dpx',
                    $dimensions['width'],
                    $this->maxImageWidth
                )
            );
        }

        if ($this->maxImageHeight && $dimensions['height'] > $this->maxImageHeight) {
            throw new RuntimeException(
                sprintf(
                    'Image height (%dpx) exceeds maximum allowed: %dpx',
                    $dimensions['height'],
                    $this->maxImageHeight
                )
            );
        }

        if ($this->minImageWidth && $dimensions['width'] < $this->minImageWidth) {
            throw new RuntimeException(
                sprintf(
                    'Image width (%dpx) is below minimum required: %dpx',
                    $dimensions['width'],
                    $this->minImageWidth
                )
            );
        }

        if ($this->minImageHeight && $dimensions['height'] < $this->minImageHeight) {
            throw new RuntimeException(
                sprintf(
                    'Image height (%dpx) is below minimum required: %dpx',
                    $dimensions['height'],
                    $this->minImageHeight
                )
            );
        }

        $this->verifyImageIntegrity($file->getTempPath(), $dimensions['type']);
    }

    private function verifyImageIntegrity(string $path, int $imageType): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return;
        }

        $img = null;
        try {
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $img = @imagecreatefromjpeg($path);
                    break;
                case IMAGETYPE_PNG:
                    $img = @imagecreatefrompng($path);
                    break;
                case IMAGETYPE_GIF:
                    $img = @imagecreatefromgif($path);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $img = @imagecreatefromwebp($path);
                    }
                    break;
                default:
                    return;
            }

            if ($img === false || $img === null) {
                throw new RuntimeException('Failed to process image: file may be corrupted or malicious');
            }

            imagedestroy($img);
        } catch (\Exception $e) {
            if ($img !== null && $img !== false) {
                @imagedestroy($img);
            }
            throw new RuntimeException('Image verification failed: ' . $e->getMessage());
        }
    }

    private function checkDuplicate(UploadedFile $file): ?array
    {
        $hash = $file->getHash();
        if ($hash && isset($this->uploadedHashes[$hash])) {
            return array_merge($this->uploadedHashes[$hash], ['duplicate' => true]);
        }
        return null;
    }

    private function generateFilename(UploadedFile $file, ?string $customName = null): string
    {
        $extension = $file->getClientExtension();

        if ($customName !== null) {
            $filename = $this->sanitizeFilename($customName);
            if ($extension && !str_ends_with(strtolower($filename), '.' . $extension)) {
                $filename .= '.' . $extension;
            }
            return $filename;
        }

        if ($this->generateUniqueName) {
            // Use only random bytes for maximum unpredictability
            return sprintf(
                '%s.%s',
                bin2hex(random_bytes(20)), // 40 character hex string
                $extension
            );
        }

        return $file->getSafeFilename();
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $info = pathinfo($filename);
        $name = $info['filename'] ?? 'file';
        $extension = $info['extension'] ?? '';

        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = trim($name, '_-');
        $name = preg_replace('/[_-]+/', '_', $name);

        if ($name === '') {
            $name = 'file_' . bin2hex(random_bytes(8));
        }

        if (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }

        return $extension ? $name . '.' . $extension : $name;
    }

    private function buildFileInfo(UploadedFile $file, string $targetPath, string $filename): array
    {
        $actualSize = filesize($targetPath);
        $relativePath = str_replace($this->uploadPath . '/', '', $targetPath);

        $info = [
            'name' => $filename,
            'original_name' => $file->getClientFilename(),
            'path' => $targetPath,
            'relative_path' => $relativePath,
            'url' => '/uploads/' . $relativePath,
            'size' => $actualSize,
            'type' => $file->getMimeType(),
            'extension' => $file->getClientExtension(),
            'uploaded_at' => date('Y-m-d H:i:s'),
            'uploaded_timestamp' => time(),
        ];

        if ($this->preventDuplicates) {
            $info['hash'] = hash_file('sha256', $targetPath);
        }

        if ($file->isImage() && $file->getClientExtension() !== 'svg') {
            $dimensions = $file->getImageDimensions();
            if ($dimensions) {
                $info['dimensions'] = [
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height']
                ];
            }
        }

        return $info;
    }

    public function delete(string $path): bool
    {
        if (!file_exists($path) || !is_file($path)) {
            return false;
        }

        $realPath = realpath($path);
        $realUploadPath = realpath($this->uploadPath);

        if ($realPath === false || $realUploadPath === false) {
            return false;
        }

        if (strpos($realPath, $realUploadPath) !== 0) {
            $this->logger->error('Delete attempt outside upload directory', [
                'path' => $path,
                'real_path' => $realPath
            ]);
            throw new RuntimeException('Security violation: Cannot delete files outside upload directory');
        }

        $result = @unlink($path);

        if ($result) {
            $this->logger->info('File deleted', ['path' => $path]);
        }

        return $result;
    }

    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public static function getMaxUploadSize(): int
    {
        $maxUpload = self::parseSize(ini_get('upload_max_filesize'));
        $maxPost = self::parseSize(ini_get('post_max_size'));
        $memoryLimit = self::parseSize(ini_get('memory_limit'));

        return min($maxUpload, $maxPost, $memoryLimit ?: PHP_INT_MAX);
    }

    private static function parseSize($size): int
    {
        if ($size === false || $size === '') {
            return 0;
        }

        $size = trim($size);
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;

        switch ($unit) {
            case 'g':
                $value *= 1024;
            // fall through
            case 'm':
                $value *= 1024;
            // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
