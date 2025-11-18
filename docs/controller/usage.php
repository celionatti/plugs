<?php

/**
 * Examples of using file uploads in your controllers
 */

use Plugs\Base\Controller\Controller;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

// ============================================
// Example 1: Simple Image Upload
// ============================================

class ProfileController extends Controller
{
    /**
     * Upload profile picture
     */
    public function uploadAvatar(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Check if file exists
            if (!$this->hasFile($request, 'avatar')) {
                return $this->json([
                    'success' => false,
                    'error' => 'No avatar file uploaded'
                ], 400);
            }

            // Upload with simple helper
            $result = $this->uploadImage($request, 'avatar', 2 * 1024 * 1024, [
                'maxWidth' => 1000,
                'maxHeight' => 1000,
                'minWidth' => 200,
                'minHeight' => 200
            ]);

            // Save to database
            if ($this->db) {
                $userId = $_SESSION['user_id'] ?? 1;
                
                $this->db->table('users')
                    ->where('id', $userId)
                    ->update([
                        'avatar' => $result['relative_path'],
                        'avatar_url' => $result['url']
                    ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'avatar' => $result
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}

// ============================================
// Example 2: Document Upload with Validation
// ============================================

class DocumentController extends Controller
{
    /**
     * Upload document with custom validation
     */
    public function uploadDocument(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Validate request data
            $data = $this->validate($request, [
                'title' => 'required|string|max:255',
                'description' => 'string|max:1000',
                'category' => 'required|in:invoice,contract,report'
            ]);

            // Check file
            if (!$this->hasFile($request, 'document')) {
                return $this->json([
                    'success' => false,
                    'error' => 'No document uploaded'
                ], 400);
            }

            // Upload document
            $result = $this->upload($request, 'document', [
                'preset' => 'documents',
                'maxSize' => 20 * 1024 * 1024, // 20MB
                'allowed' => ['pdf', 'docx', 'xlsx'],
                'organizeByDate' => true,
                'preventDuplicates' => true
            ]);

            // Save to database
            $documentId = $this->db->table('documents')->insertGetId([
                'user_id' => $_SESSION['user_id'] ?? 1,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'filename' => $result['name'],
                'original_name' => $result['original_name'],
                'path' => $result['relative_path'],
                'size' => $result['size'],
                'mime_type' => $result['type'],
                'hash' => $result['hash'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'document_id' => $documentId,
                'file' => $result
            ]);

        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() === 422 ? 422 : 400);
        }
    }
}

// ============================================
// Example 3: Multiple File Upload (Gallery)
// ============================================

class GalleryController extends Controller
{
    /**
     * Upload multiple images
     */
    public function uploadPhotos(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Upload multiple files
            $results = $this->uploadMultiple($request, 'photos', [
                'preset' => 'images',
                'maxSize' => 5 * 1024 * 1024, // 5MB per image
                'maxWidth' => 2000,
                'maxHeight' => 2000,
                'organizeByDate' => true,
                'rateLimit' => 10 // Max 10 uploads per minute
            ]);

            // Save uploaded files to database
            $uploadedIds = [];
            foreach ($results['uploaded'] as $file) {
                $photoId = $this->db->table('gallery_photos')->insertGetId([
                    'user_id' => $_SESSION['user_id'] ?? 1,
                    'filename' => $file['name'],
                    'path' => $file['relative_path'],
                    'url' => $file['url'],
                    'size' => $file['size'],
                    'width' => $file['dimensions']['width'] ?? null,
                    'height' => $file['dimensions']['height'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $uploadedIds[] = $photoId;
            }

            return $this->json([
                'success' => true,
                'message' => sprintf('%d photos uploaded successfully', $results['success_count']),
                'uploaded' => $results['success_count'],
                'failed' => $results['error_count'],
                'photo_ids' => $uploadedIds,
                'errors' => $results['errors'],
                'total_size' => $results['total_size']
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}

// ============================================
// Example 4: Advanced Upload with Custom Configuration
// ============================================

class MediaController extends Controller
{
    /**
     * Upload with custom uploader configuration
     */
    public function uploadMedia(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $file = $this->file($request, 'media');

            if (!$file) {
                return $this->json(['error' => 'No file uploaded'], 400);
            }

            // Get custom uploader and configure
            $uploader = $this->uploader();
            
            // Configure based on media type
            $mediaType = $this->input($request, 'type', 'image');
            
            if ($mediaType === 'image') {
                $uploader->imagesOnly(10 * 1024 * 1024) // 10MB
                    ->setImageDimensions(
                        maxWidth: 3000,
                        maxHeight: 3000
                    )
                    ->allowSvg(false);
            } else {
                $uploader->documentsOnly(50 * 1024 * 1024) // 50MB
                    ->setAllowedExtensions(['pdf', 'doc', 'docx', 'ppt', 'pptx']);
            }

            $uploader->setRateLimit(5)
                ->organizeByDate(true)
                ->preventDuplicates(true);

            // Custom upload path based on user
            $userId = $_SESSION['user_id'] ?? 'guest';
            $uploader->setUploadPath(
                config('upload.path', 'storage/uploads') . '/users/' . $userId
            );

            // Upload with custom name
            $customName = $this->input($request, 'filename');
            $userIdentifier = 'user_' . $userId;
            
            $result = $uploader->upload($file, $customName, $userIdentifier);

            return $this->json([
                'success' => true,
                'file' => $result
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}

// ============================================
// Example 5: File Replacement (Delete Old, Upload New)
// ============================================

class UserController extends Controller
{
    /**
     * Update profile picture (replace old one)
     */
    public function updateProfilePicture(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                return $this->json(['error' => 'Unauthorized'], 401);
            }

            // Get current user data
            $user = $this->db->table('users')->find($userId);

            // Upload new picture
            $result = $this->uploadImage($request, 'picture', 5 * 1024 * 1024, [
                'maxWidth' => 1200,
                'maxHeight' => 1200
            ]);

            // Delete old picture if exists
            if (!empty($user['profile_picture_path'])) {
                $oldPath = config('upload.path') . '/' . $user['profile_picture_path'];
                $this->deleteFile($oldPath);
            }

            // Update database
            $this->db->table('users')
                ->where('id', $userId)
                ->update([
                    'profile_picture_path' => $result['relative_path'],
                    'profile_picture_url' => $result['url'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            return $this->json([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'picture_url' => $result['url']
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}

// ============================================
// Example 6: API Endpoint with File Info
// ============================================

class ApiController extends Controller
{
    /**
     * Get file information before upload
     */
    public function getFileInfo(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $file = $this->file($request, 'file');

            if (!$file) {
                return $this->json(['error' => 'No file provided'], 400);
            }

            $info = [
                'filename' => $file->getClientFilename(),
                'extension' => $file->getClientExtension(),
                'size' => $file->getSize(),
                'size_formatted' => $this->formatBytes($file->getSize()),
                'mime_type' => $file->getMimeType(),
                'is_valid' => $file->isValid(),
                'is_image' => $file->isImage(),
                'has_dangerous_extension' => $file->hasDangerousExtension(),
                'has_suspicious_extension' => $file->hasSuspiciousExtension(),
            ];

            if ($file->isValid() && $file->isImage()) {
                $dimensions = $file->getImageDimensions();
                if ($dimensions) {
                    $info['dimensions'] = [
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height']
                    ];
                }
            }

            return $this->json([
                'success' => true,
                'file_info' => $info
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

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
}

// ============================================
// Example 7: Form with File Upload
// ============================================

class PostController extends Controller
{
    /**
     * Create post with featured image
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        // Show form
        if ($request->getMethod() === 'GET') {
            return $this->view('posts/create');
        }

        // Handle submission
        try {
            // Validate post data
            $data = $this->validate($request, [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'category_id' => 'required|integer'
            ]);

            // Optional image upload
            $imagePath = null;
            $imageUrl = null;

            if ($this->hasFile($request, 'featured_image')) {
                $result = $this->uploadImage($request, 'featured_image', 3 * 1024 * 1024);
                $imagePath = $result['relative_path'];
                $imageUrl = $result['url'];
            }

            // Create post
            $postId = $this->db->table('posts')->insertGetId([
                'user_id' => $_SESSION['user_id'] ?? 1,
                'title' => $data['title'],
                'content' => $data['content'],
                'category_id' => $data['category_id'],
                'featured_image' => $imagePath,
                'featured_image_url' => $imageUrl,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return $this->redirect('/posts/' . $postId);

        } catch (\RuntimeException $e) {
            return $this->view('posts/create', [
                'errors' => json_decode($e->getMessage(), true),
                'old' => $this->all($request)
            ]);
        }
    }
}

// ============================================
// Example 8: AJAX Upload with Progress
// ============================================

class UploadController extends Controller
{
    /**
     * AJAX file upload endpoint
     */
    public function ajaxUpload(ServerRequestInterface $request): ResponseInterface
    {
        // Set headers for AJAX
        header('Content-Type: application/json');

        try {
            // Validate file
            if (!$this->hasFile($request, 'file')) {
                return $this->json([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
            }

            $file = $this->file($request, 'file');

            // Validate file type and size
            $allowedTypes = $this->input($request, 'allowed_types', 'images');
            
            $options = [
                'rateLimit' => 20,
                'organizeByDate' => true
            ];

            if ($allowedTypes === 'images') {
                $options['preset'] = 'images';
                $options['maxSize'] = 5 * 1024 * 1024;
            } else {
                $options['preset'] = 'documents';
                $options['maxSize'] = 10 * 1024 * 1024;
            }

            // Upload
            $result = $this->uploadFile($file, $options);

            return $this->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'file' => [
                    'name' => $result['name'],
                    'url' => $result['url'],
                    'size' => $result['size'],
                    'path' => $result['relative_path']
                ]
            ]);

        } catch (\RuntimeException $e) {
            // Rate limit error
            if (strpos($e->getMessage(), 'rate limit') !== false) {
                return $this->json([
                    'success' => false,
                    'message' => 'Too many uploads. Please wait a moment.',
                    'retry_after' => 60
                ], 429);
            }

            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}