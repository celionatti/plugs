<?php

/**
 * FileUploader - Production Usage Examples
 */

use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;

// ============================================
// Example 1: Basic Image Upload
// ============================================

try {
    $uploader = new FileUploader('/path/to/storage/uploads');
    
    // Configure for images only
    $uploader->imagesOnly(5 * 1024 * 1024) // 5MB max
             ->setImageDimensions(
                 maxWidth: 2000,
                 maxHeight: 2000,
                 minWidth: 100,
                 minHeight: 100
             );

    // Handle single file upload
    if (isset($_FILES['avatar'])) {
        $file = new UploadedFile($_FILES['avatar']);
        $result = $uploader->upload($file);
        
        echo "File uploaded successfully!\n";
        echo "Path: {$result['path']}\n";
        echo "URL: {$result['url']}\n";
        echo "Size: {$result['size']} bytes\n";
    }
} catch (\Exception $e) {
    error_log("Upload failed: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// ============================================
// Example 2: Multiple File Upload with Rate Limiting
// ============================================

try {
    $uploader = new FileUploader();
    
    $uploader->imagesOnly(10 * 1024 * 1024) // 10MB max
             ->setRateLimit(5) // Max 5 uploads per minute per user
             ->preventDuplicates(true)
             ->organizeByDate(true);

    // Get user identifier for rate limiting
    $userId = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];

    if (isset($_FILES['photos'])) {
        $files = [];
        
        // Handle multiple file upload
        foreach ($_FILES['photos']['name'] as $key => $name) {
            $files[] = new UploadedFile([
                'name' => $_FILES['photos']['name'][$key],
                'type' => $_FILES['photos']['type'][$key],
                'tmp_name' => $_FILES['photos']['tmp_name'][$key],
                'error' => $_FILES['photos']['error'][$key],
                'size' => $_FILES['photos']['size'][$key],
            ]);
        }

        $result = $uploader->uploadMultiple($files, $userId);
        
        echo json_encode([
            'success' => true,
            'uploaded' => $result['success_count'],
            'failed' => $result['error_count'],
            'files' => $result['uploaded'],
            'errors' => $result['errors']
        ]);
    }
} catch (\Exception $e) {
    error_log("Batch upload failed: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}

// ============================================
// Example 3: Document Upload with Custom Configuration
// ============================================

try {
    $uploader = new FileUploader('/var/www/documents');
    
    $uploader->documentsOnly(20 * 1024 * 1024) // 20MB for documents
             ->setAllowedExtensions(['pdf', 'docx', 'xlsx'])
             ->generateUniqueName(true)
             ->organizeByDate(false); // Don't organize by date

    if (isset($_FILES['document'])) {
        $file = new UploadedFile($_FILES['document']);
        
        // Use custom filename
        $customName = 'user_' . $userId . '_document';
        $result = $uploader->upload($file, $customName);
        
        // Store in database
        $db->insert('documents', [
            'user_id' => $userId,
            'filename' => $result['name'],
            'original_name' => $result['original_name'],
            'path' => $result['relative_path'],
            'size' => $result['size'],
            'mime_type' => $result['type'],
            'hash' => $result['hash'] ?? null,
            'created_at' => $result['uploaded_at']
        ]);
    }
} catch (\Exception $e) {
    error_log("Document upload failed: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}

// ============================================
// Example 4: Profile Picture with Strict Validation
// ============================================

try {
    $uploader = new FileUploader();
    
    // Very strict image validation for profile pictures
    $uploader->setAllowedExtensions(['jpg', 'jpeg', 'png'])
             ->setAllowedMimeTypes(['image/jpeg', 'image/png'])
             ->setMaxSize(2 * 1024 * 1024) // 2MB max
             ->setImageDimensions(
                 maxWidth: 1000,
                 maxHeight: 1000,
                 minWidth: 200,
                 minHeight: 200
             )
             ->allowSvg(false) // Explicitly disable SVG
             ->preventDuplicates(true);

    if (isset($_FILES['profile_picture'])) {
        $file = new UploadedFile($_FILES['profile_picture']);
        
        // Additional custom validation
        if (!$file->hasValidSignature()) {
            throw new RuntimeException('File signature does not match extension');
        }
        
        $result = $uploader->upload($file, null, $_SESSION['user_id']);
        
        // Delete old profile picture if exists
        if (!empty($user['profile_picture_path'])) {
            $uploader->delete($user['profile_picture_path']);
        }
        
        // Update user profile
        $db->update('users', ['profile_picture' => $result['relative_path']], ['id' => $userId]);
        
        echo json_encode([
            'success' => true,
            'url' => $result['url'],
            'dimensions' => $result['dimensions']
        ]);
    }
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// ============================================
// Example 5: With PSR-3 Logger Integration
// ============================================

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

try {
    // Set up logger
    $logger = new Logger('uploads');
    $logger->pushHandler(new StreamHandler('/var/log/uploads.log', Logger::INFO));
    
    // Create uploader with logger
    $uploader = new FileUploader('/path/to/uploads', $logger);
    
    $uploader->imagesOnly()
             ->setRateLimit(10);

    if (isset($_FILES['image'])) {
        $file = new UploadedFile($_FILES['image']);
        $result = $uploader->upload($file, null, $_SERVER['REMOTE_ADDR']);
        
        // All operations are automatically logged
        echo json_encode(['success' => true, 'file' => $result]);
    }
} catch (\Exception $e) {
    // Errors are also logged automatically
    echo json_encode(['error' => $e->getMessage()]);
}

// ============================================
// Example 6: Advanced - File Type Detection
// ============================================

if (isset($_FILES['unknown_file'])) {
    $file = new UploadedFile($_FILES['unknown_file']);
    
    echo "Client Filename: " . $file->getClientFilename() . "\n";
    echo "Client Extension: " . $file->getClientExtension() . "\n";
    echo "Client MIME: " . $file->getClientMediaType() . "\n";
    echo "Actual MIME: " . $file->getActualMediaType() . "\n";
    echo "File Size: " . $file->getSize() . " bytes\n";
    echo "SHA-256 Hash: " . $file->getHash() . "\n";
    echo "Magic Bytes: " . $file->getMagicBytes() . "\n";
    echo "Valid Signature: " . ($file->hasValidSignature() ? 'Yes' : 'No') . "\n";
    
    if ($file->isImage()) {
        echo "Is Image: Yes\n";
        echo "Is Actual Image: " . ($file->isActualImage() ? 'Yes' : 'No') . "\n";
        
        $dimensions = $file->getImageDimensions();
        if ($dimensions) {
            echo "Dimensions: {$dimensions['width']}x{$dimensions['height']}\n";
        }
    }
    
    echo "Has Dangerous Extension: " . ($file->hasDangerousExtension() ? 'Yes' : 'No') . "\n";
    echo "Has Suspicious Extension: " . ($file->hasSuspiciousExtension() ? 'Yes' : 'No') . "\n";
    echo "Safe Filename: " . $file->getSafeFilename() . "\n";
}

// ============================================
// Example 7: Error Handling and User Feedback
// ============================================

function handleUpload(): array
{
    try {
        if (!isset($_FILES['file'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        $file = new UploadedFile($_FILES['file']);
        
        if (!$file->isValid()) {
            return ['success' => false, 'error' => $file->getErrorMessage()];
        }

        $uploader = new FileUploader();
        $uploader->imagesOnly();
        
        $result = $uploader->upload($file);
        
        return [
            'success' => true,
            'file' => [
                'name' => $result['name'],
                'url' => $result['url'],
                'size' => $result['size'],
                'dimensions' => $result['dimensions'] ?? null
            ]
        ];
        
    } catch (\RuntimeException $e) {
        // Log error for debugging
        error_log("Upload error: " . $e->getMessage());
        
        // Return user-friendly message
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    } catch (\Exception $e) {
        error_log("Unexpected upload error: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'An unexpected error occurred. Please try again.'
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(handleUpload());

// ============================================
// Example 8: Configuration File
// ============================================

// config/upload.php
return [
    'path' => BASE_PATH . '/storage/uploads',
    
    'images' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
        'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'max_width' => 2000,
        'max_height' => 2000,
        'min_width' => 100,
        'min_height' => 100,
    ],
    
    'documents' => [
        'max_size' => 20 * 1024 * 1024, // 20MB
        'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'],
        'mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv'
        ],
    ],
    
    'security' => [
        'allow_svg' => false,
        'rate_limit' => 10, // uploads per minute
        'prevent_duplicates' => true,
        'organize_by_date' => true,
    ]
];

// Usage with config
$config = require 'config/upload.php';

$uploader = new FileUploader($config['path']);
$uploader->setAllowedExtensions($config['images']['extensions'])
         ->setAllowedMimeTypes($config['images']['mime_types'])
         ->setMaxSize($config['images']['max_size'])
         ->setImageDimensions(
             $config['images']['max_width'],
             $config['images']['max_height'],
             $config['images']['min_width'],
             $config['images']['min_height']
         )
         ->setRateLimit($config['security']['rate_limit'])
         ->preventDuplicates($config['security']['prevent_duplicates'])
         ->organizeByDate($config['security']['organize_by_date'])
         ->allowSvg($config['security']['allow_svg']);

// ============================================
// Example 9: HTML Form
// ============================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Secure File Upload</title>
</head>
<body>
    <h1>Upload File</h1>
    
    <!-- Single file upload -->
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <input type="file" name="file" accept="image/jpeg,image/png,image/gif" required>
        <button type="submit">Upload</button>
    </form>
    
    <!-- Multiple file upload -->
    <h2>Upload Multiple Photos</h2>
    <form action="upload_multiple.php" method="post" enctype="multipart/form-data">
        <input type="file" name="photos[]" accept="image/*" multiple required>
        <button type="submit">Upload Photos</button>
    </form>
    
    <script>
    // Client-side validation (additional layer, not replacement for server-side)
    document.querySelector('form').addEventListener('submit', function(e) {
        const input = this.querySelector('input[type="file"]');
        const file = input.files[0];
        
        if (file) {
            // Check file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('File is too large. Maximum size is 5MB.');
                return;
            }
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                return;
            }
        }
    });
    </script>
</body>
</html>