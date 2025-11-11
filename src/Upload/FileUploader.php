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

    // Security settings
    private bool $blockDangerousExtensions = true;
    private bool $blockDoubleExtensions = true;

    /**
     * Create a new FileUploader instance
     * 
     * @param string|null $uploadPath Base upload directory path
     * @throws RuntimeException If directory cannot be created or is not writable
     */
    public function __construct(?string $uploadPath = null)
    {
        $this->uploadPath = $uploadPath ?? $this->getDefaultUploadPath();
        $this->ensureDirectoryExists($this->uploadPath);
    }

    /**
     * Get default upload path
     */
    private function getDefaultUploadPath(): string
    {
        if (defined('BASE_PATH')) {
            return rtrim(BASE_PATH, '/') . '/public/uploads';
        }
        return getcwd() . '/uploads';
    }

    /**
     * Ensure directory exists and is writable
     * 
     * @throws RuntimeException If directory cannot be created or accessed
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true)) {
                throw new RuntimeException("Failed to create upload directory: {$path}");
            }
        }

        if (!is_writable($path)) {
            throw new RuntimeException("Upload directory is not writable: {$path}");
        }

        // Create .htaccess for additional security
        $this->createSecurityFiles($path);
    }

    /**
     * Create security files in upload directory
     */
    private function createSecurityFiles(string $path): void
    {
        $htaccess = $path . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Prevent PHP execution in uploads directory\n";
            $content .= "php_flag engine off\n";
            $content .= "<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|phps|pht|phar)$\">\n";
            $content .= "    Deny from all\n";
            $content .= "</FilesMatch>\n";
            @file_put_contents($htaccess, $content);
        }

        // Create index.html to prevent directory listing
        $index = $path . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '<!-- Directory listing disabled -->');
        }
    }

    /**
     * Set allowed file extensions
     */
    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    /**
     * Set allowed MIME types
     */
    public function setAllowedMimeTypes(array $mimeTypes): self
    {
        $this->allowedMimeTypes = array_map('strtolower', $mimeTypes);
        return $this;
    }

    /**
     * Set maximum file size in bytes
     */
    public function setMaxSize(int $bytes): self
    {
        if ($bytes <= 0) {
            throw new InvalidArgumentException('Max size must be positive');
        }
        $this->maxSize = $bytes;
        return $this;
    }

    /**
     * Set minimum file size in bytes
     */
    public function setMinSize(int $bytes): self
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Min size cannot be negative');
        }
        $this->minSize = $bytes;
        return $this;
    }

    /**
     * Set upload path
     */
    public function setUploadPath(string $path): self
    {
        $this->uploadPath = rtrim($path, '/');
        $this->ensureDirectoryExists($this->uploadPath);
        return $this;
    }

    /**
     * Enable/disable unique name generation
     */
    public function generateUniqueName(bool $generate = true): self
    {
        $this->generateUniqueName = $generate;
        return $this;
    }

    /**
     * Enable/disable actual MIME type checking
     */
    public function checkActualMimeType(bool $check = true): self
    {
        $this->checkActualMimeType = $check;
        return $this;
    }

    /**
     * Enable/disable date-based directory organization
     */
    public function organizeByDate(bool $organize = true): self
    {
        $this->organizeByDate = $organize;
        return $this;
    }

    /**
     * Enable/disable duplicate file prevention
     */
    public function preventDuplicates(bool $prevent = true): self
    {
        $this->preventDuplicates = $prevent;
        return $this;
    }

    /**
     * Set image dimension constraints
     */
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

    /**
     * Preset configuration for image uploads only
     */
    public function imagesOnly(int $maxSize = 5242880): self
    {
        $this->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
        $this->setAllowedMimeTypes([
            'image/jpeg', 'image/jpg', 'image/png', 
            'image/gif', 'image/webp', 'image/svg+xml'
        ]);
        $this->setMaxSize($maxSize);
        $this->validateImageContent = true;
        return $this;
    }

    /**
     * Preset configuration for document uploads
     */
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
     * Upload a single file
     */
    public function upload(UploadedFile $file, ?string $customName = null): array
    {
        // Validate file
        $this->validate($file);

        // Check for duplicates
        if ($this->preventDuplicates && $existingFile = $this->checkDuplicate($file)) {
            return $existingFile;
        }

        // Generate filename
        $filename = $this->generateFilename($file, $customName);

        // Determine target directory
        $targetDir = $this->organizeByDate 
            ? $this->uploadPath . '/' . date('Y/m/d')
            : $this->uploadPath;

        // Ensure target directory exists
        $this->ensureDirectoryExists($targetDir);

        // Generate unique filename if file exists
        $targetPath = $targetDir . '/' . $filename;
        if (file_exists($targetPath)) {
            $filename = $this->generateUniqueFilename($targetDir, $filename);
            $targetPath = $targetDir . '/' . $filename;
        }

        // Move file
        try {
            $file->moveTo($targetPath);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to move uploaded file: ' . $e->getMessage());
        }

        // Verify file was saved correctly
        if (!file_exists($targetPath)) {
            throw new RuntimeException('File was not saved correctly');
        }

        // Get final file information
        $result = $this->buildFileInfo($file, $targetPath, $filename);

        // Store hash for duplicate detection
        if ($this->preventDuplicates && isset($result['hash'])) {
            $this->uploadedHashes[$result['hash']] = $result;
        }

        return $result;
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(array $files): array
    {
        $uploaded = [];
        $errors = [];

        foreach ($files as $key => $file) {
            try {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $uploaded[] = $this->upload($file);
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

    /**
     * Validate uploaded file
     */
    private function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new RuntimeException('Invalid file upload: ' . $file->getErrorMessage());
        }

        if (!$file->isUploaded()) {
            throw new RuntimeException('Security violation: File was not uploaded via HTTP POST');
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

    /**
     * Validate image file
     */
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

    /**
     * Verify image integrity by attempting to re-encode it
     */
    private function verifyImageIntegrity(string $path, int $imageType): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return;
        }

        try {
            $img = null;
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
            throw new RuntimeException('Image verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if file is duplicate
     */
    private function checkDuplicate(UploadedFile $file): ?array
    {
        $hash = $file->getHash();
        if ($hash && isset($this->uploadedHashes[$hash])) {
            return array_merge($this->uploadedHashes[$hash], ['duplicate' => true]);
        }
        return null;
    }

    /**
     * Generate filename for uploaded file
     */
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
            return sprintf(
                '%s_%s.%s',
                date('Ymd_His'),
                bin2hex(random_bytes(8)),
                $extension
            );
        }

        return $file->getSafeFilename();
    }

    /**
     * Sanitize custom filename
     */
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
            $name = 'file_' . uniqid('', true);
        }

        if (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }

        return $extension ? $name . '.' . $extension : $name;
    }

    /**
     * Generate unique filename if file exists
     */
    private function generateUniqueFilename(string $directory, string $filename): string
    {
        $info = pathinfo($filename);
        $name = $info['filename'];
        $extension = $info['extension'] ?? '';
        $counter = 1;

        while (file_exists($directory . '/' . $filename) && $counter <= 1000) {
            $filename = sprintf(
                '%s_%d%s',
                $name,
                $counter,
                $extension ? '.' . $extension : ''
            );
            $counter++;
        }

        if ($counter > 1000) {
            $filename = sprintf(
                'file_%s%s',
                bin2hex(random_bytes(16)),
                $extension ? '.' . $extension : ''
            );
        }

        return $filename;
    }

    /**
     * Build file information array
     */
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
        ];

        if ($this->preventDuplicates) {
            $info['hash'] = hash_file('sha256', $targetPath);
        }

        if ($file->isImage()) {
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

    /**
     * Delete uploaded file
     */
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
            throw new RuntimeException('Security violation: Cannot delete files outside upload directory');
        }

        return @unlink($path);
    }

    /**
     * Get upload path
     */
    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }

    /**
     * Format bytes to human-readable size
     */
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

    /**
     * Get maximum upload size from PHP configuration
     */
    public static function getMaxUploadSize(): int
    {
        $maxUpload = self::parseSize(ini_get('upload_max_filesize'));
        $maxPost = self::parseSize(ini_get('post_max_size'));
        $memoryLimit = self::parseSize(ini_get('memory_limit'));

        return min($maxUpload, $maxPost, $memoryLimit ?: PHP_INT_MAX);
    }

    /**
     * Parse PHP size string to bytes
     */
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
