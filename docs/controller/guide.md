# File Upload System - Quick Reference Guide

## 📁 Files Updated

1. **FileUploader.php** - Apply minimal critical fixes (5 changes)
2. **UploadedFile.php** - Keep as-is (already production ready)
3. **Controller.php** - Updated with new upload methods
4. **ServerRequest.php** - Already compatible, no changes needed

---

## 🚀 Quick Start in Controllers

### Simple Image Upload

```php
public function uploadAvatar(ServerRequestInterface $request): ResponseInterface
{
    $result = $this->uploadImage($request, 'avatar', 2 * 1024 * 1024);
    
    return $this->json([
        'success' => true,
        'url' => $result['url']
    ]);
}
```

### Document Upload

```php
public function uploadDocument(ServerRequestInterface $request): ResponseInterface
{
    $result = $this->uploadDocument($request, 'document', 20 * 1024 * 1024);
    
    return $this->json(['success' => true, 'file' => $result]);
}
```

### Multiple Files

```php
public function uploadGallery(ServerRequestInterface $request): ResponseInterface
{
    $results = $this->uploadMultiple($request, 'photos', [
        'preset' => 'images',
        'maxSize' => 5 * 1024 * 1024
    ]);
    
    return $this->json($results);
}
```

---

## 🎯 Controller Methods

### Basic Methods

| Method | Description | Example |
|--------|-------------|---------|
| `file()` | Get single uploaded file | `$file = $this->file($request, 'avatar')` |
| `hasFile()` | Check if file exists | `if ($this->hasFile($request, 'photo'))` |
| `files()` | Get multiple files | `$files = $this->files($request, 'photos')` |

### Upload Methods

| Method | Description | Use Case |
|--------|-------------|----------|
| `upload()` | Upload with options | Custom configuration |
| `uploadImage()` | Quick image upload | Profile pictures, avatars |
| `uploadDocument()` | Quick document upload | PDFs, Word docs |
| `uploadMultiple()` | Upload multiple files | Gallery, attachments |
| `uploadFile()` | Upload UploadedFile instance | Direct file upload |

### Helper Methods

| Method | Description |
|--------|-------------|
| `uploader()` | Get FileUploader instance |
| `deleteFile()` | Delete uploaded file |
| `input()` | Get form input |
| `has()` | Check if input exists |

---

## ⚙️ Upload Options

### Common Options

```php
$options = [
    'preset' => 'images',           // 'images' or 'documents'
    'path' => 'storage/uploads',    // Custom upload path
    'allowed' => ['jpg', 'png'],    // Allowed extensions
    'mimes' => ['image/jpeg'],      // Allowed MIME types
    'maxSize' => 5242880,           // 5MB in bytes
    'minSize' => 1024,              // 1KB
    'uniqueName' => true,           // Generate unique names
    'organizeByDate' => true,       // YYYY/MM/DD structure
    'preventDuplicates' => true,    // Block duplicate files
    'allowSvg' => false,            // Allow SVG (security risk)
    'rateLimit' => 10,              // Uploads per minute
    'name' => 'custom.jpg',         // Custom filename
];
```

### Image-Specific Options

```php
$options = [
    'maxWidth' => 2000,
    'maxHeight' => 2000,
    'minWidth' => 100,
    'minHeight' => 100,
];
```

---

## 📋 Complete Example

```php
class ProfileController extends Controller
{
    public function updateAvatar(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Validate form data
            $data = $this->validate($request, [
                'username' => 'required|string|max:50'
            ]);

            // Check if file uploaded
            if (!$this->hasFile($request, 'avatar')) {
                return $this->json(['error' => 'No avatar uploaded'], 400);
            }

            // Upload with custom options
            $result = $this->upload($request, 'avatar', [
                'preset' => 'images',
                'maxSize' => 2 * 1024 * 1024, // 2MB
                'maxWidth' => 500,
                'maxHeight' => 500,
                'minWidth' => 100,
                'minHeight' => 100,
                'rateLimit' => 5
            ]);

            // Delete old avatar
            $user = $this->db->table('users')->find($_SESSION['user_id']);
            if ($user && $user['avatar_path']) {
                $this->deleteFile($user['avatar_path']);
            }

            // Save to database
            $this->db->table('users')
                ->where('id', $_SESSION['user_id'])
                ->update([
                    'username' => $data['username'],
                    'avatar_path' => $result['relative_path'],
                    'avatar_url' => $result['url'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            return $this->json([
                'success' => true,
                'message' => 'Avatar updated successfully',
                'avatar_url' => $result['url']
            ]);

        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

---

## 🔒 Security Features

### Automatic Protection

- ✅ Upload verification (`is_uploaded_file()`)
- ✅ Dangerous extension blocking (`.php`, `.exe`, etc.)
- ✅ Double extension detection (`.php.jpg`)
- ✅ Null byte protection
- ✅ MIME type validation
- ✅ Image integrity checking
- ✅ Rate limiting per user/IP
- ✅ Directory traversal prevention
- ✅ Atomic file operations (prevents race conditions)

### Manual Security

```php
// Check file before upload
$file = $this->file($request, 'document');

if ($file->hasDangerousExtension()) {
    throw new RuntimeException('Dangerous file type');
}

if ($file->hasSuspiciousExtension()) {
    throw new RuntimeException('Suspicious file detected');
}

if (!$file->hasValidSignature()) {
    throw new RuntimeException('File signature mismatch');
}
```

---

## 🌐 HTML Forms

### Single File Upload

```html
<form action="/upload" method="POST" enctype="multipart/form-data">
    <input type="file" name="avatar" accept="image/*" required>
    <button type="submit">Upload</button>
</form>
```

### Multiple Files

```html
<form action="/upload-multiple" method="POST" enctype="multipart/form-data">
    <input type="file" name="photos[]" multiple accept="image/*" required>
    <button type="submit">Upload Photos</button>
</form>
```

### Client-Side Validation

```javascript
document.querySelector('input[type="file"]').addEventListener('change', (e) => {
    const file = e.target.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (file.size > maxSize) {
        alert('File too large');
        e.target.value = '';
    }
});
```

---

## 📊 Upload Result

```php
[
    'name' => '20a1b2c3d4e5f6g7.jpg',
    'original_name' => 'my-photo.jpg',
    'path' => '/full/path/to/2025/11/18/20a1b2c3d4e5f6g7.jpg',
    'relative_path' => '2025/11/18/20a1b2c3d4e5f6g7.jpg',
    'url' => '/uploads/2025/11/18/20a1b2c3d4e5f6g7.jpg',
    'size' => 1048576, // bytes
    'type' => 'image/jpeg',
    'extension' => 'jpg',
    'uploaded_at' => '2025-11-18 14:30:00',
    'uploaded_timestamp' => 1700317800,
    'hash' => 'sha256hash...', // if preventDuplicates enabled
    'dimensions' => [ // for images
        'width' => 1920,
        'height' => 1080
    ]
]
```

---

## ⚡ Configuration File

Create `config/upload.php`:

```php
return [
    'path' => BASE_PATH . '/storage/uploads',
    'max_size' => 10 * 1024 * 1024,
    
    'images' => [
        'max_size' => 5 * 1024 * 1024,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_width' => 2000,
        'max_height' => 2000,
    ],
    
    'security' => [
        'allow_svg' => false,
        'rate_limit' => 10,
        'prevent_duplicates' => true,
        'organize_by_date' => true,
    ],
];
```

---

## 🚨 Error Handling

```php
try {
    $result = $this->upload($request, 'file', $options);
} catch (\RuntimeException $e) {
    // Handle specific errors
    if (strpos($e->getMessage(), 'rate limit') !== false) {
        return $this->json(['error' => 'Too many uploads'], 429);
    }
    
    if (strpos($e->getMessage(), 'exceeds maximum') !== false) {
        return $this->json(['error' => 'File too large'], 413);
    }
    
    return $this->json(['error' => $e->getMessage()], 400);
}
```

---

## 📝 Database Integration

```php
// Save upload info to database
$uploadId = $this->db->table('uploads')->insertGetId([
    'user_id' => $_SESSION['user_id'],
    'filename' => $result['name'],
    'original_name' => $result['original_name'],
    'path' => $result['relative_path'],
    'url' => $result['url'],
    'size' => $result['size'],
    'mime_type' => $result['type'],
    'hash' => $result['hash'] ?? null,
    'created_at' => $result['uploaded_at']
]);
```

### Database Schema

```sql
CREATE TABLE uploads (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    url VARCHAR(500) NOT NULL,
    size BIGINT NOT NULL,
    mime_type VARCHAR(100),
    hash VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_hash (hash)
);
```

---

## ✅ Testing Checklist

Before production:

- [ ] Test with various file types
- [ ] Test file size limits
- [ ] Test rate limiting
- [ ] Test with malicious files (`.php.jpg`, etc.)
- [ ] Test concurrent uploads (race conditions)
- [ ] Test with corrupted images
- [ ] Verify file permissions (should be 644)
- [ ] Check upload directory is not executable
- [ ] Test error handling
- [ ] Monitor upload performance

---

## 🎓 Best Practices

1. **Always validate on server-side** - Never trust client validation
2. **Use rate limiting** - Prevent abuse
3. **Organize by date** - Easier management
4. **Store in database** - Track all uploads
5. **Delete old files** - When replacing
6. **Check file size** - Before upload starts
7. **Use presets** - For consistency
8. **Log uploads** - For monitoring
9. **Disable SVG** - Unless absolutely necessary
10. **Move uploads outside webroot** - For better security
