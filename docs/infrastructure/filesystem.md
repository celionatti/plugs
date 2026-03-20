# Filesystem & Storage

Plugs provides a powerful abstraction layer for file storage, allowing you to interact with local or cloud storage using a unified API.

---

## 1. The Storage Facade

The `Storage` facade is the primary way to interact with your files.

```php
use Plugs\Facades\Storage;

// Basic Operations
Storage::put('file.txt', 'Contents');
$content = Storage::get('file.txt');
Storage::delete('file.txt');

// Public URLs
$url = Storage::url('avatars/user_1.jpg');
```

---

## 2. File Uploads

### Simple Uploads
Handle files directly from the `Request` object:

```php
public function upload(Request $request)
{
    if ($request->hasFile('avatar')) {
        $path = $request->file('avatar')->store('avatars');
        return "Stored at: {$path}";
    }
}
```

### Advanced Uploader
For stricter validation and image processing, use the `FileUploader` service:

```php
use Plugs\Upload\FileUploader;

$result = FileUploader::make()
    ->imagesOnly()
    ->setMaxSize(2048) // 2MB
    ->stripMetadata(true) // Remove EXIF for privacy
    ->upload($request->file('photo'));
```

---

## 3. Image Optimization
Plugs can automatically compress and strip metadata from images during upload to save space and improve performance.

```php
$request->file('photo')->compress(80)->store('photos');
```

---

## Next Steps
Monitor your application with [Logging](./logging.md).
