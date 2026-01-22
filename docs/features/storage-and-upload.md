# Storage & File Uploads

PLUGS provides a powerful, simplified abstraction for file storage and uploads, enabling you to switch between local and cloud storage (coming soon) without changing your application code.

## Configuration

Your filesystem configuration is located at `config/filesystems.php`. By default, PLUGS uses the `local` driver, storing files in `storage/app`.

To make these files accessible via the web, you must create a symbolic link:

```bash
php theplugs storage:link
```

This creates a link from `public/storage` to `storage/app/public`.

## The Storage Facade

The `Storage` facade is your primary gateway to filesystem operations.

### Basic Usage

```php
use Plugs\Facades\Storage;

// Write to a file
Storage::put('example.txt', 'Contents of the file');

// Read a file
$content = Storage::get('example.txt');

// Check existence
if (Storage::exists('example.txt')) {
    // ...
}

// Delete a file
Storage::delete('example.txt');
```

### File Downloads

You can easily trigger a file download response:

```php
return Storage::download('invoices/invoice_102.pdf');

// With custom filename
return Storage::download('invoices/invoice_102.pdf', 'MyInvoice.pdf');
```

### URLs

Get the public URL for a file:

```php
$url = Storage::url('avatars/user_1.jpg');
// Result: /storage/avatars/user_1.jpg
```

---

## File Uploads

PLUGS offers two ways to handle uploads: the simple `UploadedFile` methods and the advanced `FileUploader` class.

### 1. Simple Uploads (Controller)

When you receive a file in a controller, you can store it directly using the `store` methods.

```php
use Plugs\Http\Request;

public function updateAvatar(Request $request)
{
    if ($request->hasFile('avatar')) {
        $file = $request->file('avatar');

        // Store in 'avatars' folder (auto-generated ID name)
        $path = $file->store('avatars');

        // Store with public visibility (accessible via browser)
        $path = $file->storePublicly('avatars');
        
        // Store with a custom name
        $path = $file->storeAs('avatars', 'user_123.jpg');

        // Save $path to database...
        return "File stored at: " . $path;
    }
}
```

### 2. Advanced Uploads (FileUploader)

For more control (validation, security, constraints), use the `FileUploader` class. This is perfect for robust applications.

#### Fluent API

```php
use Plugs\Upload\FileUploader;

public function uploadDocument(Request $request)
{
    $file = $request->file('document');

    try {
        $result = FileUploader::make()
            ->documentsOnly()           // Restrict to PDF, DOC, etc.
            ->setMaxSize(5 * 1024 * 1024) // 5MB limit
            ->upload($file);
            
        return json_encode($result);
        
    } catch (\Exception $e) {
        return response($e->getMessage(), 400);
    }
}
```

#### Image Upload Example

```php
$result = FileUploader::make()
    ->imagesOnly()
    ->generateUniqueName(true)
    ->upload($file);

// $result contains:
// [
//    'name' => 'abcd123.jpg',
//    'path' => 'uploads/2026/01/22/abcd123.jpg',
//    'url' => '/storage/uploads/2026/01/22/abcd123.jpg',
//    'size' => 1024,
//    ...
// ]
```

#### Security Features
The `FileUploader` automatically:
- Validates strict MIME types.
- Blocks dangerous extensions (php, exe, sh, etc.).
- Verifies image integrity (detects fake images).
- Prevents directory clutter by securing only the root upload folder.

### Helper Functions

You can use the global `storage_path()` helper to get the absolute path to your storage directory.

```php
$path = storage_path('app/public/file.txt');
```
