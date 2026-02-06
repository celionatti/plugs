# Filesystem Storage

The Plugs framework provides a powerful filesystem abstraction layer that allows you to work with multiple storage disks using a consistent API.

## Configuration

Storage disks are configured in `config/filesystems.php`. By default, it uses the `local` driver.

```php
return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
        ],
    ],
];
```

## Basic Usage

You can use the `Storage` facade or the `storage()` helper to interact with your disks.

### Storing Files

```php
use Plugs\Facades\Storage;

Storage::put('file.txt', 'Contents');
// Or using helper
storage()->put('file.txt', 'Contents');
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

## Working with Multiple Disks

You can specify which disk to use:

```php
Storage::disk('public')->put('avatar.png', $data);
```

## File Metadata

```php
$size = Storage::size('file.txt');
$time = Storage::lastModified('file.txt');
$url = Storage::url('file.txt');
$full = Storage::fullPath('file.txt'); // Absolute system path
$relative = Storage::path('/abs/path/to/file.txt'); // Convert abs to relative
```

## Directory Operations

```php
Storage::makeDirectory('photos');
Storage::deleteDirectory('photos');
```
