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
        
        // Navigate through the array using dot notation
        $value = self::$config[$file] ?? null;
        
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        
        return $value ?? $default;
    }
    
    /**
     * Set configuration value
     */
    public static function set(string $key, $value): void
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        
        // Ensure file config exists
        if (!isset(self::$config[$file])) {
            self::$config[$file] = [];
        }
        
        // Navigate and set the value
        $config = &self::$config[$file];
        
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $config[$part] = $value;
            } else {
                if (!isset($config[$part]) || !is_array($config[$part])) {
                    $config[$part] = [];
                }
                $config = &$config[$part];
            }
        }
        
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
        
        $value = self::$config[$file] ?? null;
        
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return false;
            }
        }
        
        return true;
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
        
        if (file_exists($configPath)) {
            self::$config[$file] = require $configPath;
            self::$loaded[$file] = true;
        } else {
            self::$config[$file] = [];
            self::$loaded[$file] = true;
        }
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
    }
    
    /**
     * Get configuration file path
     */
    private static function getConfigPath(string $file): string
    {
        return BASE_PATH . 'config/' . $file . '.php';
    }
}