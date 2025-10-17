<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dump and Die Function
|--------------------------------------------------------------------------
|
| This function provides a stylish way to dump variables and halt execution.
*/

if (!function_exists('dd')) {
    /**
     * Dump and Die - Stylish variable debugging
     * 
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function dd(...$vars): void
    {
        // Set content type to HTML with UTF-8
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<style>
            .dd-container {
                font-family: "Monaco", "Menlo", "Ubuntu Mono", monospace;
                font-size: 14px;
                line-height: 1.4;
                margin: 20px;
                padding: 0;
                background: #1e1e1e;
                color: #e0e0e0;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            
            .dd-header {
                background: #2d2d2d;
                color: #ff6b6b;
                padding: 12px 20px;
                font-weight: bold;
                border-bottom: 1px solid #404040;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .dd-file-info {
                font-size: 12px;
                color: #888;
                background: #2a2a2a;
                padding: 8px 20px;
                border-bottom: 1px solid #404040;
            }
            
            .dd-content {
                padding: 20px;
            }
            
            .dd-item {
                margin-bottom: 15px;
                border-left: 3px solid #ff6b6b;
                padding-left: 15px;
            }
            
            .dd-item-title {
                color: #64b5f6;
                font-weight: bold;
                margin-bottom: 8px;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            pre {
                margin: 0;
                padding: 12px;
                background: #2a2a2a;
                border-radius: 4px;
                border: 1px solid #404040;
                overflow-x: auto;
                color: #e0e0e0;
            }
            
            .dd-type {
                color: #ffd54f;
                font-style: italic;
            }
            
            .dd-null { color: #ff6b6b; }
            .dd-bool { color: #4fc3f7; }
            .dd-number { color: #4db6ac; }
            .dd-string { color: #aed581; }
            .dd-array { color: #ba68c8; }
            .dd-object { color: #ff8a65; }
        </style>';

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $caller = $backtrace[0] ?? [];
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 'unknown';

        echo '<div class="dd-container">';
        echo '<div class="dd-header">';
        echo '🌲 Trees Framework Debug';
        echo '<span style="color: #888; font-size: 12px;">DD</span>';
        echo '</div>';
        
        echo '<div class="dd-file-info">';
        echo 'File: ' . htmlspecialchars($file) . ' | Line: ' . $line;
        echo '</div>';
        
        echo '<div class="dd-content">';

        if (empty($vars)) {
            echo '<div class="dd-item">';
            echo '<div class="dd-item-title">No variables provided</div>';
            echo '</div>';
        } else {
            foreach ($vars as $index => $var) {
                echo '<div class="dd-item">';
                echo '<div class="dd-item-title">Variable #' . ($index + 1);
                echo ' <span class="dd-type">(' . gettype($var) . ')</span>';
                echo '</div>';
                
                echo '<pre>';
                echo htmlspecialchars(print_r($var, true));
                echo '</pre>';
                
                // Additional type-specific styling
                echo '<div style="margin-top: 5px; font-size: 12px; color: #888;">';
                echo 'Type: <span class="dd-' . gettype($var) . '">' . gettype($var) . '</span>';
                if (is_countable($var)) {
                    echo ' | Count: ' . count($var);
                }
                if (is_string($var)) {
                    echo ' | Length: ' . strlen($var);
                }
                echo '</div>';
                
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</div>';
        
        // Add some JavaScript for optional interactivity
        echo '<script>
            document.addEventListener("click", function(e) {
                if (e.target.closest(".dd-header")) {
                    const container = e.target.closest(".dd-container");
                    const content = container.querySelector(".dd-content");
                    content.style.display = content.style.display === "none" ? "block" : "none";
                }
            });
        </script>';
        
        exit(1);
    }
}

// Optional: Add a d() function that doesn't die
if (!function_exists('d')) {
    /**
     * Dump without dying
     * 
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function d(...$vars): void
    {
        // Remove the exit call from dd by using output buffering
        ob_start();
        call_user_func_array('dd', $vars);
        $output = ob_get_clean();
        
        // Remove the exit and any closing tags
        $output = preg_replace('/<script>[\s\S]*?<\/script>$/', '', $output);
        echo $output;
    }
}