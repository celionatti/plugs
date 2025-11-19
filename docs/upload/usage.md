# File 3: Usage Examples

```php
<?php

use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;

// Example 1: Basic image upload
$uploader = new FileUploader('/path/to/uploads');
$uploader->imagesOnly(5 * 1024 * 1024); // 5MB max

if (isset($_FILES['avatar'])) {
    $file = new UploadedFile($_FILES['avatar']);
    
    try {
        $result = $uploader->upload($file);
        
        echo "File uploaded successfully!\n";
        echo "Path: {$result['path']}\n";
        echo "URL: {$result['url']}\n";
        echo "Size: {$result['size']} bytes\n";
        
    } catch (Exception $e) {
        echo "Upload failed: " . $e->getMessage();
    }
}

// Example 2: Document upload with custom settings
$uploader = new FileUploader();
$uploader->documentsOnly(10 * 1024 * 1024) // 10MB
         ->organizeByDate(true)
         ->generateUniqueName(true)
         ->preventDuplicates(true);

// Example 3: Multiple file upload
if (isset($_FILES['photos'])) {
    $files = [];
    foreach ($_FILES['photos']['tmp_name'] as $key => $tmpName) {
        $files[] = new UploadedFile([
            'name' => $_FILES['photos']['name'][$key],
            'type' => $_FILES['photos']['type'][$key],
            'tmp_name' => $tmpName,
            'error' => $_FILES['photos']['error'][$key],
            'size' => $_FILES['photos']['size'][$key],
        ]);
    }
    
    $result = $uploader->uploadMultiple($files, 'user_' . $userId);
    
    echo "Uploaded: {$result['success_count']} files\n";
    echo "Errors: {$result['error_count']} files\n";
    echo "Total size: " . number_format($result['total_size']) . " bytes\n";
}

// Example 4: Custom validation
$uploader = new FileUploader();
$uploader->setAllowedExtensions(['jpg', 'png', 'pdf'])
         ->setAllowedMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
         ->setMaxSize(5 * 1024 * 1024)
         ->setMinSize(1024) // At least 1KB
         ->setImageDimensions(
             maxWidth: 4000,
             maxHeight: 4000,
             minWidth: 100,
             minHeight: 100
         );

// Example 5: Disable security files if causing issues
$uploader = new FileUploader();
$uploader->disableSecurityFiles(); // Won't create .htaccess files

// Example 6: Rate limiting
$uploader = new FileUploader();
$uploader->setRateLimit(5); // Max 5 uploads per minute per user

$userId = 'user_123';
$file = new UploadedFile($_FILES['file']);

try {
    $result = $uploader->upload($file, null, $userId);
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'rate limit')) {
        echo "Too many uploads. Please wait.";
    }
}

// Example 7: Custom filename
$file = new UploadedFile($_FILES['profile_pic']);
$result = $uploader->upload($file, 'user_' . $userId . '_profile');
// Result: user_123_profile.jpg

// Example 8: SVG upload (disabled by default)
$uploader = new FileUploader();
$uploader->allowSvg(true) // Enable SVG
         ->setAllowedExtensions(['svg'])
         ->setAllowedMimeTypes(['image/svg+xml']);

// Example 9: Delete uploaded file
$uploader->delete('/path/to/uploads/2024/01/15/file.jpg');

// Example 10: Check system limits
echo "Max upload size: " . FileUploader::getMaxUploadSize() . " bytes\n";
```

## File 4: Form Example

```html
<!DOCTYPE html>
<html>
<head>
    <title>File Upload</title>
</head>
<body>
    <h1>Upload Files</h1>
    
    <!-- Single file upload -->
    <form action="/upload" method="POST" enctype="multipart/form-data">
        @csrf
        
        <label>Profile Picture:</label>
        <input type="file" name="avatar" accept="image/*" required>
        
        <button type="submit">Upload</button>
    </form>
    
    <!-- Multiple file upload -->
    <form action="/upload-multiple" method="POST" enctype="multipart/form-data">
        @csrf
        
        <label>Photos:</label>
        <input type="file" name="photos[]" accept="image/*" multiple>
        
        <button type="submit">Upload All</button>
    </form>
    
    <!-- Document upload -->
    <form action="/upload-document" method="POST" enctype="multipart/form-data">
        @csrf
        
        <label>Document:</label>
        <input type="file" name="document" accept=".pdf,.doc,.docx">
        
        <button type="submit">Upload</button>
    </form>
</body>
</html>
```

## File 5: Controller Example

```php
<?php

namespace App\Controllers;

use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;

class UploadController
{
    private FileUploader $uploader;
    
    public function __construct()
    {
        $this->uploader = new FileUploader(
            uploadPath: BASE_PATH . '/storage/uploads'
        );
    }
    
    public function uploadAvatar(ServerRequestInterface $request)
    {
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles['avatar'])) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }
        
        $file = $uploadedFiles['avatar'];
        
        // Convert PSR-7 UploadedFileInterface to our UploadedFile
        $uploadedFile = new UploadedFile([
            'name' => $file->getClientFilename(),
            'type' => $file->getClientMediaType(),
            'tmp_name' => $file->getStream()->getMetadata('uri'),
            'error' => $file->getError(),
            'size' => $file->getSize(),
        ]);
        
        try {
            $this->uploader->imagesOnly(2 * 1024 * 1024); // 2MB
            $this->uploader->setImageDimensions(
                maxWidth: 1000,
                maxHeight: 1000,
                minWidth: 100,
                minHeight: 100
            );
            
            $userId = auth()->id();
            $result = $this->uploader->upload(
                $uploadedFile,
                'avatar_' . $userId,
                'user_' . $userId
            );
            
            // Save to database
            db()->table('users')->where('id', $userId)->update([
                'avatar_path' => $result['path'],
                'avatar_url' => $result['url']
            ]);
            
            return response()->json([
                'success' => true,
                'file' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
    
    public function uploadDocument(ServerRequestInterface $request)
    {
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles['document'])) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }
        
        try {
            $this->uploader->documentsOnly(10 * 1024 * 1024); // 10MB
            
            // Convert file
            $file = $uploadedFiles['document'];
            $uploadedFile = new UploadedFile([
                'name' => $file->getClientFilename(),
                'type' => $file->getClientMediaType(),
                'tmp_name' => $file->getStream()->getMetadata('uri'),
                'error' => $file->getError(),
                'size' => $file->getSize(),
            ]);
            
            $result = $this->uploader->upload($uploadedFile);
            
            return response()->json([
                'success' => true,
                'file' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
```

## Key Fixes Applied

### 1. **Fixed .htaccess Recreation Issue** ✅

- Added `$securityFilesCreated` flag
- Added `.upload_security` marker file
- Only recreates security files once per 24 hours
- Added `disableSecurityFiles()` method to completely disable

### 2. **Fixed Type Issues** ✅

- Added proper type checking for `imagedestroy()`
- Fixed `is_resource()` checks for GD images
- Improved error handling in image processing

### 3. **Improved Security** ✅

- Better path traversal detection
- Enhanced SVG validation
- Additional dangerous patterns detection
- Improved permission checks

### 4. **Better Error Handling** ✅

- More descriptive error messages
- Proper exception catching
- Better logging integration

### 5. **Performance Improvements** ✅

- Atomic file operations with locks
- Better rate limiting cleanup
- Optimized duplicate detection

### 6. **Production Readiness** ✅

- Proper constant checks
- Better fallback handling
- Improved file size parsing
- Enhanced validation methods

## Usage Tips

1. **Disable security files if they cause issues:**

```php
$uploader->disableSecurityFiles();

2. **Use rate limiting for public uploads:**

```php
$uploader->setRateLimit(5); // 5 uploads per minute

3. **Always validate file types:**

```php
$uploader->setAllowedExtensions(['jpg', 'png'])
         ->setAllowedMimeTypes(['image/jpeg', 'image/png']);
```

4. **Enable duplicate prevention for efficiency:**

```php
$uploader->preventDuplicates(true);
```

These classes are now production-ready with all major issues fixed!
