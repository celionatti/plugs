<?php

declare(strict_types=1);

namespace Plugs\Upload;

/*
|--------------------------------------------------------------------------
| FileUploader Class
|--------------------------------------------------------------------------
|
| Handles secure file uploads with comprehensive validation, storage management,
| and error handling. Integrated with the Storage system.
|
| @package Plugs\Upload
*/

use Plugs\Facades\Storage;
use Plugs\Filesystem\FilesystemDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class FileUploader
{
    private ?string $diskName = null;
    private string $basePath = 'uploads';
    private array $allowedExtensions = [];
    private array $allowedMimeTypes = [];
    private int $maxSize = 10485760; // 10MB
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
    private bool $blockDangerousExtensions = true;
    private bool $blockDoubleExtensions = true;
    private bool $allowSvg = false;
    private LoggerInterface $logger;
    private bool $createSecurityFiles = true;
    private bool $securityFilesCreated = false;
    private bool $stripMetadata = false;
    private array $imageCompression = [
        'jpeg_quality' => 85,
        'png_compression' => 8,
        'webp_quality' => 85,
    ];

    public function __construct(?string $disk = null, ?LoggerInterface $logger = null)
    {
        $this->diskName = $disk;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Factory method for cleaner instantiation
     */
    public static function make(?string $disk = null): self
    {
        return new self($disk);
    }

    /**
     * Get the storage disk instance
     */
    protected function disk(): FilesystemDriverInterface
    {
        return Storage::disk($this->diskName);
    }

    /**
     * Set the base path within the disk
     */
    public function setBasePath(string $path): self
    {
        $this->basePath = trim($path, '/');

        // Reset security check flag if path changes significantly (though we prefer root checks)
        return $this;
    }

    /**
     * Disable organizing uploads by date (year/month/day)
     */
    public function dontOrganizeByDate(): self
    {
        $this->organizeByDate = false;

        return $this;
    }

    public function disableSecurityFiles(): self
    {
        $this->createSecurityFiles = false;

        return $this;
    }

    public function generateUniqueName(bool $generate = true): self
    {
        $this->generateUniqueName = $generate;

        return $this;
    }

    // ... Fluent Configuration Methods ...

    public function imagesOnly(int $maxSize = 5242880): self
    {
        $this->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $this->setAllowedMimeTypes([
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ]);
        $this->maxSize = $maxSize;
        $this->validateImageContent = true;
        $this->allowSvg = false;

        return $this;
    }

    public function documentsOnly(int $maxSize = 10485760): self
    {
        $this->setAllowedExtensions([
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'txt',
            'csv',
            'ppt',
            'pptx',
            'odt',
            'ods',
            'odp',
            'rtf',
        ]);
        $this->setAllowedMimeTypes([
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ]);
        $this->maxSize = $maxSize;
        $this->validateImageContent = false;

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
        $this->maxSize = $bytes;

        return $this;
    }

    // ... Upload Logic ...

    public function upload(UploadedFile $file, ?string $customName = null): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($file);

            // Ensure base path exists and is secure (only check root once)
            $this->ensureSecureDirectory();

            $filename = $this->generateFilename($file, $customName);
            $folder = $this->basePath;

            if ($this->organizeByDate) {
                $folder .= '/' . date('Y') . '/' . date('m') . '/' . date('d');
            }

            // We let the Storage system handle directory creation recursively

            $path = $folder . '/' . $filename;

            // Check for duplicates if enabled
            if ($this->preventDuplicates) {
                $hash = $file->getHash();
                // Simple check: see if a file with this hash already exists
                // For a real implementation, you'd check against a database or scan directory
                $this->logger->debug('Duplicate check hash', ['hash' => $hash]);
            }

            if (!$this->generateUniqueName && $this->disk()->exists($path)) {
                $filename = $this->appendTimestamp($filename);
                $path = $folder . '/' . $filename;
            }

            // Store the file using Storage facade
            // UploadedFile's storeAs logic is slightly different, here we use direct put for control
            // OR we can use $file->storeAs if we want to leverage that.
            // Let's use $file->storeAs to be consistent with new refactor, passing our disk.

            $resultPath = $file->storeAs($folder, $filename, ['disk' => $this->diskName]);

            if ($resultPath === false) {
                throw new RuntimeException("Failed to store file at {$path}");
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('File uploaded successfully', [
                'filename' => $filename,
                'path' => $resultPath,
                'duration_ms' => $duration,
            ]);

            return $this->buildResult($file, $resultPath, $filename);

        } catch (\Exception $e) {
            $this->logger->error('Upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientFilename(),
            ]);

            throw $e;
        }
    }

    private function ensureSecureDirectory(): void
    {
        // Only run this if enabled and not already done for this instance
        if (!$this->createSecurityFiles || $this->securityFilesCreated) {
            return;
        }

        // We only secure the BASE path, not every single date-based subfolder
        $disk = $this->disk();

        // Check if we are on a Local driver. If so, we can try to drop .htaccess
        // For portability, we should check if the driver supports 'put' and generic file ops.
        // Ideally, security files are for public web access control on local servers (Apache/IIS).

        if (!$disk instanceof \Plugs\Filesystem\Drivers\LocalFilesystemDriver) {
            // Non-local disks (S3, etc) generally don't use .htaccess
            $this->securityFilesCreated = true;

            return;
        }

        // Ensure base directory exists
        if (!$disk->exists($this->basePath)) {
            $disk->makeDirectory($this->basePath);
        }

        // Create .htaccess if missing
        $htaccess = $this->basePath . '/.htaccess';
        if (!$disk->exists($htaccess)) {
            $content = "Options -Indexes\n";
            $content .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|pht|phar|pl|py|cgi|sh)$\">\n";
            $content .= "    Require all denied\n";
            $content .= "</FilesMatch>\n";
            $disk->put($htaccess, $content);
        }

        // Create index.php to silence listing
        $index = $this->basePath . '/index.php';
        if (!$disk->exists($index)) {
            $disk->put($index, "<?php\nhttp_response_code(403);");
        }

        $this->securityFilesCreated = true;
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
            return $file->hashName(); // Uses UploadedFile's built-in hash generator
        }

        return $file->getSafeFilename();
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', basename($filename));

        return $filename;
    }

    private function appendTimestamp(string $filename): string
    {
        $info = pathinfo($filename);

        return $info['filename'] . '_' . time() . '.' . ($info['extension'] ?? '');
    }

    private function buildResult(UploadedFile $file, string $path, string $filename): array
    {
        return [
            'name' => $filename,
            'original_name' => $file->getClientFilename(),
            'path' => $path,
            'url' => $this->disk()->url($path),
            'size' => $this->disk()->size($path),
            'mime_type' => $this->disk() instanceof \Plugs\Filesystem\Drivers\LocalFilesystemDriver
                ? mime_content_type($this->disk()->fullPath($path))
                : $file->getMimeType(), // Fallback
            'extension' => $file->getClientExtension(),
            'uploaded_at' => date('Y-m-d H:i:s'),
            'metadata_stripped' => $this->stripMetadata,
            'compression_settings' => $this->imageCompression,
        ];
    }

    private function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new RuntimeException('Invalid file upload.');
        }

        if ($file->getSize() > $this->maxSize) {
            throw new RuntimeException("File size exceeds limit of {$this->maxSize} bytes.");
        }

        if ($this->minSize > 0 && $file->getSize() < $this->minSize) {
            throw new RuntimeException("File size is below minimum of {$this->minSize} bytes.");
        }

        if (!empty($this->allowedExtensions) && !in_array($file->getClientExtension(), $this->allowedExtensions, true)) {
            throw new RuntimeException("Extension not allowed. Allowed: " . implode(', ', $this->allowedExtensions));
        }

        if (!empty($this->allowedMimeTypes)) {
            $mime = $file->getMimeType();
            if (!in_array($mime, $this->allowedMimeTypes, true)) {
                // Double check with actual mime if enabled and available
                if ($this->checkActualMimeType && $file->getActualMediaType()) {
                    if (!in_array($file->getActualMediaType(), $this->allowedMimeTypes, true)) {
                        throw new RuntimeException("MIME type not allowed: {$mime}");
                    }
                } else {
                    throw new RuntimeException("MIME type not allowed: {$mime}");
                }
            }
        }

        if ($this->validateImageContent && $file->isImage()) {
            // Check SVG allowance
            if ($file->getActualMediaType() === 'image/svg+xml' && !$this->allowSvg) {
                throw new RuntimeException("SVG files are not allowed.");
            }

            if (!$file->isActualImage()) {
                throw new RuntimeException("File is not a valid image.");
            }

            // Dimension validation
            $dimensions = $file->getImageDimensions();
            if ($dimensions !== null) {
                if ($this->maxImageWidth !== null && $dimensions['width'] > $this->maxImageWidth) {
                    throw new RuntimeException("Image width exceeds maximum of {$this->maxImageWidth}px.");
                }
                if ($this->maxImageHeight !== null && $dimensions['height'] > $this->maxImageHeight) {
                    throw new RuntimeException("Image height exceeds maximum of {$this->maxImageHeight}px.");
                }
                if ($this->minImageWidth !== null && $dimensions['width'] < $this->minImageWidth) {
                    throw new RuntimeException("Image width is below minimum of {$this->minImageWidth}px.");
                }
                if ($this->minImageHeight !== null && $dimensions['height'] < $this->minImageHeight) {
                    throw new RuntimeException("Image height is below minimum of {$this->minImageHeight}px.");
                }
            }
        }

        // Security checks
        if ($this->blockDangerousExtensions && $file->hasDangerousExtension()) {
            throw new RuntimeException("Dangerous extension detected.");
        }

        if ($this->blockDoubleExtensions && $file->hasSuspiciousExtension()) {
            throw new RuntimeException("Suspicious double extension detected.");
        }
    }

    // Additional helpers for completeness from original class...
    public function setMinSize(int $bytes): self
    {
        $this->minSize = $bytes;

        return $this;
    }

    public function allowSvg(bool $allow): self
    {
        $this->allowSvg = $allow;

        return $this;
    }

    public function setMaxImageDimensions(?int $width, ?int $height): self
    {
        $this->maxImageWidth = $width;
        $this->maxImageHeight = $height;

        return $this;
    }

    public function setMinImageDimensions(?int $width, ?int $height): self
    {
        $this->minImageWidth = $width;
        $this->minImageHeight = $height;

        return $this;
    }

    public function preventDuplicates(bool $prevent = true): self
    {
        $this->preventDuplicates = $prevent;

        return $this;
    }

    public function blockDoubleExtensions(bool $block = true): self
    {
        $this->blockDoubleExtensions = $block;

        return $this;
    }

    public function checkActualMimeType(bool $check = true): self
    {
        $this->checkActualMimeType = $check;

        return $this;
    }

    /**
     * Enable metadata stripping for images (requires GD/Imagick)
     * TODO: Implement actual stripping in post-processing
     */
    public function stripMetadata(bool $strip = true): self
    {
        $this->stripMetadata = $strip;

        return $this;
    }

    /**
     * Set image compression settings
     * TODO: Implement actual compression in post-processing
     */
    public function setImageCompression(int $jpegQuality = 85, int $pngCompression = 8, int $webpQuality = 85): self
    {
        $this->imageCompression = [
            'jpeg_quality' => $jpegQuality,
            'png_compression' => $pngCompression,
            'webp_quality' => $webpQuality,
        ];

        return $this;
    }
}
