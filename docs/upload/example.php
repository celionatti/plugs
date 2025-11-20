<?php

/**
 * FileUploader Usage Examples
 * Complete guide for different project scenarios
 */

require_once 'vendor/autoload.php';

use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;

// ============================================================================
// EXAMPLE 1: Basic Upload to Public Folder (Most Common)
// ============================================================================

try {
    // Create uploader with public folder
    $uploader = new FileUploader();
    $uploader->usePublicFolder('uploads'); // Creates /public/uploads
    
    // Configure for images
    $uploader->imagesOnly(5 * 1024 * 1024) // 5MB max
             ->setImageDimensions(maxWidth: 2000, maxHeight: 2000);
    
    // Upload single file
    $file = UploadedFile::createFromFilesArray($_FILES['avatar']);
    $result = $uploader->upload($file);
    
    echo "File uploaded successfully!\n";
    echo "URL: " . $result['url'] . "\n";
    echo "Path: " . $result['path'] . "\n";
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 2: Upload to Storage Folder (Outside Public Directory)
// ============================================================================

try {
    $uploader = new FileUploader();
    $uploader->useStorageFolder('documents'); // Creates /storage/documents
    
    // Configure for documents
    $uploader->documentsOnly(10 * 1024 * 1024) // 10MB max
             ->generateUniqueName(true)
             ->organizeByDate(true); // Organize by year/month/day
    
    $file = UploadedFile::createFromFilesArray($_FILES['document']);
    $result = $uploader->upload($file);
    
    // For storage files, you'll need a download controller
    echo "File stored at: " . $result['path'] . "\n";
    echo "Relative path: " . $result['relative_path'] . "\n";
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 3: Custom Path Configuration
// ============================================================================

try {
    $uploader = new FileUploader();
    
    // Set custom absolute path
    $uploader->setUploadPath('/var/www/myapp/storage/uploads')
             ->setBaseUrl('/files'); // URL prefix for accessing files
    
    $file = UploadedFile::createFromFilesArray($_FILES['file']);
    $result = $uploader->upload($file);
    
    echo "Uploaded: " . $result['url'] . "\n";
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 4: Multiple File Upload
// ============================================================================

try {
    $uploader = new FileUploader();
    $uploader->usePublicFolder('uploads')
             ->imagesOnly()
             ->setRateLimit(20); // 20 uploads per minute
    
    // Create multiple files from array
    $files = UploadedFile::createMultipleFromFilesArray($_FILES['photos']);
    
    // Upload all
    $results = $uploader->uploadMultiple($files, 'user_123');
    
    echo "Successfully uploaded: " . $results['success_count'] . "\n";
    echo "Failed: " . $results['error_count'] . "\n";
    echo "Total size: " . formatBytes($results['total_size']) . "\n";
    
    foreach ($results['uploaded'] as $file) {
        echo "- " . $file['name'] . " (" . $file['url'] . ")\n";
    }
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "- " . $error['file'] . ": " . $error['error'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 5: Advanced Image Upload with Validation
// ============================================================================

try {
    $uploader = new FileUploader();
    $uploader->usePublicFolder('uploads/avatars')
             ->setAllowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
             ->setAllowedMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
             ->setMaxSize(2 * 1024 * 1024) // 2MB
             ->setImageDimensions(
                 maxWidth: 1000,
                 maxHeight: 1000,
                 minWidth: 100,
                 minHeight: 100
             )
             ->generateUniqueName(true)
             ->preventDuplicates(true)
             ->checkActualMimeType(true);
    
    $file = UploadedFile::createFromFilesArray($_FILES['avatar']);
    $result = $uploader->upload($file, null, 'user_' . $_SESSION['user_id']);
    
    if (isset($result['duplicate']) && $result['duplicate']) {
        echo "File already exists: " . $result['url'] . "\n";
    } else {
        echo "New file uploaded: " . $result['url'] . "\n";
        echo "Dimensions: " . $result['dimensions']['width'] . "x" . $result['dimensions']['height'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 6: Document Upload with Custom Naming
// ============================================================================

try {
    $uploader = new FileUploader();
    $uploader->usePublicFolder('uploads/invoices')
             ->setAllowedExtensions(['pdf'])
             ->setAllowedMimeTypes(['application/pdf'])
             ->setMaxSize(5 * 1024 * 1024)
             ->generateUniqueName(false) // Use custom name
             ->organizeByDate(false); // Don't organize by date
    
    $file = UploadedFile::createFromFilesArray($_FILES['invoice']);
    
    // Custom filename based on business logic
    $customName = 'invoice_' . date('Y-m-d') . '_' . uniqid();
    
    $result = $uploader->upload($file, $customName);
    
    echo "Invoice saved: " . $result['name'] . "\n";
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 7: Profile Picture with CDN
// ============================================================================

try {
    $uploader = new FileUploader();
    $uploader->usePublicFolder('uploads/profiles')
             ->imagesOnly(1 * 1024 * 1024) // 1MB
             ->setImageDimensions(maxWidth: 500, maxHeight: 500)
             ->setCdnUrl('https://cdn.example.com'); // Use CDN URL
    
    $file = UploadedFile::createFromFilesArray($_FILES['profile']);
    $result = $uploader->upload($file);
    
    // URL will be: https://cdn.example.com/2024/11/20/filename.jpg
    echo "CDN URL: " . $result['url'] . "\n";
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 8: File Deletion (Safe)
// ============================================================================

try {
    $uploader = new FileUploader();
    $uploader->usePublicFolder('uploads');
    
    // Delete using relative path
    $deleted = $uploader->delete('2024/11/20/abc123.jpg');
    
    if ($deleted) {
        echo "File deleted successfully\n";
    } else {
        echo "File not found or already deleted\n";
    }
    
    // Or delete using full path
    $deleted = $uploader->delete('/var/www/public/uploads/file.jpg');
    
} catch (Exception $e) {
    echo "Delete failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 9: Laravel Integration
// ============================================================================

// In a Laravel Controller
class UploadController extends Controller
{
    public function uploadAvatar(Request $request)
    {
        try {
            $uploader = new FileUploader();
            $uploader->setUploadPath(storage_path('app/public/avatars'))
                     ->setBaseUrl('/storage/avatars')
                     ->imagesOnly(2 * 1024 * 1024);
            
            $file = UploadedFile::createFromFilesArray($request->file('avatar'));
            $result = $uploader->upload($file, null, 'user_' . auth()->id());
            
            // Save to database
            auth()->user()->update(['avatar' => $result['relative_path']]);
            
            return response()->json([
                'success' => true,
                'url' => $result['url']
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

// ============================================================================
// EXAMPLE 10: Symfony Integration
// ============================================================================

// In a Symfony Controller
class UploadController extends AbstractController
{
    #[Route('/upload', name: 'app_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        try {
            $uploader = new FileUploader(null, $this->logger);
            $uploader->setUploadPath($this->getParameter('upload_directory'))
                     ->setBaseUrl('/uploads')
                     ->imagesOnly();
            
            $uploadedFile = $request->files->get('file');
            $file = new UploadedFile([
                'name' => $uploadedFile->getClientOriginalName(),
                'type' => $uploadedFile->getMimeType(),
                'tmp_name' => $uploadedFile->getPathname(),
                'error' => $uploadedFile->getError(),
                'size' => $uploadedFile->getSize()
            ]);
            
            $result = $uploader->upload($file);
            
            return $this->json([
                'success' => true,
                'file' => $result
            ]);
            
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

// ============================================================================
// EXAMPLE 11: Production Setup with Logging
// ============================================================================

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

try {
    // Setup logger
    $logger = new Logger('upload');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/upload.log', Logger::INFO));
    
    // Create uploader with logger
    $uploader = new FileUploader(null, $logger);
    $uploader->usePublicFolder('uploads')
             ->imagesOnly(5 * 1024 * 1024)
             ->setRateLimit(10)
             ->preventDuplicates(true)
             ->organizeByDate(true);
    
    $file = UploadedFile::createFromFilesArray($_FILES['file']);
    $result = $uploader->upload($file, null, $_SERVER['REMOTE_ADDR']);
    
    // All operations are logged automatically
    echo "Upload successful: " . $result['url'] . "\n";
    
} catch (Exception $e) {
    // Error is already logged
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXAMPLE 12: Disable Security Files (If Needed)
// ============================================================================

try {
    $uploader = new FileUploader();
    $uploader->usePublicFolder('uploads')
             ->disableSecurityFiles() // Don't create .htaccess, etc.
             ->imagesOnly();
    
    $file = UploadedFile::createFromFilesArray($_FILES['file']);
    $result = $uploader->upload($file);
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// ============================================================================
// Helper Function
// ============================================================================

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    
    return round($size, 2) . ' ' . $units[$i];
}

// ============================================================================
// Check System Limits
// ============================================================================

echo "Maximum upload size: " . formatBytes(FileUploader::getMaxUploadSize()) . "\n";