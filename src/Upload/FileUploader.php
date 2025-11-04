<?php

declare(strict_types=1);

namespace Plugs\Upload;

/*
|--------------------------------------------------------------------------
| FileUploader Class
|--------------------------------------------------------------------------
|
| This class handles file uploads, including validation, storage,
| and error handling with production-ready security and reliability.
*/

class FileUploader
{
    private $uploadPath;
    private $allowedExtensions = [];
    private $allowedMimeTypes = [];
    private $maxSize = 10485760; // 10MB default
    private $minSize = 0; // No minimum by default
    private $generateUniqueName = true;
    private $checkActualMimeType = true;
    private $validateImageContent = true;
    private $maxImageWidth = null;
    private $maxImageHeight = null;
    private $minImageWidth = null;
    private $minImageHeight = null;

    // Security settings
    private $blockDangerousExtensions = true;
    private $blockDoubleExtensions = true;

    public function __construct(string|null $uploadPath = null)
    {
        $this->uploadPath = $uploadPath ?? (defined('BASE_PATH') ? BASE_PATH . 'public/uploads' : 'uploads');

        if (!is_dir($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true)) {
                throw new \RuntimeException("Failed to create upload directory: {$this->uploadPath}");
            }
        }

        if (!is_writable($this->uploadPath)) {
            throw new \RuntimeException("Upload directory is not writable: {$this->uploadPath}");
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
        $this->maxSize = $bytes;
        return $this;
    }

    /**
     * Set minimum file size in bytes
     */
    public function setMinSize(int $bytes): self
    {
        $this->minSize = $bytes;
        return $this;
    }

    /**
     * Set upload path
     */
    public function setUploadPath(string $path): self
    {
        $this->uploadPath = $path;

        if (!is_dir($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true)) {
                throw new \RuntimeException("Failed to create upload directory: {$path}");
            }
        }

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
     * Set image dimension constraints
     */
    public function setImageDimensions(?int $maxWidth = null, ?int $maxHeight = null, ?int $minWidth = null, ?int $minHeight = null): self
    {
        $this->maxImageWidth = $maxWidth;
        $this->maxImageHeight = $maxHeight;
        $this->minImageWidth = $minWidth;
        $this->minImageHeight = $minHeight;
        return $this;
    }

    /**
     * Preset for image uploads only
     */
    public function imagesOnly(int $maxSize = 5242880): self // 5MB default for images
    {
        $this->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $this->setAllowedMimeTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
        $this->setMaxSize($maxSize);
        $this->validateImageContent = true;
        return $this;
    }

    /**
     * Upload a file
     */
    public function upload(UploadedFile $file, ?string $customName = null): array
    {
        // Validate file
        $this->validate($file);

        // Generate filename
        if ($customName) {
            // Sanitize custom name
            $filename = $this->sanitizeFilename($customName);
            $extension = $file->getClientExtension();

            // Ensure extension is present
            if (!str_ends_with($filename, '.' . $extension)) {
                $filename .= '.' . $extension;
            }
        } else {
            $filename = $this->generateFilename($file);
        }

        // Create subdirectory by date (optional organization)
        $subdir = date('Y/m/d');
        $fullPath = $this->uploadPath . '/' . $subdir;

        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                throw new \RuntimeException("Failed to create subdirectory: {$fullPath}");
            }
        }

        $targetPath = $fullPath . '/' . $filename;

        // Check if file already exists and generate unique name if needed
        if (file_exists($targetPath)) {
            $filename = $this->generateUniqueFilename($fullPath, $filename);
            $targetPath = $fullPath . '/' . $filename;
        }

        // Move file
        try {
            $file->moveTo($targetPath);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to move uploaded file: ' . $e->getMessage());
        }

        // Verify file was saved correctly
        if (!file_exists($targetPath)) {
            throw new \RuntimeException('File was not saved correctly');
        }

        // Get final file size (verify it matches)
        $actualSize = filesize($targetPath);

        return [
            'name' => $filename,
            'original_name' => $file->getClientFilename(),
            'path' => $targetPath,
            'url' => '/uploads/' . $subdir . '/' . $filename,
            'relative_path' => $subdir . '/' . $filename,
            'size' => $actualSize,
            'type' => $file->getActualMediaType() ?? $file->getClientMediaType(),
            'extension' => $file->getClientExtension(),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];
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
                }
            } catch (\Exception $e) {
                $errors[$key] = [
                    'file' => $file->getClientFilename(),
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
            'success_count' => count($uploaded),
            'error_count' => count($errors)
        ];
    }

    /**
     * Validate uploaded file
     */
    private function validate(UploadedFile $file): void
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new \RuntimeException('Invalid file upload: ' . $file->getErrorMessage());
        }

        // Verify file was actually uploaded via HTTP POST
        if (!$file->isUploaded()) {
            throw new \RuntimeException('Security: File was not uploaded via HTTP POST');
        }

        // Check for suspicious double extensions
        if ($this->blockDoubleExtensions && $file->hasSuspiciousExtension()) {
            throw new \RuntimeException('Security: File has suspicious double extension');
        }

        // Check file size
        if ($file->getSize() > $this->maxSize) {
            throw new \RuntimeException(
                'File size (' . $this->formatBytes($file->getSize()) . ') exceeds maximum allowed: ' .
                    $this->formatBytes($this->maxSize)
            );
        }

        if ($file->getSize() < $this->minSize) {
            throw new \RuntimeException(
                'File size is below minimum required: ' .
                    $this->formatBytes($this->minSize)
            );
        }

        // Check for empty file
        if ($file->getSize() === 0) {
            throw new \RuntimeException('File is empty (0 bytes)');
        }

        // Check extension
        if (!empty($this->allowedExtensions)) {
            $extension = $file->getClientExtension();

            if (!in_array($extension, $this->allowedExtensions)) {
                throw new \RuntimeException(
                    'File type not allowed. Allowed types: ' .
                        implode(', ', $this->allowedExtensions)
                );
            }
        }

        // Check actual MIME type (more secure than client-provided type)
        if ($this->checkActualMimeType && !empty($this->allowedMimeTypes)) {
            $actualMimeType = $file->getActualMediaType();

            if (!in_array($actualMimeType, $this->allowedMimeTypes)) {
                throw new \RuntimeException(
                    'File MIME type not allowed. File appears to be: ' . $actualMimeType
                );
            }
        }

        // Additional validation for images
        if ($file->isImage() && $this->validateImageContent) {
            $this->validateImage($file);
        }
    }

    /**
     * Validate image file
     */
    private function validateImage(UploadedFile $file): void
    {
        // Verify file is actually an image
        if (!$file->isActualImage()) {
            throw new \RuntimeException('File is not a valid image despite having image extension');
        }

        // Get image dimensions
        $dimensions = $file->getImageDimensions();

        if ($dimensions === null) {
            throw new \RuntimeException('Unable to read image dimensions');
        }

        // Check maximum dimensions
        if ($this->maxImageWidth && $dimensions['width'] > $this->maxImageWidth) {
            throw new \RuntimeException(
                "Image width ({$dimensions['width']}px) exceeds maximum allowed: {$this->maxImageWidth}px"
            );
        }

        if ($this->maxImageHeight && $dimensions['height'] > $this->maxImageHeight) {
            throw new \RuntimeException(
                "Image height ({$dimensions['height']}px) exceeds maximum allowed: {$this->maxImageHeight}px"
            );
        }

        // Check minimum dimensions
        if ($this->minImageWidth && $dimensions['width'] < $this->minImageWidth) {
            throw new \RuntimeException(
                "Image width ({$dimensions['width']}px) is below minimum required: {$this->minImageWidth}px"
            );
        }

        if ($this->minImageHeight && $dimensions['height'] < $this->minImageHeight) {
            throw new \RuntimeException(
                "Image height ({$dimensions['height']}px) is below minimum required: {$this->minImageHeight}px"
            );
        }

        // Additional security check: try to re-encode the image
        // This helps detect malicious files disguised as images
        $this->verifyImageIntegrity($file->getTempPath(), $dimensions['type']);
    }

    /**
     * Verify image integrity by attempting to re-encode it
     */
    private function verifyImageIntegrity(string $path, int $imageType): void
    {
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
                    $img = @imagecreatefromwebp($path);
                    break;
                default:
                    return; // Skip verification for unsupported types
            }

            if ($img === false) {
                throw new \RuntimeException('Failed to process image: file may be corrupted or malicious');
            }

            imagedestroy($img);
        } catch (\Exception $e) {
            throw new \RuntimeException('Image verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove path information
        $filename = basename($filename);

        // Get name and extension
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Remove any potentially dangerous characters
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = trim($name, '_');

        // If name is empty after sanitization, use a default
        if (empty($name)) {
            $name = 'file_' . uniqid();
        }

        // Limit length
        if (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }

        return $name . ($extension ? '.' . $extension : '');
    }

    /**
     * Generate unique filename
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientExtension();

        if ($this->generateUniqueName) {
            // Generate truly unique name
            return uniqid('file_', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        }

        // Use safe version of original filename
        return $file->getSafeFilename();
    }

    /**
     * Generate unique filename if file exists
     */
    private function generateUniqueFilename(string $directory, string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 1;

        while (file_exists($directory . '/' . $filename)) {
            $filename = $name . '_' . $counter . '.' . $extension;
            $counter++;

            // Prevent infinite loop
            if ($counter > 1000) {
                $filename = uniqid('file_', true) . '.' . $extension;
                break;
            }
        }

        return $filename;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete uploaded file
     */
    public function delete(string $path): bool
    {
        if (file_exists($path) && is_file($path)) {
            return @unlink($path);
        }

        return false;
    }

    /**
     * Get upload path
     */
    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }

    /**
     * Get max upload size from PHP configuration
     */
    public static function getMaxUploadSize(): int
    {
        $maxUpload = self::parseSize(ini_get('upload_max_filesize'));
        $maxPost = self::parseSize(ini_get('post_max_size'));
        $memoryLimit = self::parseSize(ini_get('memory_limit'));

        return min($maxUpload, $maxPost, $memoryLimit);
    }

    /**
     * Parse size string to bytes
     */
    private static function parseSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
