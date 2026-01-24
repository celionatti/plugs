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

    public static function get(string $path): string
    {
        if (!self::exists($path)) {
            throw new \RuntimeException("File does not exist: {$path}");
        }

        return file_get_contents($path);
    }

    public static function put(string $path, string $contents): void
    {
        self::ensureDir(dirname($path));
        file_put_contents($path, $contents);
    }

    public static function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    public static function delete(string $path): bool
    {
        if (!self::exists($path)) {
            return false;
        }

        return self::isDirectory($path)
            ? self::deleteDirectory($path)
            : unlink($path);
    }

    public static function deleteDirectory(string $path): bool
    {
        if (!self::isDirectory($path)) {
            return false;
        }

        $items = array_diff(scandir($path), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            self::isDirectory($itemPath)
                ? self::deleteDirectory($itemPath)
                : unlink($itemPath);
        }

        return rmdir($path);
    }

    public static function copy(string $source, string $destination): bool
    {
        if (!self::exists($source)) {
            return false;
        }

        return self::isDirectory($source)
            ? self::copyDirectory($source, $destination)
            : copy($source, $destination);
    }

    public static function copyDirectory(string $source, string $destination): bool
    {
        if (!self::isDirectory($source)) {
            return false;
        }

        self::ensureDir($destination);
        $items = array_diff(scandir($source), ['.', '..']);

        foreach ($items as $item) {
            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destPath = $destination . DIRECTORY_SEPARATOR . $item;

            self::isDirectory($sourcePath)
                ? self::copyDirectory($sourcePath, $destPath)
                : copy($sourcePath, $destPath);
        }

        return true;
    }

    public static function files(string $path, bool $recursive = false): array
    {
        if (!self::isDirectory($path)) {
            return [];
        }

        $files = [];
        $items = array_diff(scandir($path), ['.', '..']);

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
        $items = array_diff(scandir($path), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (self::isDirectory($itemPath)) {
                $directories[] = $itemPath;

                if ($recursive) {
                    $directories = array_merge(
                        $directories,
                        self::directories($itemPath, true)
                    );
                }
            }
        }

        return $directories;
    }

    public static function basename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    public static function dirname(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    public static function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }
}
