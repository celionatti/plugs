<?php

declare(strict_types=1);

namespace Plugs\Upload;

/*
|--------------------------------------------------------------------------
| UploadedFile Class
|--------------------------------------------------------------------------
|
| This class represents an uploaded file and provides methods to access
| its properties and move it to a desired location.
*/

class UploadedFile
{
    private $name;
    private $type;
    private $tmpName;
    private $error;
    private $size;
    private $actualMimeType;

    public function __construct(array $file)
    {
        $this->name = $file['name'] ?? '';
        $this->type = $file['type'] ?? '';
        $this->tmpName = $file['tmp_name'] ?? '';
        $this->error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $this->size = $file['size'] ?? 0;

        // Get actual MIME type from file content (more secure)
        if ($this->isValid() && is_uploaded_file($this->tmpName)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $this->actualMimeType = finfo_file($finfo, $this->tmpName);
            finfo_close($finfo);
        }
    }

    /**
     * Get original filename
     */
    public function getClientFilename(): string
    {
        return $this->name;
    }

    /**
     * Get file extension
     */
    public function getClientExtension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Get MIME type from client
     */
    public function getClientMediaType(): string
    {
        return $this->type;
    }

    /**
     * Get actual MIME type (detected from file content)
     * More reliable than client-provided type
     */
    public function getActualMediaType(): ?string
    {
        return $this->actualMimeType;
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
     * Check if upload was successful
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && !empty($this->tmpName);
    }

    /**
     * Verify the file was actually uploaded via HTTP POST
     */
    public function isUploaded(): bool
    {
        return is_uploaded_file($this->tmpName);
    }

    /**
     * Get temporary file path
     */
    public function getTempPath(): string
    {
        return $this->tmpName;
    }

    /**
     * Check if file is an image based on extension
     */
    public function isImage(): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        return in_array($this->getClientExtension(), $imageExtensions);
    }

    /**
     * Verify file is actually an image by checking content
     * More reliable than extension check
     */
    public function isActualImage(): bool
    {
        if (!$this->isValid() || !$this->isUploaded()) {
            return false;
        }

        // Check MIME type
        $imageMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/x-ms-bmp'
        ];

        if (!in_array($this->actualMimeType, $imageMimeTypes)) {
            return false;
        }

        // Use getimagesize as additional verification
        $imageInfo = @getimagesize($this->tmpName);

        return $imageInfo !== false;
    }

    /**
     * Get image dimensions if file is an image
     */
    public function getImageDimensions(): ?array
    {
        if (!$this->isActualImage()) {
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
            'mime' => $imageInfo['mime'] ?? null
        ];
    }

    /**
     * Get file contents
     */
    public function getContents(): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Cannot read invalid upload');
        }

        if (!$this->isUploaded()) {
            throw new \RuntimeException('File was not uploaded via HTTP POST');
        }

        $contents = @file_get_contents($this->tmpName);

        if ($contents === false) {
            throw new \RuntimeException('Failed to read uploaded file');
        }

        return $contents;
    }

    /**
     * Move uploaded file to destination
     */
    public function moveTo(string $targetPath): bool
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Cannot move invalid upload: ' . $this->getErrorMessage());
        }

        if (!$this->isUploaded()) {
            throw new \RuntimeException('File was not uploaded via HTTP POST');
        }

        // Validate target path
        $realTargetPath = realpath(dirname($targetPath));
        if ($realTargetPath === false) {
            $directory = dirname($targetPath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory: {$directory}");
                }
            }
        }

        // Ensure filename is safe
        $filename = basename($targetPath);
        if (empty($filename) || $filename === '.' || $filename === '..') {
            throw new \RuntimeException('Invalid target filename');
        }

        // Check if target file already exists
        if (file_exists($targetPath) && !is_writable($targetPath)) {
            throw new \RuntimeException('Target file exists and is not writable');
        }

        // Move the file
        if (!move_uploaded_file($this->tmpName, $targetPath)) {
            throw new \RuntimeException('Failed to move uploaded file to destination');
        }

        // Set proper permissions
        @chmod($targetPath, 0644);

        return true;
    }

    /**
     * Get error message
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

        return $errors[$this->error] ?? 'Unknown upload error';
    }

    /**
     * Check if file has a suspicious double extension
     */
    public function hasSuspiciousExtension(): bool
    {
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'cgi', 'pl', 'sh', 'py', 'exe', 'bat'];
        $filename = strtolower($this->name);

        // Check for double extensions like image.php.jpg
        foreach ($dangerousExtensions as $ext) {
            if (strpos($filename, '.' . $ext . '.') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a safe filename
     */
    public function getSafeFilename(): string
    {
        $name = pathinfo($this->name, PATHINFO_FILENAME);
        $extension = $this->getClientExtension();

        // Remove any potentially dangerous characters
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = trim($name, '_');

        // If name is empty after sanitization, use a default
        if (empty($name)) {
            $name = 'file_' . time();
        }

        return $name . '.' . $extension;
    }
}
