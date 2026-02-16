<?php

declare(strict_types=1);

namespace Plugs;

/*
|--------------------------------------------------------------------------
| Config Class
|--------------------------------------------------------------------------
|
| This class manages the configuration settings for the application. It allows
| loading, retrieving, and setting configuration values.
*/

class Config
{
    private static $config = [];
    private static $loaded = [];

    /**
     * Get configuration value using dot notation
     */
    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        // Load config file if not already loaded
        if (!isset(self::$loaded[$file])) {
            self::load($file);
        }

        if (empty($parts)) {
            return self::$config[$file] ?? $default;
        }

        return \Plugs\Utils\Arr::get(self::$config[$file], implode('.', $parts), $default);
    }

    /**
     * Set configuration value
     */
    public static function set(string $key, $value): void
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (empty($parts)) {
            self::$config[$file] = $value;
            self::$loaded[$file] = true;

            return;
        }

        if (!isset(self::$config[$file])) {
            self::$config[$file] = [];
        }

        \Plugs\Utils\Arr::set(self::$config[$file], implode('.', $parts), $value);
        self::$loaded[$file] = true;
    }

    /**
     * Check if configuration key exists
     */
    public static function has(string $key): bool
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset(self::$loaded[$file])) {
            self::load($file);
        }

        if (empty($parts)) {
            return isset(self::$config[$file]);
        }

        return \Plugs\Utils\Arr::has(self::$config[$file], implode('.', $parts));
    }

    /**
     * Load configuration file
     */
    public static function load(string $file): void
    {
        if (isset(self::$loaded[$file])) {
            return;
        }

        $configPath = self::getConfigPath($file);
        $defaults = \Plugs\Config\DefaultConfig::get($file);

        if (file_exists($configPath)) {
            $userConfig = require $configPath;
            self::$config[$file] = array_replace_recursive($defaults, is_array($userConfig) ? $userConfig : []);
        } else {
            self::$config[$file] = $defaults;
        }

        self::$loaded[$file] = true;
    }

    /**
     * Get all configuration
     */
    public static function all(string|null $file = null): array
    {
        if ($file !== null) {
            if (!isset(self::$loaded[$file])) {
                self::load($file);
            }

            return self::$config[$file] ?? [];
        }

        return self::$config;
    }

    /**
     * Pre-load all configuration files.
     */
    public static function warmup(): void
    {
        $configPath = self::$path ?? (defined('BASE_PATH') ? BASE_PATH . 'config/' : __DIR__ . '/../config/');

        if (!is_dir($configPath)) {
            return;
        }

        $files = glob($configPath . '*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            self::load($name);
        }
    }

    /**
     * Clear configuration cache
     */
    public static function clear(string|null $file = null): void
    {
        if ($file !== null) {
            unset(self::$config[$file], self::$loaded[$file]);
        } else {
            self::$config = [];
            self::$loaded = [];
        }

        self::$path = null;

        // Also clear physical cache file if it exists
        $cacheFile = self::getCachePath();
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * Cache all loaded configuration to a single file.
     */
    public static function cache(): bool
    {
        $configPath = self::$path ?? (defined('BASE_PATH') ? BASE_PATH . 'config/' : __DIR__ . '/../config/');
        $files = glob($configPath . '*.php');
        $allConfig = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $allConfig[$name] = require $file;
        }

        $cacheFile = self::getCachePath();
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $content = '<?php return ' . var_export($allConfig, true) . ';';

        return file_put_contents($cacheFile, $content) !== false;
    }

    /**
     * Load configuration from cache.
     */
    public static function loadFromCache(): bool
    {
        $cacheFile = self::getCachePath();

        if (file_exists($cacheFile)) {
            $cached = require $cacheFile;
            if (is_array($cached)) {
                self::$config = $cached;
                foreach (self::$config as $file => $values) {
                    self::$loaded[$file] = true;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Get the path to the configuration cache file.
     */
    private static function getCachePath(): string
    {
        if (defined('STORAGE_PATH')) {
            return STORAGE_PATH . 'framework/config.php';
        }

        return __DIR__ . '/../storage/framework/config.php';
    }

    private static $path = null;

    /**
     * Set the configuration path.
     */
    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get configuration file path
     */
    private static function getConfigPath(string $file): string
    {
        if (self::$path === null) {
            if (defined('BASE_PATH')) {
                self::setPath(BASE_PATH . 'config/');
            } else {
                // Fallback for when used as a standalone package without BASE_PATH
                // Assuming standard structure if not defined
                self::setPath(__DIR__ . '/../config/');
            }
        }

        return self::$path . $file . '.php';
    }
}
