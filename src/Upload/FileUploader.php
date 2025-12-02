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
    private array $uploadedHashes = [];
    private bool $blockDangerousExtensions = true;
    private bool $blockDoubleExtensions = true;
    private bool $allowSvg = false;
    private LoggerInterface $logger;
    private int $maxRetries = 10;
    private array $uploadCounts = [];
    private ?int $maxFilesPerUpload = null;
    private ?int $maxUploadSize = null;
    private int $maxUploadsPerMinute = 10;
    private bool $createSecurityFiles = true;
    private bool $securityFilesCreated = false;
    private array $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    private bool $stripMetadata = false;
    private ?string $cdnUrl = null;
    private array $imageCompression = [
        'jpeg_quality' => 85,
        'png_compression' => 8,
        'webp_quality' => 85
    ];
    private string $baseUrl = '/uploads';
    private bool $preserveOriginalName = false;
    private array $thumbnailSizes = [];

    public function __construct(?string $uploadPath = null, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->uploadPath = $uploadPath ?? $this->getDefaultUploadPath();
        $this->uploadPath = $this->normalizePath($this->uploadPath);
        $this->ensureDirectoryExists($this->uploadPath);
    }

    private function getDefaultUploadPath(): string
    {
        // Try multiple common locations
        $candidates = [
            defined('BASE_PATH') ? rtrim(constant('BASE_PATH'), '/\\') . '/storage/uploads' : null,
            defined('UPLOAD_PATH') ? constant('UPLOAD_PATH') : null,
            $_SERVER['DOCUMENT_ROOT'] . '/uploads',
            dirname($_SERVER['SCRIPT_FILENAME']) . '/uploads',
            sys_get_temp_dir() . '/uploads', // Fallback
        ];

        foreach ($candidates as $path) {
            if ($path && $this->isWritableOrCreatable($path)) {
                return $path;
            }
        }

        // Last resort: use temp directory
        return sys_get_temp_dir() . '/uploads';
    }

    private function isWritableOrCreatable(string $path): bool
    {
        if (is_dir($path)) {
            return is_writable($path);
        }

        $parent = dirname($path);
        return is_dir($parent) && is_writable($parent);
    }

    /**
     * Normalize path to prevent traversal issues
     */
    private function normalizePath(string $path): string
    {
        // Convert to absolute path
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Get real path if exists, otherwise normalize manually
        if (file_exists($path)) {
            $realPath = realpath($path);
            if ($realPath !== false) {
                return $realPath;
            }
        }

        // Manual normalization for non-existent paths
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

    private function ensureDirectoryExists(string $path): void
    {
        clearstatcache(true, $path);

        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true) && !is_dir($path)) {
                throw new RuntimeException("Failed to create upload directory: {$path}");
            }
            $this->logger->info("Created upload directory", ['path' => $path]);
        }

        if (!is_writable($path)) {
            throw new RuntimeException("Upload directory is not writable: {$path}");
        }

        if ($this->createSecurityFiles && !$this->securityFilesCreated) {
            $this->createSecurityFiles($path);
            $this->securityFilesCreated = true;
        }
    }

    private function createSecurityFiles(string $path): void
    {
        $markerFile = $path . DIRECTORY_SEPARATOR . '.upload_security';
        if (file_exists($markerFile)) {
            $age = time() - filemtime($markerFile);
            if ($age < 86400) { // Less than 24 hours
                return;
            }
        }

        // .htaccess for Apache
        $htaccess = $path . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Security configuration for upload directory\n";
            $content .= "Options -Indexes -ExecCGI\n";
            $content .= "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps .pht\n";
            $content .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|pht|phar|pl|py|cgi|sh)$\">\n";
            $content .= "    Require all denied\n";
            $content .= "</FilesMatch>\n";
            $content .= "<IfModule mod_headers.c>\n";
            $content .= "    Header set X-Content-Type-Options \"nosniff\"\n";
            $content .= "    Header set X-Frame-Options \"DENY\"\n";
            $content .= "</IfModule>\n";
            @file_put_contents($htaccess, $content);
        }

        // web.config for IIS
        $webConfig = $path . DIRECTORY_SEPARATOR . 'web.config';
        if (!file_exists($webConfig)) {
            $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $content .= '<configuration>' . "\n";
            $content .= '  <system.webServer>' . "\n";
            $content .= '    <handlers>' . "\n";
            $content .= '      <clear />' . "\n";
            $content .= '      <add name="StaticFile" path="*" verb="*" modules="StaticFileModule" />' . "\n";
            $content .= '    </handlers>' . "\n";
            $content .= '  </system.webServer>' . "\n";
            $content .= '</configuration>';
            @file_put_contents($webConfig, $content);
        }

        // index.php with access denied
        $index = $path . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($index)) {
            $content = "<?php\nhttp_response_code(403);\nexit('Directory access is forbidden.');\n";
            @file_put_contents($index, $content);
        }

        @touch($markerFile);
    }

    public function disableSecurityFiles(): self
    {
        $this->createSecurityFiles = false;
        return $this;
    }

    /**
     * Set custom upload path - now with proper validation
     */
    public function setUploadPath(string $path): self
    {
        $path = $this->normalizePath($path);

        // Security: Ensure path is not attempting traversal
        if (strpos($path, '..') !== false) {
            throw new InvalidArgumentException('Path cannot contain ".."');
        }

        $this->uploadPath = $path;
        $this->securityFilesCreated = false; // Reset flag for new path
        $this->ensureDirectoryExists($this->uploadPath);

        return $this;
    }

    /**
     * Set base URL for file access
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * Quick setup for public folder uploads
     */
    public function usePublicFolder(string $subfolder = 'uploads'): self
    {
        $publicPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . trim($subfolder, '/\\');
        $this->setUploadPath($publicPath);
        $this->setBaseUrl('/' . trim($subfolder, '/'));
        return $this;
    }

    /**
     * Quick setup for storage folder uploads (outside public)
     */
    public function useStorageFolder(string $subfolder = 'uploads'): self
    {
        $storagePath = dirname($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . trim($subfolder, '/\\');
        $this->setUploadPath($storagePath);
        // These files won't be directly accessible via URL
        $this->setBaseUrl('/storage/' . trim($subfolder, '/'));
        return $this;
    }

    public function setRateLimit(int $maxUploadsPerMinute): self
    {
        if ($maxUploadsPerMinute < 1) {
            throw new InvalidArgumentException('Rate limit must be at least 1');
        }
        $this->maxUploadsPerMinute = $maxUploadsPerMinute;
        return $this;
    }

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

    public function setMaxUploadSize(int $bytes): self
    {
        if ($bytes <= 0) {
            throw new InvalidArgumentException('Max upload size must be positive');
        }
        $this->maxUploadSize = $bytes;
        return $this;
    }

    public function setMaxFilesPerUpload(int $max): self
    {
        if ($max < 1) {
            throw new InvalidArgumentException('Max files must be at least 1');
        }
        $this->maxFilesPerUpload = $max;
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
            'rtf'
        ]);
        $this->setAllowedMimeTypes([
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'application/rtf',
            'text/plain',
            'text/csv'
        ]);
        $this->setMaxSize($maxSize);
        $this->validateImageContent = false;
        return $this;
    }

    /**
     * Configure uploader for audio files only
     * 
     * @param int $maxSize Maximum file size in bytes (default: 50MB)
     * @return self
     */
    public function audiosOnly(int $maxSize = 52428800): self
    {
        $this->setAllowedExtensions([
            'mp3',  // MPEG Audio
            'wav',  // Waveform Audio
            'flac', // Free Lossless Audio Codec
            'm4a',  // MPEG-4 Audio
            'aac',  // Advanced Audio Coding
            'ogg',  // Ogg Vorbis
            'oga',  // Ogg Audio
            'wma',  // Windows Media Audio
            'opus', // Opus Audio
            'aiff', // Audio Interchange File Format
            'aif',  // AIFF short form
            'ape',  // Monkey's Audio
            'alac', // Apple Lossless
            'wv',   // WavPack
            'mid',  // MIDI
            'midi', // MIDI
            'ra',   // RealAudio
            'mp2',  // MPEG Audio Layer 2
            'mpa'   // MPEG Audio
        ]);

        $this->setAllowedMimeTypes([
            // MP3
            'audio/mpeg',
            'audio/mp3',
            'audio/mpeg3',
            'audio/x-mpeg-3',

            // WAV
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/x-pn-wav',

            // FLAC
            'audio/flac',
            'audio/x-flac',

            // M4A/AAC
            'audio/mp4',
            'audio/x-m4a',
            'audio/aac',
            'audio/aacp',
            'audio/x-aac',

            // OGG
            'audio/ogg',
            'audio/x-ogg',
            'application/ogg',

            // WMA
            'audio/x-ms-wma',
            'audio/wma',

            // OPUS
            'audio/opus',
            'audio/x-opus+ogg',

            // AIFF
            'audio/aiff',
            'audio/x-aiff',

            // APE
            'audio/ape',
            'audio/x-ape',

            // ALAC
            'audio/alac',
            'audio/x-alac',

            // WavPack
            'audio/x-wavpack',

            // MIDI
            'audio/midi',
            'audio/x-midi',
            'audio/mid',

            // RealAudio
            'audio/x-pn-realaudio',
            'audio/x-realaudio',

            // MP2
            'audio/mp2',
            'audio/x-mp2'
        ]);

        $this->setMaxSize($maxSize);
        $this->validateImageContent = false;
        $this->checkActualMimeType = true;

        // Audio-specific security checks
        $this->blockDangerousExtensions = true;
        $this->blockDoubleExtensions = true;

        return $this;
    }

    /**
     * Configure uploader for video files only
     * 
     * @param int $maxSize Maximum file size in bytes (default: 500MB)
     * @return self
     */
    public function videosOnly(int $maxSize = 524288000): self
    {
        $this->setAllowedExtensions([
            'mp4',  // MPEG-4 Video
            'avi',  // Audio Video Interleave
            'mov',  // QuickTime Movie
            'wmv',  // Windows Media Video
            'flv',  // Flash Video
            'webm', // WebM Video
            'mkv',  // Matroska Video
            'mpeg', // MPEG Video
            'mpg',  // MPEG short form
            'm4v',  // iTunes Video
            '3gp',  // 3GPP Video
            '3g2',  // 3GPP2 Video
            'ogv',  // Ogg Video
            'mxf',  // Material Exchange Format
            'vob',  // DVD Video Object
            'ts',   // MPEG Transport Stream
            'm2ts', // Blu-ray BDAV Video
            'mts',  // AVCHD Video
            'divx', // DivX Video
            'xvid', // Xvid Video
            'rm',   // RealMedia
            'rmvb', // RealMedia Variable Bitrate
            'asf',  // Advanced Systems Format
            'f4v',  // Flash MP4 Video
            'swf'   // Shockwave Flash (if needed)
        ]);

        $this->setAllowedMimeTypes([
            // MP4
            'video/mp4',
            'video/mpeg4',
            'application/mp4',

            // AVI
            'video/avi',
            'video/x-msvideo',
            'video/msvideo',

            // MOV
            'video/quicktime',
            'video/x-quicktime',

            // WMV
            'video/x-ms-wmv',
            'video/x-ms-asf',
            'video/x-ms-asf-plugin',

            // FLV
            'video/x-flv',
            'video/flv',
            'application/x-shockwave-flash',

            // WebM
            'video/webm',

            // MKV
            'video/x-matroska',
            'video/matroska',

            // MPEG
            'video/mpeg',
            'video/mpg',
            'video/x-mpeg',

            // M4V
            'video/x-m4v',

            // 3GP/3G2
            'video/3gpp',
            'video/3gpp2',
            'video/3gp',
            'video/3g2',

            // OGG
            'video/ogg',
            'video/x-ogg',
            'application/ogg',

            // MXF
            'application/mxf',

            // VOB
            'video/dvd',
            'video/x-ms-vob',

            // TS
            'video/mp2t',
            'video/mpeg2',

            // DivX/Xvid
            'video/divx',
            'video/x-divx',
            'video/xvid',
            'video/x-xvid',

            // RealMedia
            'video/vnd.rn-realvideo',
            'application/vnd.rn-realmedia',

            // ASF
            'video/x-ms-asf',
            'application/vnd.ms-asf',

            // F4V
            'video/x-f4v'
        ]);

        $this->setMaxSize($maxSize);
        $this->validateImageContent = false;
        $this->checkActualMimeType = true;

        // Video-specific security checks
        $this->blockDangerousExtensions = true;
        $this->blockDoubleExtensions = true;

        return $this;
    }

    /**
     * Configure uploader for streaming video formats (web-optimized)
     * 
     * @param int $maxSize Maximum file size in bytes (default: 200MB)
     * @return self
     */
    public function streamingVideosOnly(int $maxSize = 209715200): self
    {
        $this->setAllowedExtensions(['mp4', 'webm', 'm3u8', 'mpd']);

        $this->setAllowedMimeTypes([
            'video/mp4',
            'video/webm',
            'application/x-mpegURL',
            'application/vnd.apple.mpegurl',
            'application/dash+xml'
        ]);

        $this->setMaxSize($maxSize);
        $this->validateImageContent = false;
        $this->checkActualMimeType = true;

        return $this;
    }

    /**
     * Configure uploader for lossless audio formats (high quality)
     * 
     * @param int $maxSize Maximum file size in bytes (default: 100MB)
     * @return self
     */
    public function losslessAudiosOnly(int $maxSize = 104857600): self
    {
        $this->setAllowedExtensions(['flac', 'wav', 'aiff', 'aif', 'alac', 'ape', 'wv']);

        $this->setAllowedMimeTypes([
            'audio/flac',
            'audio/x-flac',
            'audio/wav',
            'audio/x-wav',
            'audio/aiff',
            'audio/x-aiff',
            'audio/alac',
            'audio/x-alac',
            'audio/ape',
            'audio/x-ape',
            'audio/x-wavpack'
        ]);

        $this->setMaxSize($maxSize);
        $this->validateImageContent = false;
        $this->checkActualMimeType = true;

        return $this;
    }

    /**
     * Configure uploader for compressed/streaming audio formats
     * 
     * @param int $maxSize Maximum file size in bytes (default: 20MB)
     * @return self
     */
    public function compressedAudiosOnly(int $maxSize = 20971520): self
    {
        $this->setAllowedExtensions(['mp3', 'm4a', 'aac', 'ogg', 'opus', 'wma']);

        $this->setAllowedMimeTypes([
            'audio/mpeg',
            'audio/mp3',
            'audio/mp4',
            'audio/x-m4a',
            'audio/aac',
            'audio/ogg',
            'audio/opus',
            'audio/x-ms-wma'
        ]);

        $this->setMaxSize($maxSize);
        $this->validateImageContent = false;
        $this->checkActualMimeType = true;

        return $this;
    }

    private function cleanupRateLimitCache(): void
    {
        $currentMinute = '_' . (int) (time() / 60);

        foreach (array_keys($this->uploadCounts) as $key) {
            if (!str_ends_with($key, $currentMinute)) {
                unset($this->uploadCounts[$key]);
            }
        }
    }

    private function checkRateLimit(string $identifier): void
    {
        $this->cleanupRateLimitCache();

        $minute = (int) (time() / 60);
        $key = $identifier . '_' . $minute;

        if (!isset($this->uploadCounts[$key])) {
            $this->uploadCounts[$key] = 0;
            $currentMinute = '_' . $minute;
            foreach (array_keys($this->uploadCounts) as $k) {
                if (!str_ends_with($k, $currentMinute)) {
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

    public function upload(UploadedFile $file, ?string $customName = null, ?string $userIdentifier = null): array
    {
        $startTime = microtime(true);

        try {
            if ($userIdentifier) {
                $this->checkRateLimit($userIdentifier);
            }

            $this->validate($file);

            if ($this->preventDuplicates && $existingFile = $this->checkDuplicate($file)) {
                $this->logger->info('Duplicate file detected', [
                    'hash' => $existingFile['hash'] ?? 'unknown',
                    'original' => $file->getClientFilename()
                ]);
                return array_merge($existingFile, ['duplicate' => true]);
            }

            $filename = $this->generateFilename($file, $customName);

            $targetDir = $this->organizeByDate
                ? $this->uploadPath . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR . date('d')
                : $this->uploadPath;

            $this->ensureDirectoryExists($targetDir);

            $targetPath = $this->generateUniquePathAtomic($targetDir, $filename);
            $filename = basename($targetPath);

            try {
                $file->moveTo($targetPath);
            } catch (\Exception $e) {
                $this->logger->error('Failed to move file', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientFilename()
                ]);
                throw new RuntimeException('Failed to move uploaded file: ' . $e->getMessage());
            }

            if (!file_exists($targetPath)) {
                throw new RuntimeException('File was not saved correctly');
            }

            $this->postUploadSecurityCheck($targetPath);

            $result = $this->buildFileInfo($file, $targetPath, $filename);

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

    private function generateUniquePathAtomic(string $directory, string $filename): string
    {
        $info = pathinfo($filename);
        $name = $info['filename'];
        $extension = $info['extension'] ?? '';

        $targetPath = $directory . DIRECTORY_SEPARATOR . $filename;
        $lockFile = $targetPath . '.lock';

        $attempts = 0;
        while ($attempts < $this->maxRetries) {
            $fp = @fopen($lockFile, 'x');
            if ($fp !== false) {
                fclose($fp);

                if (!file_exists($targetPath)) {
                    @unlink($lockFile);
                    return $targetPath;
                }

                @unlink($lockFile);
            }

            $randomSuffix = bin2hex(random_bytes(8));
            $filename = sprintf(
                '%s_%s%s',
                $name,
                $randomSuffix,
                $extension ? '.' . $extension : ''
            );
            $targetPath = $directory . DIRECTORY_SEPARATOR . $filename;
            $lockFile = $targetPath . '.lock';

            $attempts++;

            if ($attempts > 3) {
                usleep(10000);
            }
        }

        $filename = sprintf(
            'file_%d_%s%s',
            time(),
            bin2hex(random_bytes(16)),
            $extension ? '.' . $extension : ''
        );

        return $directory . DIRECTORY_SEPARATOR . $filename;
    }

    private function postUploadSecurityCheck(string $filepath): void
    {
        $perms = fileperms($filepath);
        if ($perms === false || ($perms & 0111)) {
            @unlink($filepath);
            throw new RuntimeException('Security violation: Uploaded file has execute permissions');
        }

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
        if (isset($this->maxFilesPerUpload) && count($files) > $this->maxFilesPerUpload) {
            throw new RuntimeException(
                "Cannot upload more than {$this->maxFilesPerUpload} files at once"
            );
        }

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

        $this->validateFileSignature($file);
        $this->checkForMaliciousContent($file);
    }

    private function validateFileSignature(UploadedFile $file): void
    {
        if (!$this->checkActualMimeType || !function_exists('finfo_open')) {
            return;
        }

        $signatures = [
            'image/jpeg' => "\xFF\xD8\xFF",
            'image/png' => "\x89PNG\r\n\x1A\n",
            'image/gif' => 'GIF',
            'application/pdf' => '%PDF',
        ];

        $content = $file->getContents();
        $detectedMime = $file->getActualMediaType();

        foreach ($signatures as $mime => $signature) {
            if ($detectedMime === $mime && strpos($content, $signature) !== 0) {
                throw new RuntimeException("File signature does not match MIME type: {$detectedMime}");
            }
        }
    }

    private function checkForMaliciousContent(UploadedFile $file): void
    {
        // Check for PHP tags in any file type
        $content = substr($file->getContents(), 0, 8192); // Check first 8KB

        $patterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<script/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new RuntimeException('Security violation: Malicious content detected in file');
            }
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

            if (is_resource($img)) {
                imagedestroy($img);
            }
        } catch (\Exception $e) {
            if ($img !== null && $img !== false && is_resource($img)) {
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
            return sprintf(
                '%s.%s',
                bin2hex(random_bytes(20)),
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
        if ($actualSize === false) {
            $actualSize = $file->getSize();
        }

        // Build relative path from upload path
        $realTargetPath = realpath($targetPath);
        $realUploadPath = realpath($this->uploadPath);

        $relativePath = '';
        if ($realTargetPath !== false && $realUploadPath !== false) {
            $relativePath = str_replace(
                $realUploadPath . DIRECTORY_SEPARATOR,
                '',
                $realTargetPath
            );
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        } else {
            $relativePath = $filename;
        }

        $info = [
            'name' => $filename,
            'original_name' => $file->getClientFilename(),
            'path' => $targetPath,
            'relative_path' => $relativePath,
            'url' => $this->cdnUrl
                ? rtrim($this->cdnUrl, '/') . '/' . $relativePath
                : $this->baseUrl . '/' . $relativePath,
            'size' => $actualSize,
            'type' => $file->getMimeType(),
            'extension' => $file->getClientExtension(),
            'uploaded_at' => date('Y-m-d H:i:s'),
            'uploaded_timestamp' => time(),
        ];

        if ($this->preventDuplicates) {
            $hash = hash_file('sha256', $targetPath);
            if ($hash !== false) {
                $info['hash'] = $hash;
            }
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

    /**
     * Delete a file - Fixed path traversal issue
     */
    public function delete(string $path): bool
    {
        // Normalize the input path
        $path = $this->normalizePath($path);

        // If it's a relative path, make it absolute
        if (!$this->isAbsolutePath($path)) {
            $path = $this->uploadPath . DIRECTORY_SEPARATOR . $path;
        }

        if (!file_exists($path) || !is_file($path)) {
            return false;
        }

        $realPath = realpath($path);
        $realUploadPath = realpath($this->uploadPath);

        if ($realPath === false || $realUploadPath === false) {
            $this->logger->warning('Invalid path for deletion', [
                'path' => $path,
                'real_path' => $realPath,
                'upload_path' => $this->uploadPath
            ]);
            return false;
        }

        // Security check: ensure file is within upload directory
        if (strpos($realPath, $realUploadPath) !== 0) {
            $this->logger->error('Delete attempt outside upload directory', [
                'path' => $path,
                'real_path' => $realPath,
                'upload_path' => $realUploadPath
            ]);
            throw new RuntimeException('Security violation: Cannot delete files outside upload directory');
        }

        $result = @unlink($realPath);

        if ($result) {
            $this->logger->info('File deleted', ['path' => $realPath]);
        }

        return $result;
    }

    /**
     * Check if path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        // Windows: C:\ or C:/
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return true;
        }

        // Unix: /
        if (strpos($path, DIRECTORY_SEPARATOR) === 0 || strpos($path, '/') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Get full absolute path from relative path - Fixed
     */
    public function getFullPath(string $relativePath): string
    {
        $relativePath = $this->normalizePath($relativePath);

        // Remove leading slashes
        $relativePath = ltrim($relativePath, '/\\');

        $fullPath = $this->uploadPath . DIRECTORY_SEPARATOR . $relativePath;
        $fullPath = $this->normalizePath($fullPath);

        // Security check
        if (!file_exists($fullPath)) {
            throw new RuntimeException('File does not exist: ' . $relativePath);
        }

        $realFullPath = realpath($fullPath);
        $realUploadPath = realpath($this->uploadPath);

        if ($realFullPath === false || $realUploadPath === false) {
            throw new RuntimeException('Invalid path');
        }

        if (strpos($realFullPath, $realUploadPath) !== 0) {
            throw new RuntimeException('Security violation: Path traversal detected');
        }

        return $realFullPath;
    }

    /**
     * Get relative path from full path - Fixed
     */
    public function getRelativePath(string $fullPath): string
    {
        $fullPath = $this->normalizePath($fullPath);

        $realFullPath = realpath($fullPath);
        $realUploadPath = realpath($this->uploadPath);

        if ($realFullPath === false || $realUploadPath === false) {
            throw new RuntimeException('Invalid path');
        }

        if (strpos($realFullPath, $realUploadPath) !== 0) {
            throw new RuntimeException('Security violation: Path outside upload directory');
        }

        $relativePath = substr($realFullPath, strlen($realUploadPath));
        return ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/');
    }

    public function stripMetadata(bool $strip = true): self
    {
        $this->stripMetadata = $strip;
        return $this;
    }

    public function setCdnUrl(?string $cdnUrl): self
    {
        $this->cdnUrl = $cdnUrl ? rtrim($cdnUrl, '/') : null;
        return $this;
    }

    public function setImageCompression(array $compression): self
    {
        $this->imageCompression = array_merge($this->imageCompression, $compression);
        return $this;
    }

    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
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

        $size = trim((string) $size);
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
