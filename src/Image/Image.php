<?php

declare(strict_types=1);

namespace Plugs\Image;

use GdImage;

/*
|--------------------------------------------------------------------------
| Image Class
|--------------------------------------------------------------------------
|
| This class provides image manipulation with improved error handling
| and memory management for production use. Also using GDImage extension
*/

class Image
{
    private ?GdImage $image = null;
    private $width;
    private $height;
    private $type;
    private $quality = 90;

    /**
     * Load image from file
     */
    public function load(string $path): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Image not found: {$path}");
        }

        if (!is_readable($path)) {
            throw new \RuntimeException("Image is not readable: {$path}");
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw new \RuntimeException("Invalid image file: {$path}");
        }

        $this->width = $info[0];
        $this->height = $info[1];
        $this->type = $info[2];

        // Free existing image if any
        if ($this->image !== null) {
            imagedestroy($this->image);
        }

        // Check memory availability
        $this->checkMemory($info[0], $info[1]);

        $image = null;

        try {
            switch ($this->type) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($path);

                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($path);

                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($path);

                    break;
                case IMAGETYPE_WEBP:
                    if (!function_exists('imagecreatefromwebp')) {
                        throw new \RuntimeException("WebP support not available");
                    }
                    $image = imagecreatefromwebp($path);

                    break;
                case IMAGETYPE_BMP:
                    if (function_exists('imagecreatefrombmp')) {
                        $image = imagecreatefrombmp($path);
                    } else {
                        throw new \RuntimeException("BMP support not available");
                    }

                    break;
                default:
                    throw new \RuntimeException("Unsupported image type: " . image_type_to_mime_type($this->type));
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to load image: " . $e->getMessage());
        }

        if (!$image) {
            throw new \RuntimeException("Failed to create image resource from: {$path}");
        }

        $this->image = $image;

        return $this;
    }

    /**
     * Create from resource
     */
    public function fromResource($resource): self
    {
        if (!$resource instanceof GdImage) {
            throw new \InvalidArgumentException("Invalid GD resource provided");
        }

        $this->image = $resource;
        $this->width = imagesx($resource);
        $this->height = imagesy($resource);
        $this->type = IMAGETYPE_PNG; // Default type for resources

        return $this;
    }

    /**
     * Set quality (1-100)
     */
    public function quality(int $quality): self
    {
        $this->quality = max(1, min(100, $quality));

        return $this;
    }

    /**
     * Resize image maintaining aspect ratio
     */
    public function resize(int $width, int $height, bool $aspectRatio = true): self
    {
        $this->ensureImageLoaded();

        if ($aspectRatio) {
            $ratio = min($width / $this->width, $height / $this->height);
            $newWidth = (int) ($this->width * $ratio);
            $newHeight = (int) ($this->height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // Check memory before creating new image
        $this->checkMemory($newWidth, $newHeight);

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($newImage === false) {
            throw new \RuntimeException('Failed to create new image resource');
        }

        // Preserve transparency for PNG and GIF
        $this->preserveTransparency($newImage);

        $result = imagecopyresampled(
            $newImage,
            $this->image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $this->width,
            $this->height
        );

        if (!$result) {
            imagedestroy($newImage);

            throw new \RuntimeException('Failed to resize image');
        }

        imagedestroy($this->image);
        $this->image = $newImage;
        $this->width = $newWidth;
        $this->height = $newHeight;

        return $this;
    }

    /**
     * Resize to fit within max dimensions
     */
    public function resizeToFit(int $maxWidth, int $maxHeight): self
    {
        $this->ensureImageLoaded();

        if ($this->width <= $maxWidth && $this->height <= $maxHeight) {
            return $this; // Already fits
        }

        return $this->resize($maxWidth, $maxHeight, true);
    }

    /**
     * Resize to cover dimensions (will crop if needed)
     */
    public function fit(int $width, int $height): self
    {
        $this->ensureImageLoaded();

        $ratio = max($width / $this->width, $height / $this->height);

        $newWidth = (int) ($this->width * $ratio);
        $newHeight = (int) ($this->height * $ratio);

        $tempImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($tempImage === false) {
            throw new \RuntimeException('Failed to create temporary image');
        }

        $this->preserveTransparency($tempImage);

        imagecopyresampled(
            $tempImage,
            $this->image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $this->width,
            $this->height
        );

        // Crop to exact size
        $newImage = imagecreatetruecolor($width, $height);

        if ($newImage === false) {
            imagedestroy($tempImage);

            throw new \RuntimeException('Failed to create final image');
        }

        $this->preserveTransparency($newImage);

        $cropX = (int) (($newWidth - $width) / 2);
        $cropY = (int) (($newHeight - $height) / 2);

        imagecopy($newImage, $tempImage, 0, 0, $cropX, $cropY, $width, $height);

        imagedestroy($this->image);
        imagedestroy($tempImage);

        $this->image = $newImage;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Crop image
     */
    public function crop(int $x, int $y, int $width, int $height): self
    {
        $this->ensureImageLoaded();

        // Validate crop coordinates
        if ($x < 0 || $y < 0 || $x + $width > $this->width || $y + $height > $this->height) {
            throw new \InvalidArgumentException('Crop coordinates exceed image boundaries');
        }

        $newImage = imagecreatetruecolor($width, $height);

        if ($newImage === false) {
            throw new \RuntimeException('Failed to create cropped image');
        }

        $this->preserveTransparency($newImage);

        imagecopy($newImage, $this->image, 0, 0, $x, $y, $width, $height);

        imagedestroy($this->image);
        $this->image = $newImage;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Rotate image
     */
    public function rotate(float $angle, int $bgColor = 0): self
    {
        $this->ensureImageLoaded();

        $newImage = imagerotate($this->image, $angle, $bgColor);

        if ($newImage === false) {
            throw new \RuntimeException('Failed to rotate image');
        }

        imagedestroy($this->image);
        $this->image = $newImage;
        $this->width = imagesx($newImage);
        $this->height = imagesy($newImage);

        return $this;
    }

    /**
     * Flip image horizontally
     */
    public function flipHorizontal(): self
    {
        $this->ensureImageLoaded();
        imageflip($this->image, IMG_FLIP_HORIZONTAL);

        return $this;
    }

    /**
     * Flip image vertically
     */
    public function flipVertical(): self
    {
        $this->ensureImageLoaded();
        imageflip($this->image, IMG_FLIP_VERTICAL);

        return $this;
    }

    /**
     * Convert to grayscale
     */
    public function grayscale(): self
    {
        $this->ensureImageLoaded();
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);

        return $this;
    }

    /**
     * Adjust brightness (-255 to 255)
     */
    public function brightness(int $level): self
    {
        $this->ensureImageLoaded();
        $level = max(-255, min(255, $level));
        imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $level);

        return $this;
    }

    /**
     * Adjust contrast (-100 to 100)
     */
    public function contrast(int $level): self
    {
        $this->ensureImageLoaded();
        $level = max(-100, min(100, $level));
        imagefilter($this->image, IMG_FILTER_CONTRAST, $level);

        return $this;
    }

    /**
     * Apply blur
     */
    public function blur(int $passes = 1): self
    {
        $this->ensureImageLoaded();
        $passes = max(1, min(10, $passes)); // Limit passes to prevent excessive processing

        for ($i = 0; $i < $passes; $i++) {
            imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return $this;
    }

    /**
     * Sharpen image
     */
    public function sharpen(): self
    {
        $this->ensureImageLoaded();
        imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);

        return $this;
    }

    /**
     * Add watermark
     */
    public function watermark(string $watermarkPath, string $position = 'bottom-right', int $margin = 10, int $opacity = 100): self
    {
        $this->ensureImageLoaded();

        if (!file_exists($watermarkPath)) {
            throw new \RuntimeException("Watermark image not found: {$watermarkPath}");
        }

        $watermark = @imagecreatefrompng($watermarkPath);

        if ($watermark === false) {
            throw new \RuntimeException('Failed to load watermark image');
        }

        $wmWidth = imagesx($watermark);
        $wmHeight = imagesy($watermark);

        // Calculate position
        [$x, $y] = $this->calculateWatermarkPosition($position, $wmWidth, $wmHeight, $margin);

        // Apply opacity
        if ($opacity < 100) {
            $this->applyWatermarkOpacity($watermark, $opacity);
        }

        imagecopy($this->image, $watermark, $x, $y, 0, 0, $wmWidth, $wmHeight);
        imagedestroy($watermark);

        return $this;
    }

    /**
     * Calculate watermark position
     */
    private function calculateWatermarkPosition(string $position, int $wmWidth, int $wmHeight, int $margin): array
    {
        switch ($position) {
            case 'top-left':
                return [$margin, $margin];
            case 'top-right':
                return [$this->width - $wmWidth - $margin, $margin];
            case 'bottom-left':
                return [$margin, $this->height - $wmHeight - $margin];
            case 'bottom-right':
                return [$this->width - $wmWidth - $margin, $this->height - $wmHeight - $margin];
            case 'center':
                return [
                    (int) (($this->width - $wmWidth) / 2),
                    (int) (($this->height - $wmHeight) / 2),
                ];
            default:
                throw new \InvalidArgumentException("Invalid watermark position: {$position}");
        }
    }

    /**
     * Apply opacity to watermark
     */
    private function applyWatermarkOpacity($watermark, int $opacity): void
    {
        $opacity = max(0, min(100, $opacity));
        $alpha = (100 - $opacity) * 1.27;
        imagefilter($watermark, IMG_FILTER_COLORIZE, 0, 0, 0, (int) $alpha);
    }

    /**
     * Add text to image
     */
    public function text(string $text, int $x, int $y, int $size = 20, array $color = [0, 0, 0]): self
    {
        $this->ensureImageLoaded();

        $textColor = imagecolorallocate($this->image, $color[0], $color[1], $color[2]);

        // Use built-in font if TTF not available
        imagestring($this->image, $size, $x, $y, $text, $textColor);

        return $this;
    }

    /**
     * Add text to image using TrueType font
     */
    public function textTtf(string $text, string $fontPath, int $size, int $x, int $y, array $color = [0, 0, 0], float $angle = 0): self
    {
        $this->ensureImageLoaded();

        if (!function_exists('imagettftext')) {
            throw new \RuntimeException('FreeType support is not enabled in your PHP installation');
        }

        if (!file_exists($fontPath)) {
            throw new \RuntimeException("Font file not found: {$fontPath}");
        }

        $textColor = imagecolorallocate($this->image, $color[0], $color[1], $color[2]);

        if ($textColor === false) {
            throw new \RuntimeException('Failed to allocate text color');
        }

        imagettftext($this->image, $size, $angle, $x, $y, $textColor, $fontPath, $text);

        return $this;
    }

    /**
     * Apply sepia filter
     */
    public function sepia(): self
    {
        $this->ensureImageLoaded();
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        imagefilter($this->image, IMG_FILTER_COLORIZE, 90, 60, 40);

        return $this;
    }

    /**
     * Apply negative filter
     */
    public function negative(): self
    {
        $this->ensureImageLoaded();
        imagefilter($this->image, IMG_FILTER_NEGATE);

        return $this;
    }

    /**
     * Pixelate image
     */
    public function pixelate(int $blockSize = 10): self
    {
        $this->ensureImageLoaded();
        imagefilter($this->image, IMG_FILTER_PIXELATE, $blockSize);

        return $this;
    }

    /**
     * Colorize image
     */
    public function colorize(int $red, int $green, int $blue, int $alpha = 0): self
    {
        $this->ensureImageLoaded();
        imagefilter($this->image, IMG_FILTER_COLORIZE, $red, $green, $blue, $alpha);

        return $this;
    }

    /**
     * Automatically rotate image based on EXIF data
     */
    public function autoRotate(string $path): self
    {
        $this->ensureImageLoaded();

        if (!function_exists('exif_read_data')) {
            return $this;
        }

        $exif = @exif_read_data($path);
        if (!$exif || !isset($exif['Orientation'])) {
            return $this;
        }

        switch ($exif['Orientation']) {
            case 3:
                $this->rotate(180);

                break;
            case 6:
                $this->rotate(-90);

                break;
            case 8:
                $this->rotate(90);

                break;
        }

        return $this;
    }

    /**
     * Get image information
     */
    public function getInfo(): array
    {
        $this->ensureImageLoaded();

        return [
            'width' => $this->width,
            'height' => $this->height,
            'type' => $this->type,
            'mime' => image_type_to_mime_type($this->type),
        ];
    }

    /**
     * Save image to file
     */
    public function save(string $path, ?int $type = null): bool
    {
        $this->ensureImageLoaded();

        $type = $type ?? $this->type ?? IMAGETYPE_JPEG;

        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new \RuntimeException("Directory is not writable: {$directory}");
        }

        $result = false;

        try {
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $result = imagejpeg($this->image, $path, $this->quality);

                    break;
                case IMAGETYPE_PNG:
                    $pngQuality = (int) (9 - ($this->quality / 100 * 9));
                    $result = imagepng($this->image, $path, $pngQuality);

                    break;
                case IMAGETYPE_GIF:
                    $result = imagegif($this->image, $path);

                    break;
                case IMAGETYPE_WEBP:
                    if (!function_exists('imagewebp')) {
                        throw new \RuntimeException("WebP support not available");
                    }
                    $result = imagewebp($this->image, $path, $this->quality);

                    break;
                default:
                    throw new \RuntimeException("Unsupported image type for saving");
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to save image: " . $e->getMessage());
        }

        if (!$result) {
            throw new \RuntimeException("Failed to save image to: {$path}");
        }

        // Set file permissions
        @chmod($path, 0644);

        // Verify file was saved
        if (!file_exists($path) || filesize($path) === 0) {
            throw new \RuntimeException("Image file was not saved correctly");
        }

        return true;
    }

    /**
     * Output image to browser
     */
    public function output(?int $type = null): void
    {
        $this->ensureImageLoaded();

        $type = $type ?? $this->type ?? IMAGETYPE_JPEG;

        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }

        switch ($type) {
            case IMAGETYPE_JPEG:
                header('Content-Type: image/jpeg');
                imagejpeg($this->image, null, $this->quality);

                break;
            case IMAGETYPE_PNG:
                header('Content-Type: image/png');
                $pngQuality = (int) (9 - ($this->quality / 100 * 9));
                imagepng($this->image, null, $pngQuality);

                break;
            case IMAGETYPE_GIF:
                header('Content-Type: image/gif');
                imagegif($this->image);

                break;
            case IMAGETYPE_WEBP:
                header('Content-Type: image/webp');
                imagewebp($this->image, null, $this->quality);

                break;
            default:
                throw new \RuntimeException("Unsupported image type for output");
        }
    }

    /**
     * Get image dimensions
     */
    public function getDimensions(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * Get image resource
     */
    public function getResource(): ?GdImage
    {
        return $this->image;
    }

    /**
     * Preserve transparency when creating new images
     */
    private function preserveTransparency($image): void
    {
        if ($this->type === IMAGETYPE_PNG || $this->type === IMAGETYPE_GIF) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
        }
    }

    /**
     * Check if there's enough memory to process image
     */
    private function checkMemory(int $width, int $height): void
    {
        $memoryNeeded = $width * $height * 4 * 1.8; // Rough estimate with safety margin
        $memoryLimit = $this->getMemoryLimit();

        if ($memoryLimit > 0 && $memoryNeeded > $memoryLimit - memory_get_usage()) {
            throw new \RuntimeException(
                'Insufficient memory to process image. Required: ' .
                round($memoryNeeded / 1024 / 1024, 2) . 'MB'
            );
        }
    }

    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return -1; // No limit
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Ensure image is loaded
     */
    private function ensureImageLoaded(): void
    {
        if ($this->image === null) {
            throw new \RuntimeException('No image loaded. Call load() first.');
        }
    }

    /**
     * Destroy image resource
     */
    public function __destruct()
    {
        if ($this->image !== null) {
            imagedestroy($this->image);
        }
    }
}
