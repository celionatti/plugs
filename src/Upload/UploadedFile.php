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
    
    public function __construct(array $file)
    {
        $this->name = $file['name'] ?? '';
        $this->type = $file['type'] ?? '';
        $this->tmpName = $file['tmp_name'] ?? '';
        $this->error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $this->size = $file['size'] ?? 0;
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
     * Get MIME type
     */
    public function getClientMediaType(): string
    {
        return $this->type;
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
        return $this->error === UPLOAD_ERR_OK;
    }
    
    /**
     * Get temporary file path
     */
    public function getTempPath(): string
    {
        return $this->tmpName;
    }
    
    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return in_array($this->getClientExtension(), $imageExtensions);
    }
    
    /**
     * Get file contents
     */
    public function getContents(): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Cannot read invalid upload');
        }
        
        return file_get_contents($this->tmpName);
    }
    
    /**
     * Move uploaded file to destination
     */
    public function moveTo(string $targetPath): bool
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Cannot move invalid upload: ' . $this->getErrorMessage());
        }
        
        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        return move_uploaded_file($this->tmpName, $targetPath);
    }
    
    /**
     * Get error message
     */
    public function getErrorMessage(): string
    {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];
        
        return $errors[$this->error] ?? 'Unknown error';
    }
}