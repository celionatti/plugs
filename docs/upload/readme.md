# Plugs Upload Component

Production-ready file upload handler with comprehensive security features.

## Features

- ✅ Extension and MIME type validation
- ✅ File size constraints
- ✅ Image dimension validation
- ✅ Duplicate file detection
- ✅ Secure filename generation
- ✅ Automatic directory organization
- ✅ Multiple file upload support
- ✅ SVG security scanning
- ✅ Protection against malicious files
- ✅ PSR coding standards compliant

## Installation

```bash
composer require plugs/upload
```

## Basic Usage

### Simple Image Upload

```php
use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;

$uploader = new FileUploader('/var/www/uploads');
$uploader->imagesOnly();

if (isset($_FILES['photo'])) {
    try {
        $file = new UploadedFile($_FILES['photo']);
        $result = $uploader->upload($file);
        
        echo "Uploaded: {$result['url']}";
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}";
    }
}
```

### Document Upload

```php
$uploader = new FileUploader();
$uploader->documentsOnly()
    ->setMaxSize(10 * 1024 * 1024); // 10MB

$file = new UploadedFile($_FILES['document']);
$result = $uploader->upload($file);
```

### Multiple Files

```php
$uploader = new FileUploader();
$uploader->imagesOnly();

$files = [];
foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
    $files[] = new UploadedFile([
        'name' => $_FILES['images']['name'][$key],
        'type' => $_FILES['images']['type'][$key],
        'tmp_name' => $tmpName,
        'error' => $_FILES['images']['error'][$key],
        'size' => $_FILES['images']['size'][$key],
    ]);
}

$results = $uploader->uploadMultiple($files);
echo "Uploaded: {$results['success_count']} files";
```

## Configuration

### Allowed Extensions

```php
$uploader->setAllowedExtensions(['jpg', 'png', 'pdf']);
```

### File Size Limits

```php
$uploader->setMaxSize(5 * 1024 * 1024)  // 5MB max
    ->setMinSize(1024);                  // 1KB min
```

### Image Dimensions

```php
$uploader->setImageDimensions(
    maxWidth: 2000,
    maxHeight: 2000,
    minWidth: 100,
    minHeight: 100
);
```

### Custom Upload Path

```php
$uploader->setUploadPath('/custom/path/uploads');
```

### Unique Filenames

```php
$uploader->generateUniqueName(true);  // Enable
$uploader->generateUniqueName(false); // Use original names
```

### Date Organization

```php
$uploader->organizeByDate(true);  // Organize in YYYY/MM/DD folders
$uploader->organizeByDate(false); // All files in root upload folder
```

### Prevent Duplicates

```php
$uploader->preventDuplicates(true); // Check file hash before upload
```

## Security Features

### Automatic Protection

- Blocks dangerous file extensions (php, exe, etc.)
- Detects double extensions (image.php.jpg)
- Verifies actual MIME type vs client-provided
- Checks for null byte injection
- Validates image integrity
- SVG XSS protection
- Creates .htaccess to prevent PHP execution

### Manual Security Settings

```php
// Disable if needed (not recommended)
$uploader->blockDangerousExtensions = false;
$uploader->blockDoubleExtensions = false;
$uploader->checkActualMimeType = false;
```

## Advanced Usage

### Custom Filename

```php
$result = $uploader->upload($file, 'custom_name');
// Output: custom_name.jpg
```

### With PSR-7 ServerRequest

```php
$uploadedFile = $request->getUploadedFile('image');
if ($uploadedFile) {
    $result = $uploader->upload($uploadedFile);
}
```

### Get System Limits

```php
$maxSize = FileUploader::getMaxUploadSize();
echo "Max upload: " . ($maxSize / 1024 / 1024) . " MB";
```

### Delete Files

```php
$uploader->delete($result['path']);
```

## Response Format

Successful upload returns:

```php
[
    'name' => 'file_20250110_a1b2c3d4.jpg',
    'original_name' => 'photo.jpg',
    'path' => '/var/www/uploads/2025/01/10/file_20250110_a1b2c3d4.jpg',
    'relative_path' => '2025/01/10/file_20250110_a1b2c3d4.jpg',
    'url' => '/uploads/2025/01/10/file_20250110_a1b2c3d4.jpg',
    'size' => 524288,
    'type' => 'image/jpeg',
    'extension' => 'jpg',
    'uploaded_at' => '2025-01-10 14:30:00',
    'dimensions' => [  // For images only
        'width' => 1920,
        'height' => 1080
    ],
    'hash' => '...'  // If preventDuplicates is enabled
]
```

## Error Handling

```php
try {
    $result = $uploader->upload($file);
} catch (RuntimeException $e) {
    // Validation or upload failed
    error_log($e->getMessage());
    
    // Return user-friendly message
    echo "Upload failed. Please try again.";
}
```

## Testing

```php
// Check if file is valid before upload
if ($file->isValid()) {
    // Proceed with upload
}

// Get error message
if (!$file->isValid()) {
    echo $file->getErrorMessage();
}
```

## Performance Tips

1. Use `organizeByDate(true)` for large file collections
2. Enable `preventDuplicates(true)` to save storage
3. Set appropriate `maxSize` to prevent memory issues
4. Use `generateUniqueName(true)` to avoid filename conflicts

## Security Best Practices

1. Always validate file types on server-side
2. Never trust client-provided MIME types alone
3. Store uploads outside web root when possible
4. Use the auto-generated .htaccess protection
5. Regularly scan upload directory for suspicious files
6. Implement rate limiting on upload endpoints
7. Validate file content, not just extension

## License

MIT License

```

---

## File 4: `examples/upload_examples.php`

```php
<?php

declare(strict_types=1);

/**
 * FileUploader Usage Examples
 * Comprehensive examples for various upload scenarios
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;

// ============================================
// Example 1: Basic Image Upload
// ============================================

function example1_basicImageUpload(): void
{
    echo "=== Example 1: Basic Image Upload ===\n";
    
    $uploader = new FileUploader('/var/www/uploads');
    $uploader->imagesOnly()
        ->setMaxSize(5 * 1024 * 1024); // 5MB
    
    if (isset($_FILES['photo'])) {
        try {
            $file = new UploadedFile($_FILES['photo']);
            $result = $uploader->upload($file);
            
            echo "✓ Upload successful!\n";
            echo "  URL: {$result['url']}\n";
            echo "  Size: " . formatBytes($result['size']) . "\n";
            
            if (isset($result['dimensions'])) {
                echo "  Dimensions: {$result['dimensions']['width']}x{$result['dimensions']['height']}\n";
            }
        } catch (Exception $e) {
            echo "✗ Upload failed: {$e->getMessage()}\n";
        }
    }
}

// ============================================
// Example 2: Document Upload with Validation
// ============================================

function example2_documentUpload(): void
{
    echo "\n=== Example 2: Document Upload ===\n";
    
    $uploader = new FileUploader();
    $uploader->documentsOnly()
        ->setMaxSize(10 * 1024 * 1024)
        ->setMinSize(1024)
        ->generateUniqueName(true)
        ->organizeByDate(true);
    
    if (isset($_FILES['document'])) {
        try {
            $file = new UploadedFile($_FILES['document']);
            
            echo "Validating file...\n";
            echo "  Name: {$file->getClientFilename()}\n";
            echo "  Size: " . formatBytes($file->getSize()) . "\n";
            echo "  Type: {$file->getMimeType()}\n";
            
            $result = $uploader->upload($file);
            
            echo "✓ Document uploaded successfully!\n";
            echo "  Path: {$result['path']}\n";
            
            // Return JSON for API
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

// ============================================
// Example 3: Multiple File Upload
// ============================================

function example3_multipleFileUpload(): void
{
    echo "\n=== Example 3: Multiple File Upload ===\n";
    
    $uploader = new FileUploader();
    $uploader->imagesOnly()
        ->preventDuplicates(true)
        ->setMaxSize(3 * 1024 * 1024);
    
    if (isset($_FILES['images'])) {
        $files = [];
        
        // Normalize $_FILES array
        if (is_array($_FILES['images']['tmp_name'])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $files[] = new UploadedFile([
                        'name' => $_FILES['images']['name'][$key],
                        'type' => $_FILES['images']['type'][$key],
                        'tmp_name' => $tmpName,
                        'error' => $_FILES['images']['error'][$key],
                        'size' => $_FILES['images']['size'][$key],
                    ]);
                }
            }
        }
        
        $results = $uploader->uploadMultiple($files);
        
        echo "Upload Results:\n";
        echo "  ✓ Success: {$results['success_count']} files\n";
        echo "  ✗ Failed: {$results['error_count']} files\n";
        echo "  Total Size: " . formatBytes($results['total_size']) . "\n";
        
        if (!empty($results['uploaded'])) {
            echo "\nUploaded Files:\n";
            foreach ($results['uploaded'] as $file) {
                echo "  - {$file['original_name']} → {$file['url']}\n";
            }
        }
        
        if (!empty($results['errors'])) {
            echo "\nErrors:\n";
            foreach ($results['errors'] as $error) {
                echo "  - {$error['file']}: {$error['error']}\n";
            }
        }
    }
}

// ============================================
// Example 4: Avatar Upload with Custom Name
// ============================================

function example4_avatarUpload(int $userId): void
{
    echo "\n=== Example 4: Avatar Upload ===\n";
    
    $uploader = new FileUploader('/var/www/uploads/avatars');
    $uploader->imagesOnly()
        ->setMaxSize(1 * 1024 * 1024)
        ->setImageDimensions(
            maxWidth: 1000,
            maxHeight: 1000,
            minWidth: 100,
            minHeight: 100
        )
        ->organizeByDate(false);
    
    if (isset($_FILES['avatar'])) {
        try {
            $file = new UploadedFile($_FILES['avatar']);
            
            // Delete old avatar
            $oldAvatar = "/var/www/uploads/avatars/user_{$userId}_avatar.jpg";
            if (file_exists($oldAvatar)) {
                $uploader->delete($oldAvatar);
                echo "Old avatar deleted\n";
            }
            
            // Upload new avatar
            $customName = "user_{$userId}_avatar";
            $result = $uploader->upload($file, $customName);
            
            echo "✓ Avatar uploaded: {$result['url']}\n";
            
            // Update database (pseudo-code)
            // updateUserAvatar($userId, $result['path']);
            
        } catch (Exception $e) {
            echo "✗ Avatar upload failed: {$e->getMessage()}\n";
        }
    }
}

// ============================================
// Example 5: Strict Validation
// ============================================

function example5_strictValidation(): void
{
    echo "\n=== Example 5: Strict Validation ===\n";
    
    $uploader = new FileUploader();
    $uploader->setAllowedExtensions(['jpg', 'png'])
        ->setAllowedMimeTypes(['image/jpeg', 'image/png'])
        ->setMaxSize(2 * 1024 * 1024)
        ->setMinSize(50 * 1024)
        ->setImageDimensions(
            maxWidth: 2000,
            maxHeight: 2000,
            minWidth: 500,
            minHeight: 500
        )
        ->checkActualMimeType(true)
        ->generateUniqueName(true);
    
    if (isset($_FILES['photo'])) {
        try {
            $file = new UploadedFile($_FILES['photo']);
            
            echo "Performing strict validation...\n";
            echo "  Extension: {$file->getClientExtension()}\n";
            echo "  MIME: {$file->getMimeType()}\n";
            echo "  Size: " . formatBytes($file->getSize()) . "\n";
            
            if ($dims = $file->getImageDimensions()) {
                echo "  Dimensions: {$dims['width']}x{$dims['height']}\n";
            }
            
            $result = $uploader->upload($file);
            echo "✓ File passed all validation checks\n";
            echo "  Saved as: {$result['name']}\n";
            
        } catch (Exception $e) {
            echo "✗ Validation failed: {$e->getMessage()}\n";
        }
    }
}

// ============================================
// Example 6: API Endpoint
// ============================================

function example6_apiEndpoint(): void
{
    header('Content-Type: application/json');
    
    // Authentication check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $uploader = new FileUploader();
    $uploader->setAllowedExtensions(['jpg', 'png', 'pdf', 'docx'])
        ->setMaxSize(10 * 1024 * 1024)
        ->preventDuplicates(true);
    
    try {
        if (!isset($_FILES['file'])) {
            throw new RuntimeException('No file provided');
        }
        
        $file = new UploadedFile($_FILES['file']);
        $result = $uploader->upload($file);
        
        // Save to database (pseudo-code)
        $fileId = saveToDatabase([
            'user_id' => $_SESSION['user_id'],
            'filename' => $result['name'],
            'path' => $result['path'],
            'size' => $result['size'],
            'mime_type' => $result['type']
        ]);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'file_id' => $fileId,
            'url' => $result['url'],
            'name' => $result['name'],
            'size' => $result['size']
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// ============================================
// Example 7: Form Handler
// ============================================

function example7_formHandler(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "\n=== Example 7: Form Handler ===\n";
        
        $uploader = new FileUploader();
        $uploader->imagesOnly();
        
        $errors = [];
        $uploaded = [];
        
        // Handle profile picture
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $file = new UploadedFile($_FILES['profile_pic']);
                $uploaded['profile_pic'] = $uploader->upload($file, 'profile');
                echo "✓ Profile picture uploaded\n";
            } catch (Exception $e) {
                $errors['profile_pic'] = $e->getMessage();
                echo "✗ Profile picture failed: {$e->getMessage()}\n";
            }
        }
        
        // Handle cover photo
        if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $file = new UploadedFile($_FILES['cover_photo']);
                $uploaded['cover_photo'] = $uploader->upload($file, 'cover');
                echo "✓ Cover photo uploaded\n";
            } catch (Exception $e) {
                $errors['cover_photo'] = $e->getMessage();
                echo "✗ Cover photo failed: {$e->getMessage()}\n";
            }
        }
        
        // Return results
        if (empty($errors)) {
            echo "\n✓ All files uploaded successfully!\n";
        } else {
            echo "\n✗ Some uploads failed\n";
        }
    }
}

// ============================================
// Example 8: Check System Limits
// ============================================

function example8_checkLimits(): void
{
    echo "\n=== Example 8: System Limits ===\n";
    
    $maxUpload = FileUploader::getMaxUploadSize();
    
    echo "PHP Configuration:\n";
    echo "  upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
    echo "  post_max_size: " . ini_get('post_max_size') . "\n";
    echo "  memory_limit: " . ini_get('memory_limit') . "\n";
    echo "  Effective max upload: " . formatBytes($maxUpload) . "\n";
}

// ============================================
// Helper Functions
// ============================================

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

function saveToDatabase(array $data): int
{
    // Pseudo-code for database insertion
    // return $db->insert('uploads', $data);
    return rand(1, 1000); // Mock ID
}

// ============================================
// Run Examples (uncomment to test)
// ============================================

// example1_basicImageUpload();
// example2_documentUpload();
// example3_multipleFileUpload();
// example4_avatarUpload(123);
// example5_strictValidation();
// example6_apiEndpoint();
// example7_formHandler();
example8_checkLimits();
```

---

## File 5: `examples/upload_form.html`

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload Examples</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-section h2 {
            color: #34495e;
            margin-bottom: 20px;
            font-size: 1.2em;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        input[type="file"]:hover {
            border-color: #3498db;
        }
        
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #2980b9;
        }
        
        .info {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
        
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            display: none;
        }
        
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 File Upload Examples</h1>
        
        <!-- Single Image Upload -->
        <div class="form-section">
            <h2>1. Single Image Upload</h2>
            <div class="info">
                <p><strong>Max size:</strong> 5MB</p>
                <p><strong>Allowed:</strong> JPG, PNG, GIF, WebP</p>
                <p><strong>Max dimensions:</strong> 4000x4000px</p>
            </div>
            <form action="upload_handler.php?example=1" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="photo">Choose Image:</label>
                    <input type="file" id="photo" name="photo" accept="image/*" required>
                </div>
                <button type="submit">Upload Image</button>
            </form>
            <div class="result" id="result1"></div>
        </div>
        
        <!-- Document Upload -->
        <div class="form-section">
            <h2>2. Document Upload</h2>
            <div class="info">
                <p><strong>Max size:</strong> 10MB</p>
                <p><strong>Allowed:</strong> PDF, DOC, DOCX, XLS, XLSX, TXT, CSV</p>
            </div>
            <form action="upload_handler.php?example=2" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="document">Choose Document:</label>
                    <input type="file" id="document" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" required>
                </div>
                <button type="submit">Upload Document</button>
            </form>
            <div class="result" id="result2"></div>
        </div>
        
        <!-- Multiple Images Upload -->
        <div class="form-section">
            <h2>3. Multiple Images Upload</h2>
            <div class="info">
                <p><strong>Max size per file:</strong> 3MB</p>
                <p><strong>Allowed:</strong> JPG, PNG, GIF, WebP</p>
                <p><strong>Duplicate detection:</strong> Enabled</p>
            </div>
            <form action="upload_handler.php?example=3" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="images">Choose Multiple Images:</label>
                    <input type="file" id="images" name="images[]" accept="image/*" multiple required>
                </div>
                <button type="submit">Upload Images</button>
            </form>
            <div class="result" id="result3"></div>
        </div>
        
        <!-- Avatar Upload -->
        <div class="form-section">
            <h2>4. Avatar Upload</h2>
            <div class="info">
                <p><strong>Max size:</strong> 1MB</p>
                <p><strong>Allowed:</strong> JPG, PNG</p>
                <p><strong>Dimensions:</strong> 100x100 to 1000x1000px</p>
            </div>
            <form action="upload_handler.php?example=4" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="123">
                <div class="form-group">
                    <label for="avatar">Choose Avatar:</label>
                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png" required>
                </div>
                <button type="submit">Upload Avatar</button>
            </form>
            <div class="result" id="result4"></div>
        </div>
        
        <!-- Profile Form with Multiple Files -->
        <div class="form-section">
            <h2>5. Complete Profile Form</h2>
            <form action="upload_handler.php?example=7" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_pic">Profile Picture:</label>
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="cover_photo">Cover Photo:</label>
                    <input type="file" id="cover_photo" name="cover_photo" accept="image/*">
                </div>
                <button type="submit">Save Profile</button>
            </form>
            <div class="result" id="result5"></div>
        </div>
    </div>
    
    <script>
        // Add client-side file validation
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const files = e.target.files;
                const maxSize = this.dataset.maxSize || 10 * 1024 * 1024; // 10MB default
                
                for (let file of files) {
                    if (file.size > maxSize) {
                        alert(`File ${file.name} is too large. Max size: ${(maxSize / 1024 / 1024).toFixed(2)}MB`);
                        this.value = '';
                        return;
                    }
                }
            });
        });
        
        // Handle form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const resultDiv = this.parentElement.querySelector('.result');
                
                resultDiv.style.display = 'block';
                resultDiv.className = 'result';
                resultDiv.textContent = 'Uploading...';
                
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.url) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML = `
                            <strong>✓ Upload successful!</strong><br>
                            ${data.url ? `URL: ${data.url}<br>` : ''}
                            ${data.size ? `Size: ${formatBytes(data.size)}<br>` : ''}
                            ${data.success_count ? `Uploaded: ${data.success_count} files<br>` : ''}
                        `;
                    } else {
                        throw new Error(data.error || 'Upload failed');
                    }
                })
                .catch(error => {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<strong>✗ Error:</strong> ${error.message}`;
                });
            });
        });
        
        function formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            return bytes.toFixed(2) + ' ' + units[i];
        }
    </script>
</body>
</html>
```

---

## File 6: `examples/upload_handler.php`

```php
<?php

declare(strict_types=1);

/**
 * Upload Handler
 * Routes upload requests to appropriate example handlers
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/upload_examples.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors in JSON response

// Get example number from query string
$example = $_GET['example'] ?? '1';

try {
    switch ($example) {
        case '1':
            example1_basicImageUpload();
            break;
            
        case '2':
            example2_documentUpload();
            break;
            
        case '3':
            example3_multipleFileUpload();
            break;
            
        case '4':
            $userId = (int) ($_POST['user_id'] ?? 123);
            example4_avatarUpload($userId);
            break;
            
        case '5':
            example5_strictValidation();
            break;
            
        case '6':
            example6_apiEndpoint();
            break;
            
        case '7':
            example7_formHandler();
            break;
            
        default:
            throw new Exception('Invalid example number');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

---

## Installation Instructions

### Step 1: Copy Files

Copy all files to your project:

```bash
# Copy the main classes
cp UploadedFile.php src/Upload/
cp FileUploader.php src/Upload/

# Copy examples (optional)
cp upload_examples.php examples/
cp upload_form.html examples/
cp upload_handler.php examples/
```

### Step 2: Update Composer (if using)

Add to your `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "Plugs\\Upload\\": "src/Upload/"
        }
    }
}
```

Then run:

```

bash
composer dump-autoload
```

### Step 3: Configure Upload Directory

Ensure your upload directory exists and is writable:

```bash
mkdir -p public/uploads
chmod 755 public/uploads
```

### Step 4: Update Your Framework Integration

Add to your `ServerRequest` class (already included in your documents):

```php
/**
 * Get uploaded file
 */
public function getUploadedFile(string $key): ?\Plugs\Upload\UploadedFile
{
    $files = $this->getUploadedFiles();

    if (!isset($files[$key])) {
        return null;
    }

    $file = $files[$key];

    if (is_array($file) && !($file instanceof \Plugs\Upload\UploadedFile)) {
        return new \Plugs\Upload\UploadedFile($file);
    }

    return $file instanceof \Plugs\Upload\UploadedFile ? $file : null;
}
```

---

## Quick Start

### Basic Usage

```php
<?php

use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;

// Initialize uploader
$uploader = new FileUploader();

// Configure for images
$uploader->imagesOnly()
    ->setMaxSize(5 * 1024 * 1024); // 5MB

// Handle upload
if (isset($_FILES['file'])) {
    try {
        $file = new UploadedFile($_FILES['file']);
        $result = $uploader->upload($file);
        
        echo json_encode([
            'success' => true,
            'url' => $result['url'],
            'size' => $result['size']
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
```

---

## Testing Checklist

- [ ] Test single file upload
- [ ] Test multiple file upload
- [ ] Test file size validation
- [ ] Test file type validation
- [ ] Test image dimension validation
- [ ] Test dangerous file blocking
- [ ] Test duplicate file detection
- [ ] Test custom filename
- [ ] Test directory organization
- [ ] Test error handling
- [ ] Test with PSR-7 ServerRequest
- [ ] Security audit completed

---

## Production Deployment Checklist

- [ ] Set appropriate `upload_max_filesize` in php.ini
- [ ] Set appropriate `post_max_size` in php.ini
- [ ] Configure proper permissions on upload directory (755)
- [ ] Enable `open_basedir` restriction
- [ ] Implement rate limiting on upload endpoints
- [ ] Set up antivirus scanning (optional)
- [ ] Configure backup for uploaded files
- [ ] Set up monitoring for upload directory size
- [ ] Implement file retention policy
- [ ] Test all upload scenarios
- [ ] Review security settings

---

## Support & Documentation

For more information:

- Check the README.md file
- Review the examples directory
- Read inline code documentation
- Test with provided HTML form

---

**Note:** All files are PSR-12 compliant and production-ready with comprehensive security features.
