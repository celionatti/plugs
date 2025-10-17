<?php

declare(strict_types=1);

namespace Plugs\Image;

use GdImage;

/*
|--------------------------------------------------------------------------
| Image Class
|--------------------------------------------------------------------------
|
| This class provides basic image manipulation functionalities such as
| resizing, cropping, and saving images. It leverages the GD library for
| image processing tasks.
*/

class Image
{
    private ?GdImage $image;
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
        
        $info = getimagesize($path);
        
        if ($info === false) {
            throw new \RuntimeException("Invalid image file: {$path}");
        }
        
        $this->width = $info[0];
        $this->height = $info[1];
        $this->type = $info[2];
        
        switch ($this->type) {
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($path);
                break;
            case IMAGETYPE_WEBP:
                $this->image = imagecreatefromwebp($path);
                break;
            default:
                throw new \RuntimeException("Unsupported image type");
        }
        
        return $this;
    }
    
    /**
     * Create from resource
     */
    public function fromResource($resource): self
    {
        $this->image = $resource;
        $this->width = imagesx($resource);
        $this->height = imagesy($resource);
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
     * Resize image
     */
    public function resize(int $width, int $height, bool $aspectRatio = true): self
    {
        if ($aspectRatio) {
            $ratio = min($width / $this->width, $height / $this->height);
            $newWidth = (int) ($this->width * $ratio);
            $newHeight = (int) ($this->height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($this->type === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        imagecopyresampled(
            $newImage,
            $this->image,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $this->width,
            $this->height
        );
        
        imagedestroy($this->image);
        $this->image = $newImage;
        $this->width = $newWidth;
        $this->height = $newHeight;
        
        return $this;
    }
    
    /**
     * Fit image to dimensions (crops if needed)
     */
    public function fit(int $width, int $height): self
    {
        $ratio = max($width / $this->width, $height / $this->height);
        
        $newWidth = (int) ($this->width * $ratio);
        $newHeight = (int) ($this->height * $ratio);
        
        $tempImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency
        if ($this->type === IMAGETYPE_PNG) {
            imagealphablending($tempImage, false);
            imagesavealpha($tempImage, true);
        }
        
        imagecopyresampled(
            $tempImage,
            $this->image,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $this->width,
            $this->height
        );
        
        // Crop to exact size
        $newImage = imagecreatetruecolor($width, $height);
        
        if ($this->type === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        $cropX = ($newWidth - $width) / 2;
        $cropY = ($newHeight - $height) / 2;
        
        imagecopy($newImage, $tempImage, 0, 0, (int) $cropX, (int) $cropY, $width, $height);
        
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
        $newImage = imagecreatetruecolor($width, $height);
        
        if ($this->type === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
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
        $newImage = imagerotate($this->image, $angle, $bgColor);
        
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
        imageflip($this->image, IMG_FLIP_HORIZONTAL);
        return $this;
    }
    
    /**
     * Flip image vertically
     */
    public function flipVertical(): self
    {
        imageflip($this->image, IMG_FLIP_VERTICAL);
        return $this;
    }
    
    /**
     * Convert to grayscale
     */
    public function grayscale(): self
    {
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        return $this;
    }
    
    /**
     * Adjust brightness (-255 to 255)
     */
    public function brightness(int $level): self
    {
        imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $level);
        return $this;
    }
    
    /**
     * Adjust contrast (-100 to 100)
     */
    public function contrast(int $level): self
    {
        imagefilter($this->image, IMG_FILTER_CONTRAST, $level);
        return $this;
    }
    
    /**
     * Apply blur
     */
    public function blur(int $passes = 1): self
    {
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
        imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);
        return $this;
    }
    
    /**
     * Add watermark
     */
    public function watermark(string $watermarkPath, string $position = 'bottom-right', int $margin = 10): self
    {
        $watermark = imagecreatefrompng($watermarkPath);
        $wmWidth = imagesx($watermark);
        $wmHeight = imagesy($watermark);
        
        // Calculate position
        switch ($position) {
            case 'top-left':
                $x = $margin;
                $y = $margin;
                break;
            case 'top-right':
                $x = $this->width - $wmWidth - $margin;
                $y = $margin;
                break;
            case 'bottom-left':
                $x = $margin;
                $y = $this->height - $wmHeight - $margin;
                break;
            case 'bottom-right':
            default:
                $x = $this->width - $wmWidth - $margin;
                $y = $this->height - $wmHeight - $margin;
                break;
            case 'center':
                $x = ($this->width - $wmWidth) / 2;
                $y = ($this->height - $wmHeight) / 2;
                break;
        }
        
        imagecopy($this->image, $watermark, (int) $x, (int) $y, 0, 0, $wmWidth, $wmHeight);
        imagedestroy($watermark);
        
        return $this;
    }
    
    /**
     * Add text to image
     */
    public function text(string $text, int $x, int $y, int $size = 20, array $color = [0, 0, 0]): self
    {
        $textColor = imagecolorallocate($this->image, $color[0], $color[1], $color[2]);
        
        // Use built-in font if TTF not available
        imagestring($this->image, $size, $x, $y, $text, $textColor);
        
        return $this;
    }
    
    /**
     * Save image to file
     */
    public function save(string $path, ?int $type = null): bool
    {
        $type = $type ?? $this->type ?? IMAGETYPE_JPEG;
        
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($this->image, $path, $this->quality);
            case IMAGETYPE_PNG:
                $pngQuality = (int) (9 - ($this->quality / 100 * 9));
                return imagepng($this->image, $path, $pngQuality);
            case IMAGETYPE_GIF:
                return imagegif($this->image, $path);
            case IMAGETYPE_WEBP:
                return imagewebp($this->image, $path, $this->quality);
            default:
                throw new \RuntimeException("Unsupported image type for saving");
        }
    }
    
    /**
     * Output image to browser
     */
    public function output(?int $type = null): void
    {
        $type = $type ?? $this->type ?? IMAGETYPE_JPEG;
        
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
     * Destroy image resource
     */
    public function __destruct()
    {
        if (is_resource($this->image)) {
            imagedestroy($this->image);
        }
    }
}