<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\Response;
use Plugs\Exceptions\HttpException;

class AssetController
{
    /**
     * Serve framework internal assets.
     */
    public function serve(string $type, string $file)
    {
        // Sanitize file name to prevent path traversal
        $file = basename($file);
        
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($path)) {
            throw new HttpException("Framework asset '{$file}' not found.", 404);
        }

        $content = file_get_contents($path);
        
        $contentType = $type === 'js' ? 'application/javascript' : ($type === 'css' ? 'text/css' : 'application/octet-stream');

        // Check if we can serve a minified version if the requested one doesn't exist? 
        // No, we are pointing directly to the .min.js in the engine.

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
