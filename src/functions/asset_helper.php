<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Asset Helper Functions
|--------------------------------------------------------------------------
|
| Helper functions for working with assets in views and templates.
| Place this file in: src/Plugs/functions/asset.php
*/

use Plugs\Utils\AssetManager;

if (!function_exists('asset_manager')) {
    /**
     * Get the asset manager instance
     */
    function asset_manager(): AssetManager
    {
        static $manager = null;

        if ($manager === null) {
            $manager = new AssetManager(
                publicPath: defined('PUBLIC_PATH') ? PUBLIC_PATH : null,
                cachePath: null,
                minify: config('assets.minify', true),
                combine: config('assets.combine', true),
                versioning: config('assets.versioning', true)
            );

            // Register common assets from config
            $assets = config('assets.register', []);

            foreach ($assets['css'] ?? [] as $name => $config) {
                $manager->css(
                    $name,
                    $config['path'],
                    $config['dependencies'] ?? []
                );
            }

            foreach ($assets['js'] ?? [] as $name => $config) {
                $manager->js(
                    $name,
                    $config['path'],
                    $config['dependencies'] ?? []
                );
            }
        }

        return $manager;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL with versioning
     */
    function asset(string $path): string
    {
        // If it already starts with storage/, just return the URL
        if (str_starts_with($path, 'storage/')) {
            return asset_manager()->url($path);
        }

        // If the file doesn't exist in the public directory, 
        // fallback to the storage link path
        $fullPath = public_path(ltrim($path, '/'));
        if (!file_exists($fullPath)) {
            return asset_manager()->url('storage/' . ltrim($path, '/'));
        }

        return asset_manager()->url($path);
    }
}

if (!function_exists('css')) {
    /**
     * Generate CSS link tags
     */
    function css(string|array $files): string
    {
        $files = is_array($files) ? $files : [$files];

        return asset_manager()->tags($files, 'css');
    }
}

if (!function_exists('js')) {
    /**
     * Generate JavaScript script tags with optional attributes
     */
    function js(string|array $files, array $attrs = []): string
    {
        $files = is_array($files) ? $files : [$files];

        return asset_manager()->tags($files, 'js', $attrs);
    }
}

if (!function_exists('compile_css')) {
    /**
     * Compile CSS files
     */
    function compile_css(array $files, ?string $name = null): string
    {
        return asset_manager()->compileCSS($files, $name);
    }
}

if (!function_exists('compile_js')) {
    /**
     * Compile JavaScript files
     */
    function compile_js(array $files, ?string $name = null): string
    {
        return asset_manager()->compileJS($files, $name);
    }
}

if (!function_exists('inline_asset')) {
    /**
     * Get asset content as inline string
     */
    function inline_asset(string $path): string
    {
        return asset_manager()->inline($path);
    }
}

if (!function_exists('inline_tag')) {
    /**
     * Generate inline tag for asset
     */
    function inline_tag(string $path, ?string $type = null): string
    {
        return asset_manager()->inlineTag($path, $type);
    }
}

if (!function_exists('image')) {
    /**
     * Generate an optimized image URL
     */
    function image(string $path, array $options = []): string
    {
        return asset_manager()->image($path, $options);
    }
}

if (!function_exists('clear_asset_cache')) {
    /**
     * Clear asset cache
     */
    function clear_asset_cache(): void
    {
        asset_manager()->clearCache();
    }
}
