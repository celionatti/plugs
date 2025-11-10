<?php

declare(strict_types=1);

namespace Plugs\Utils;

/*
|----------------------------------------------------------------------
| Asset Manager
|----------------------------------------------------------------------
| Handles the compilation and optimization of CSS and JS assets.
*/

class AssetManager {
    public function compileCSS($files) {
        $output = '';
        foreach ($files as $file) {
            $output .= $this->minifyCSS(file_get_contents($file));
        }
        $hash = md5($output);
        $cacheFile = "assets/css/compiled-{$hash}.css";
        
        if (!file_exists($cacheFile)) {
            file_put_contents($cacheFile, $output);
        }
        
        return $cacheFile;
    }
    
    public function minifyCSS($css) {
        // Remove comments, whitespace, etc.
        return preg_replace('/\s+/', ' ', $css);
    }
}