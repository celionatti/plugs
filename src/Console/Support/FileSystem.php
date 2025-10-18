<?php

declare(strict_types=1);

namespace Plugs\Console\Support;

/*
|--------------------------------------------------------------------------
| FileSystem Class
|--------------------------------------------------------------------------
*/

class Filesystem
{
    public static function put(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($path, $contents);
    }

    public static function ensureDir(string $path): void
    {
        if (!is_dir($path)) mkdir($path, 0777, true);
    }

    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    public static function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public static function isFile(string $path): bool
    {
        return is_file($path);
    }

    public static function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    public static function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public static function get(string $path): string
    {
        if (!self::exists($path)) {
            throw new \RuntimeException("File does not exist: {$path}");
        }

        if (!self::isReadable($path)) {
            throw new \RuntimeException("File is not readable: {$path}");
        }

        return file_get_contents($path);
    }

    public static function append(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($path, $contents, FILE_APPEND);
    }

    public static function delete(string $path): bool
    {
        if (!self::exists($path)) {
            return false;
        }

        if (self::isDirectory($path)) {
            return self::deleteDirectory($path);
        }

        return unlink($path);
    }

    public static function deleteDirectory(string $path, bool $preserve = false): bool
    {
        if (!self::isDirectory($path)) {
            return false;
        }

        $items = scandir($path);
        $items = array_diff($items, ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (self::isDirectory($itemPath)) {
                self::deleteDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        if (!$preserve) {
            return rmdir($path);
        }

        return true;
    }

    public static function copy(string $source, string $destination): bool
    {
        if (!self::exists($source)) {
            return false;
        }

        if (self::isDirectory($source)) {
            return self::copyDirectory($source, $destination);
        }

        return copy($source, $destination);
    }

    public static function copyDirectory(string $source, string $destination): bool
    {
        if (!self::isDirectory($source)) {
            return false;
        }

        if (!self::isDirectory($destination)) {
            self::ensureDir($destination);
        }

        $items = scandir($source);
        $items = array_diff($items, ['.', '..']);

        foreach ($items as $item) {
            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (self::isDirectory($sourcePath)) {
                self::copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }

        return true;
    }

    public static function move(string $source, string $destination): bool
    {
        return rename($source, $destination);
    }

    public static function size(string $path): int
    {
        if (!self::exists($path)) {
            throw new \RuntimeException("Path does not exist: {$path}");
        }

        return filesize($path);
    }

    public static function lastModified(string $path): int
    {
        if (!self::exists($path)) {
            throw new \RuntimeException("Path does not exist: {$path}");
        }

        return filemtime($path);
    }

    public static function files(string $path, bool $recursive = false): array
    {
        if (!self::isDirectory($path)) {
            return [];
        }

        $files = [];
        $items = scandir($path);
        $items = array_diff($items, ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (self::isFile($itemPath)) {
                $files[] = $itemPath;
            } elseif ($recursive && self::isDirectory($itemPath)) {
                $files = array_merge($files, self::files($itemPath, true));
            }
        }

        return $files;
    }

    public static function directories(string $path, bool $recursive = false): array
    {
        if (!self::isDirectory($path)) {
            return [];
        }

        $directories = [];
        $items = scandir($path);
        $items = array_diff($items, ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (self::isDirectory($itemPath)) {
                $directories[] = $itemPath;
                
                if ($recursive) {
                    $directories = array_merge($directories, self::directories($itemPath, true));
                }
            }
        }

        return $directories;
    }

    public static function makeDirectory(string $path, int $mode = 0777, bool $recursive = true): bool
    {
        return mkdir($path, $mode, $recursive);
    }

    public static function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function basename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function dirname(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    public static function mimeType(string $path): string
    {
        if (!self::exists($path)) {
            throw new \RuntimeException("File does not exist: {$path}");
        }

        return mime_content_type($path);
    }
}