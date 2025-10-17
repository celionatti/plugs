<?php

declare(strict_types=1);

namespace Plugs\Upload;

/*
|--------------------------------------------------------------------------
| FileUploader Class
|--------------------------------------------------------------------------
|
| This class handles file uploads, including validation, storage,
| and error handling.
*/

class FileUploader
{
    private $uploadPath;
    private $allowedExtensions = [];
    private $maxSize = 10485760; // 10MB default
    private $generateUniqueName = true;
    
    public function __construct(string|null $uploadPath = null)
    {
        $this->uploadPath = $uploadPath ?? BASE_PATH . 'public/uploads';
        
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
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
     * Set maximum file size in bytes
     */
    public function setMaxSize(int $bytes): self
    {
        $this->maxSize = $bytes;
        return $this;
    }
    
    /**
     * Set upload path
     */
    public function setUploadPath(string $path): self
    {
        $this->uploadPath = $path;
        
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
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
     * Upload a file
     */
    public function upload(UploadedFile $file, ?string $customName = null): array
    {
        // Validate file
        $this->validate($file);
        
        // Generate filename
        $filename = $customName ?? $this->generateFilename($file);
        
        // Create subdirectory by date (optional organization)
        $subdir = date('Y/m/d');
        $fullPath = $this->uploadPath . '/' . $subdir;
        
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        
        $targetPath = $fullPath . '/' . $filename;
        
        // Move file
        if (!$file->moveTo($targetPath)) {
            throw new \RuntimeException('Failed to move uploaded file');
        }
        
        return [
            'name' => $filename,
            'path' => $targetPath,
            'url' => '/uploads/' . $subdir . '/' . $filename,
            'size' => $file->getSize(),
            'type' => $file->getClientMediaType(),
            'extension' => $file->getClientExtension(),
        ];
    }
    
    /**
     * Upload multiple files
     */
    public function uploadMultiple(array $files): array
    {
        $uploaded = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $uploaded[] = $this->upload($file);
            }
        }
        
        return $uploaded;
    }
    
    /**
     * Validate uploaded file
     */
    private function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Invalid file upload: ' . $file->getErrorMessage());
        }
        
        // Check file size
        if ($file->getSize() > $this->maxSize) {
            throw new \RuntimeException(
                'File size exceeds maximum allowed: ' . 
                $this->formatBytes($this->maxSize)
            );
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
        
        // Additional security: Check MIME type
        if ($file->isImage()) {
            $this->validateImage($file);
        }
    }
    
    /**
     * Validate image file
     */
    private function validateImage(UploadedFile $file): void
    {
        $imageInfo = @getimagesize($file->getTempPath());
        
        if ($imageInfo === false) {
            throw new \RuntimeException('Invalid image file');
        }
    }
    
    /**
     * Generate unique filename
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientExtension();
        
        if ($this->generateUniqueName) {
            return uniqid() . '_' . time() . '.' . $extension;
        }
        
        // Sanitize original filename
        $name = pathinfo($file->getClientFilename(), PATHINFO_FILENAME);
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        
        return $name . '.' . $extension;
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
        if (file_exists($path)) {
            return unlink($path);
        }
        
        return false;
    }
}