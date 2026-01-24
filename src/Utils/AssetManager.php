<?php

declare(strict_types=1);

namespace Plugs\Utils;

/*
|--------------------------------------------------------------------------
| Asset Manager Class
|--------------------------------------------------------------------------
|
| This class handles compilation, minification, versioning, and caching
| of CSS and JavaScript assets. It supports cache busting, source maps,
| and automatic dependency resolution.
*/

class AssetManager
{
    private string $publicPath;
    private string $cachePath;
    private string $manifestPath;
    private array $manifest = [];
    private bool $minify;
    private bool $combine;
    private bool $versioning;
    private array $registeredAssets = [];
    private ?string $cdnUrl = null;
    private bool $useSri = false;

    public function __construct(
        ?string $publicPath = null,
        ?string $cachePath = null,
        bool $minify = true,
        bool $combine = true,
        bool $versioning = true
    ) {
        $this->publicPath = rtrim($publicPath ?? (defined('PUBLIC_PATH') ? PUBLIC_PATH : getcwd() . '/public/'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->cachePath = rtrim($cachePath ?? $this->publicPath . 'assets' . DIRECTORY_SEPARATOR . 'cache', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->manifestPath = $this->cachePath . 'manifest.json';
        $this->minify = $minify;
        $this->combine = $combine;
        $this->versioning = $versioning;

        $this->loadManifest();
        $this->configureFromEnvironment();
    }

    /**
     * Automatically configure settings based on environment constants
     */
    private function configureFromEnvironment(): void
    {
        if (defined('APP_ENV')) {
            $isDev = APP_ENV === 'development' || APP_ENV === 'local';
            $this->minify = !$isDev;
            $this->combine = !$isDev;
            $this->versioning = true;
        }
    }

    /**
     * Register a CSS file
     */
    public function css(string $name, string $path, array $dependencies = []): self
    {
        $this->registeredAssets['css'][$name] = [
            'path' => $path,
            'dependencies' => $dependencies,
            'type' => 'css',
        ];

        return $this;
    }

    /**
     * Register a JavaScript file
     */
    public function js(string $name, string $path, array $dependencies = []): self
    {
        $this->registeredAssets['js'][$name] = [
            'path' => $path,
            'dependencies' => $dependencies,
            'type' => 'js',
        ];

        return $this;
    }

    /**
     * Compile CSS assets
     */
    public function compileCSS(array $files, ?string $outputName = null): string
    {
        $outputName = $outputName ?? 'compiled';

        // Resolve dependencies and get ordered file list
        $orderedFiles = $this->resolveAssetOrder($files, 'css');

        if (!$this->combine) {
            return $this->handleSeparateAssets($orderedFiles, 'css');
        }

        $content = '';
        $lastModified = 0;

        foreach ($orderedFiles as $file) {
            $filePath = $this->resolvePath($file);

            if (!file_exists($filePath)) {
                throw new \RuntimeException("CSS file not found: {$filePath}");
            }

            $fileContent = file_get_contents($filePath);
            $content .= $this->processCSS($fileContent, dirname($filePath));
            $lastModified = max($lastModified, filemtime($filePath));
        }

        if ($this->minify) {
            $content = $this->minifyCSS($content);
        }

        return $this->saveCompiledAsset($content, $outputName, 'css', $lastModified);
    }

    /**
     * Compile JavaScript assets
     */
    public function compileJS(array $files, ?string $outputName = null): string
    {
        $outputName = $outputName ?? 'compiled';

        // Resolve dependencies and get ordered file list
        $orderedFiles = $this->resolveAssetOrder($files, 'js');

        if (!$this->combine) {
            return $this->handleSeparateAssets($orderedFiles, 'js');
        }

        $content = '';
        $lastModified = 0;

        foreach ($orderedFiles as $file) {
            $filePath = $this->resolvePath($file);

            if (!file_exists($filePath)) {
                throw new \RuntimeException("JS file not found: {$filePath}");
            }

            $fileContent = file_get_contents($filePath);
            $content .= $this->processJS($fileContent, $filePath);
            $lastModified = max($lastModified, filemtime($filePath));
        }

        if ($this->minify) {
            $content = $this->minifyJS($content);
        }

        return $this->saveCompiledAsset($content, $outputName, 'js', $lastModified);
    }

    /**
     * Get asset URL with versioning and CDN support
     */
    public function url(string $asset): string
    {
        $manifestKey = basename($asset);

        if (isset($this->manifest[$manifestKey])) {
            $path = $this->manifest[$manifestKey];
        } else {
            // Generate version hash if file exists
            $fullPath = $this->publicPath . ltrim($asset, '/\\');

            if (file_exists($fullPath)) {
                $version = $this->versioning ? '?v=' . substr(md5_file($fullPath), 0, 8) : '';
                $path = '/' . ltrim($asset, '/\\') . $version;
            } else {
                $path = '/' . ltrim($asset, '/\\');
            }
        }

        if ($this->cdnUrl && !preg_match('~^(https?:)?//~', $path)) {
            return rtrim($this->cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        return $path;
    }

    /**
     * Generate HTML tags for assets
     */
    public function tags(array $assets, string $type = 'css', array $attributes = []): string
    {
        $tags = [];

        foreach ($assets as $asset) {
            $url = $this->url($asset);
            $currentAttrs = $attributes;

            if ($this->useSri) {
                $integrity = $this->generateIntegrity($asset);
                if ($integrity) {
                    $currentAttrs['integrity'] = $integrity;
                    $currentAttrs['crossorigin'] = 'anonymous';
                }
            }

            $attrStr = $this->buildAttributes($currentAttrs);

            if ($type === 'css') {
                $tags[] = sprintf('<link rel="stylesheet" href="%s"%s>', htmlspecialchars($url), $attrStr);
            } else {
                $tags[] = sprintf('<script src="%s"%s></script>', htmlspecialchars($url), $attrStr);
            }
        }

        return implode("\n", $tags);
    }

    /**
     * Generate resource hints (preload, prefetch, etc.)
     */
    public function resourceHint(string $url, string $rel = 'preload', array $extraAttrs = []): string
    {
        $type = '';
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

        if (in_array($ext, ['css'])) {
            $type = 'style';
        } elseif (in_array($ext, ['js'])) {
            $type = 'script';
        } elseif (in_array($ext, ['woff', 'woff2', 'ttf', 'otf'])) {
            $type = 'font';
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
            $type = 'image';
        }

        $attrs = array_merge(['rel' => $rel, 'href' => $url], $extraAttrs);
        if ($type) {
            $attrs['as'] = $type;
        }

        return sprintf('<link%s>', $this->buildAttributes($attrs));
    }

    /**
     * Build attribute string
     */
    private function buildAttributes(array $attributes): string
    {
        $attrStr = '';
        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attrStr .= ' ' . $key;
                }
            } else {
                $attrStr .= sprintf(' %s="%s"', $key, htmlspecialchars((string) $value));
            }
        }

        return $attrStr;
    }

    /**
     * Generate SRI hash
     */
    private function generateIntegrity(string $asset): ?string
    {
        $path = $this->resolvePath($asset);
        if (file_exists($path)) {
            $hash = base64_encode(hash('sha384', file_get_contents($path), true));

            return "sha384-{$hash}";
        }

        return null;
    }

    /**
     * Clear all cached assets
     */
    public function clearCache(): void
    {
        if (!is_dir($this->cachePath)) {
            return;
        }

        $files = glob($this->cachePath . '*');

        foreach ($files as $file) {
            if (is_file($file) && $file !== $this->manifestPath) {
                @unlink($file);
            }
        }

        $this->manifest = [];
        $this->saveManifest();
    }

    /**
     * Process CSS content (resolve URLs, imports, etc.)
     */
    private function processCSS(string $content, string $basePath): string
    {
        // Resolve relative URLs in CSS
        $content = preg_replace_callback(
            '/url\([\'"]?(?!(?:https?:|data:|\/))([^\'"\)]+)[\'"]?\)/i',
            function ($matches) use ($basePath) {
                $relativePath = trim($matches[1]);
                $absolutePath = realpath($basePath . DIRECTORY_SEPARATOR . $relativePath);

                if ($absolutePath && file_exists($absolutePath)) {
                    $webPath = str_replace($this->publicPath, '/', $absolutePath);
                    $webPath = str_replace('\\', '/', $webPath); // Ensure web path uses forward slashes

                    return 'url(' . $webPath . ')';
                }

                return $matches[0];
            },
            $content
        );

        // Add separator comment
        return "/* Compiled CSS */\n" . $content . "\n\n";
    }

    /**
     * Process JavaScript content
     */
    private function processJS(string $content, string $filePath): string
    {
        // Add separator comment with source file
        $fileName = basename($filePath);

        return "/* Source: {$fileName} */\n" . $content . "\n\n";
    }

    /**
     * Minify CSS content
     */
    private function minifyCSS(string $css): string
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        // Remove spaces around special characters
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
        // Remove trailing semicolons
        $css = preg_replace('/;}/', '}', $css);
        // Remove empty rules
        $css = preg_replace('/[^\}]+\{\s*\}/', '', $css);
        // Shorten hexadecimal colors
        $css = preg_replace('/#([a-f0-9])\\1([a-f0-9])\\2([a-f0-9])\\3/i', '#$1$2$3', $css);

        return trim($css);
    }

    /**
     * Minify JavaScript content
     */
    private function minifyJS(string $js): string
    {
        // Basic minification: remove comments and extra whitespace
        // Note: For production use, a specialized library like terser or uglify-js is recommended

        // Remove single-line comments (safer version)
        $js = preg_replace('/(?<!:)\/\/(?:(?!http:|https:).)*$/m', '', $js);
        // Remove multi-line comments
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        // Remove leading/trailing whitespace per line
        $js = preg_replace('/^\s+|\s+$/m', '', $js);
        // Replace multiple spaces/newlines with a single space
        $js = preg_replace('/\s+/', ' ', $js);
        // Remove spaces around operators
        $js = preg_replace('/\s*([=+\-*\/%&|^!<>?:,;{}()\[\]])\s*/', '$1', $js);

        return trim($js);
    }

    /**
     * Resolve asset dependencies and return ordered list
     */
    private function resolveAssetOrder(array $assets, string $type): array
    {
        $resolved = [];
        $visited = [];

        foreach ($assets as $asset) {
            $this->resolveDependencies($asset, $type, $resolved, $visited);
        }

        return $resolved;
    }

    /**
     * Recursively resolve dependencies
     */
    private function resolveDependencies(
        string $asset,
        string $type,
        array &$resolved,
        array &$visited
    ): void {
        if (in_array($asset, $resolved)) {
            return;
        }

        if (in_array($asset, $visited)) {
            throw new \RuntimeException("Circular dependency detected: {$asset}");
        }

        $visited[] = $asset;

        // Check if asset is registered
        if (isset($this->registeredAssets[$type][$asset])) {
            $assetData = $this->registeredAssets[$type][$asset];

            // Resolve dependencies first
            foreach ($assetData['dependencies'] as $dependency) {
                $this->resolveDependencies($dependency, $type, $resolved, $visited);
            }

            $resolved[] = $assetData['path'];
        } else {
            // Direct file path
            $resolved[] = $asset;
        }
    }

    /**
     * Handle separate (non-combined) assets
     */
    private function handleSeparateAssets(array $files, string $type): string
    {
        $urls = [];

        foreach ($files as $file) {
            $filePath = $this->resolvePath($file);

            if (!file_exists($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);

            if ($this->minify) {
                $content = $type === 'css' ? $this->minifyCSS($content) : $this->minifyJS($content);
            }

            $fileName = basename($file, '.' . $type);
            $url = $this->saveCompiledAsset(
                $content,
                $fileName,
                $type,
                filemtime($filePath)
            );

            $urls[] = $url;
        }

        return implode(',', $urls);
    }

    /**
     * Save compiled asset and return URL
     */
    private function saveCompiledAsset(
        string $content,
        string $name,
        string $type,
        int $lastModified
    ): string {
        $hash = $this->versioning ? substr(md5($content), 0, 8) : 'cache';
        $fileName = "{$name}-{$hash}.{$type}";
        $filePath = $this->cachePath . $fileName;
        $webPath = 'assets/cache/' . $fileName;

        // Check if file exists and is up to date
        if (file_exists($filePath) && filemtime($filePath) >= $lastModified) {
            return $webPath;
        }

        $this->ensureDirectoryExists($this->cachePath);

        // Write compiled file
        if (file_put_contents($filePath, $content) === false) {
            throw new \RuntimeException("Failed to write compiled asset: {$filePath}");
        }

        // Update manifest
        $this->manifest[$name . '.' . $type] = $webPath;
        $this->saveManifest();

        return $webPath;
    }

    /**
     * Resolve asset path
     */
    private function resolvePath(string $path): string
    {
        // If absolute path, return as is
        if (file_exists($path)) {
            return $path;
        }

        // Try relative to public path
        $fullPath = $this->publicPath . ltrim($path, '/\\');

        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // Try relative to current directory
        $cwdPath = getcwd() . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
        if (file_exists($cwdPath)) {
            return $cwdPath;
        }

        return $path;
    }

    /**
     * Load manifest file
     */
    private function loadManifest(): void
    {
        if (file_exists($this->manifestPath)) {
            $content = file_get_contents($this->manifestPath);
            $this->manifest = json_decode($content, true) ?? [];
        }
    }

    /**
     * Save manifest file
     */
    private function saveManifest(): void
    {
        file_put_contents(
            $this->manifestPath,
            json_encode($this->manifest, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                throw new \RuntimeException("Failed to create directory: {$path}");
            }
        }
    }

    /**
     * Set minification option
     */
    public function setMinify(bool $minify): self
    {
        $this->minify = $minify;

        return $this;
    }

    /**
     * Set combine option
     */
    public function setCombine(bool $combine): self
    {
        $this->combine = $combine;

        return $this;
    }

    /**
     * Set versioning option
     */
    public function setVersioning(bool $versioning): self
    {
        $this->versioning = $versioning;

        return $this;
    }

    /**
     * Set CDN URL
     */
    public function setCdnUrl(?string $url): self
    {
        $this->cdnUrl = $url;

        return $this;
    }

    /**
     * Enable/Disable SRI (Subresource Integrity)
     */
    public function useSri(bool $use): self
    {
        $this->useSri = $use;

        return $this;
    }

    /**
     * Get all registered assets
     */
    public function getRegisteredAssets(?string $type = null): array
    {
        if ($type !== null) {
            return $this->registeredAssets[$type] ?? [];
        }

        return $this->registeredAssets;
    }

    /**
     * Check if asset is cached and up to date
     */
    public function isCached(string $name, string $type): bool
    {
        $manifestKey = $name . '.' . $type;

        if (!isset($this->manifest[$manifestKey])) {
            return false;
        }

        $cachedFile = $this->publicPath . $this->manifest[$manifestKey];

        return file_exists($cachedFile);
    }
}
