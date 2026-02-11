# Filesystem Storage

The Plugs framework provides a powerful filesystem abstraction layer that allows you to work with multiple storage disks using a consistent API.

## Configuration

Storage disks are configured in your application config (defaulting to `Plugs\Config\DefaultConfig`).

> [!NOTE]
> The default disk is set to **`public`** to ensure your uploads are web-accessible by default.

---

## Enabling Public Access

To make files in the `public` disk accessible via the browser, you must create a symbolic link from your public web directory to the storage directory.

### Local Development

Run this command in your terminal:

```bash
php theplugs storage:link
```

### Production Deployment

For production environments (Shared Hosting, VPS), see the [Storage Link Deployment Guide](../deployment/storage-link.md).

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

---

## S3 Storage

The Plugs framework also supports Amazon S3 (and S3-compatible services like DigitalOcean Spaces or Minio).

### 1. Requirements

To use the S3 driver, you must install the AWS SDK for PHP via Composer:

```bash
composer require aws/aws-sdk-php
```

### 2. Configuration

Add an `s3` disk to your `disks` configuration in `DefaultConfig.php`:

```php
's3' => [
    'driver' => 's3',
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url'    => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'), // Useful for S3-compatible services
    'root'   => 'my-app-prefix',
],
```

### 3. Usage

The API is exactly the same as the local disk. You just specify the `s3` disk:

```php
use Plugs\Facades\Storage;

// Upload to S3
$path = $request->file('avatar')->store('avatars', 's3');

// Get S3 URL
$url = Storage::disk('s3')->url($path);

// Check if file exists on S3
if (Storage::disk('s3')->exists('avatars/user.jpg')) {
    // ...
}
```

---

## Directory Operations

```php
Storage::makeDirectory('projects/custom');
Storage::deleteDirectory('projects/temp');
```
