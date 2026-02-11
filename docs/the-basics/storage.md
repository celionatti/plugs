# Filesystem Storage

The Plugs framework provides a powerful filesystem abstraction layer that allows you to work with multiple storage disks using a consistent API.

## Configuration

Storage disks are configured in your application config (defaulting to `Plugs\Config\DefaultConfig`).

> [!NOTE]
> The default disk is set to **`public`** to ensure your uploads are web-accessible by default.

---

## Enabling Public Access

To make files in the `public` disk accessible via the browser, you must create a symbolic link from your public web directory to the storage directory.

Run this command in your terminal:

```bash
php theplugs storage:link
```

This creates a link at `public/storage` pointing to `storage/app/public`.

---

## Basic Usage

You can use the `Storage` facade or the `storage()` helper to interact with your disks.

### Storing Files

```php
use Plugs\Facades\Storage;

// Simple string storage
Storage::put('file.txt', 'Contents');

// Uploaded file storage (automatically generates unique name)
// This will save to storage/app/public/avatars/ because 'public' is default
$path = $request->file('avatar')->store('avatars');
```

### Retrieving Files

```php
$content = Storage::get('file.txt');
```

### Checking Existence

```php
if (Storage::exists('file.txt')) {
    // ...
}
```

### Deleting Files

```php
Storage::delete('file.txt');
```

---

## Easy URL Helpers (Saving & Reading)

We've made it extremely easy to generate URLs for your stored files.

### 1. The `storage_url()` Helper

Use this to specifically get a URL from your storage disks.

```php
// Returns "/storage/blog/images/xyz.jpg"
echo storage_url('blog/images/xyz.jpg');
```

### 2. The Smarter `asset()` Helper

The standard `asset()` helper now automatically checks storage if a file isn't found in your public assets folder.

```html
<!-- If 'blog/image.jpg' isn't in public/, it tries public/storage/blog/image.jpg -->
<img src="<?= asset($blog->image_path) ?>" alt="Blog Image" />
```

### Blog Image Example

**Saving**:

```php
$path = $request->file('blog_image')->store('blog/images');
// $path = "blog/images/hashed_name.jpg"
```

**Displaying**:

```html
<img src="<?= asset($path) ?>" alt="Image" />
<!-- OR -->
<img src="<?= storage_url($path) ?>" alt="Image" />
```

---

## Advanced Operations

### Working with Multiple Disks

```php
Storage::disk('s3')->put('backup.zip', $data);
```

### File Visibility

Toggle between `public` and `private`.

```php
Storage::setVisibility('confidential.pdf', 'private');
$visibility = Storage::getVisibility('confidential.pdf');
```

### Copying & Moving

```php
Storage::copy('temp/photo.jpg', 'gallery/photo.jpg');
Storage::move('old/folder/file.txt', 'new/folder/file.txt');
```

### Appending & Prepending

```php
Storage::append('log.txt', "New entry\n");
Storage::prepend('log.txt', "Start of log\n");
```

### File Metadata

```php
$size = Storage::size('file.txt');
$mime = Storage::mimeType('file.txt');
$time = Storage::lastModified('file.txt');
```

---

## Directory Operations

```php
Storage::makeDirectory('projects/custom');
Storage::deleteDirectory('projects/temp');
```
