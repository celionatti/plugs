<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Exceptions\HttpException;

class AssetController
{
    /**
     * MIME types for framework assets.
     */
    private const MIME_TYPES = [
        'js' => 'application/javascript',
        'css' => 'text/css',
    ];

    /**
     * Serve framework internal assets with high-performance caching.
     *
     * Features:
     * - Auto-serves minified version when available (e.g. plugs-spa.js → plugs-framework.min.js)
     * - ETag-based conditional requests (304 Not Modified)
     * - Content-Length for faster transfers
     * - Immutable cache headers for versioned requests
     */
    public function serve(string $type, string $file)
    {
        // Sanitize file name to prevent path traversal
        $file = basename($file);

        $assetsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;

        // Auto-serve minified bundle when requesting the source files
        $path = $this->resolveAssetPath($assetsDir, $file, $type);

        if (!$path || !file_exists($path)) {
            throw new HttpException(404, "Framework asset '{$file}' not found.");
        }

        // Generate ETag from file modification time + size (fast, no hashing)
        $mtime = filemtime($path);
        $size = filesize($path);
        $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

        // Check for conditional request (304 Not Modified)
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === $etag) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        $content = file_get_contents($path);
        $contentType = self::MIME_TYPES[$type] ?? 'application/octet-stream';

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) $size,
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Plugs-Asset' => basename($path),
        ]);
    }

    /**
     * Resolve the actual file path, preferring minified versions.
     *
     * Maps:
     *  - plugs-spa.js → plugs-framework.min.js (if exists)
     *  - *.js → *.min.js (if exists)
     */
    private function resolveAssetPath(string $dir, string $file, string $type): ?string
    {
        // Special case: plugs-spa.js should serve the unified minified bundle
        if ($file === 'plugs-spa.js' && $type === 'js') {
            $minified = $dir . 'plugs-framework.min.js';
            if (file_exists($minified)) {
                return $minified;
            }
        }

        // General case: try .min.{ext} variant first
        if ($type === 'js' && !str_contains($file, '.min.')) {
            $minFile = str_replace('.js', '.min.js', $file);
            $minPath = $dir . $minFile;
            if (file_exists($minPath)) {
                return $minPath;
            }
        }

        if ($type === 'css' && !str_contains($file, '.min.')) {
            $minFile = str_replace('.css', '.min.css', $file);
            $minPath = $dir . $minFile;
            if (file_exists($minPath)) {
                return $minPath;
            }
        }

        // Fallback to the exact requested file
        $path = $dir . $file;
        return file_exists($path) ? $path : null;
    }
}
