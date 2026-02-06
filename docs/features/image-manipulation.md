# Image Manipulation

The `Plugs\Image\Image` class provides a fluent API for professional image processing including filters, resizing, and text rendering.

## Basic Usage

### Loading and Saving

```php
use Plugs\Image\Image;

$image = new Image();
$image->load('path/to/source.jpg')
    ->grayscale()
    ->save('path/to/destination.jpg');
```

## Filters and Adjustments

### Core Filters

```php
$image->grayscale();
$image->sepia();
$image->negative();
$image->blur(2); // 1-10 passes
$image->sharpen();
$image->pixelate(10); // block size
```

### Adjustments

```php
$image->brightness(50); // -255 to 255
$image->contrast(20);   // -100 to 100
$image->colorize(255, 0, 0, 0); // R, G, B, Alpha
```

## Transformations

### Resizing and Cropping

```php
// Width, Height, maintainAspectRatio (default: true)
$image->resize(800, 600);

// Resize to fit within max dimensions without stretching
$image->resizeToFit(800, 600);

// Crop a specific area (x, y, width, height)
$image->crop(100, 100, 400, 300);

// Fit (Resize and Crop to exact dimensions)
$image->fit(200, 200);
```

### Rotation and Flipping

```php
$image->rotate(90);
$image->flipHorizontal();
$image->flipVertical();

// Automatically rotate based on EXIF orientation
$image->autoRotate('path/to/source.jpg');
```

## Advanced Features

### TrueType Text Rendering

```php
$image->textTtf('Hello World', 'fonts/Inter.ttf', 24, 10, 50, [255, 255, 255]);
```

### Positioning Watermarks

```php
$image->watermark('assets/logo.png', 'bottom-right', 20, 50);
// Positions: top-left, top-right, bottom-left, bottom-right, center
```

### Image Information

```php
$info = $image->getInfo();
// [
//    'width' => 1920,
//    'height' => 1080,
//    'mime' => 'image/jpeg',
//    'type' => 2
// ]
```
