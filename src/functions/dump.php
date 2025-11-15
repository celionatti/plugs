<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plugs Framework Debug Utility
|--------------------------------------------------------------------------
|
| Advanced debugging with memory analysis, query detection, and N+1 detection
*/

if (!function_exists('dd')) {
    /**
     * Plugs Debug - Advanced variable debugging with performance analysis
     * 
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function dd(...$vars): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $caller = $backtrace[0] ?? [];
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 'unknown';
        
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $executionTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

        echo '<style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            
            body { 
                background: #0a0e27;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            
            .plugs-debug {
                font-size: 14px;
                line-height: 1.6;
                margin: 20px auto;
                max-width: 1400px;
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e8ba3 100%);
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 100px rgba(30,60,114,0.3);
                overflow: hidden;
                animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            }
            
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-30px) scale(0.95); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            
            .plugs-header {
                background: rgba(0,0,0,0.25);
                backdrop-filter: blur(20px);
                padding: 24px 28px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            
            .plugs-logo {
                display: flex;
                align-items: center;
                gap: 14px;
                color: #fff;
            }
            
            .plugs-icon {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, #00d4ff 0%, #0099ff 100%);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 22px;
                box-shadow: 0 6px 20px rgba(0,153,255,0.4);
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            
            .plugs-title {
                font-size: 20px;
                font-weight: 700;
                letter-spacing: 1px;
                text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            }
            
            .plugs-subtitle {
                font-size: 12px;
                color: rgba(255,255,255,0.7);
                font-weight: 500;
            }
            
            .plugs-badge {
                background: rgba(0,212,255,0.2);
                backdrop-filter: blur(10px);
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 700;
                color: #00d4ff;
                text-transform: uppercase;
                letter-spacing: 1.2px;
                border: 1px solid rgba(0,212,255,0.3);
                box-shadow: 0 4px 15px rgba(0,212,255,0.2);
            }
            
            .plugs-meta {
                background: rgba(0,0,0,0.2);
                backdrop-filter: blur(10px);
                padding: 20px 28px;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 20px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            
            .meta-item {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            
            .meta-label {
                color: rgba(255,255,255,0.6);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .meta-value {
                color: #fff;
                font-size: 14px;
                font-family: "Monaco", "Menlo", "Consolas", monospace;
                word-break: break-all;
                font-weight: 600;
            }
            
            .plugs-content {
                background: #0f1419;
                padding: 28px;
                max-height: 75vh;
                overflow-y: auto;
            }
            
            .plugs-content::-webkit-scrollbar {
                width: 12px;
            }
            
            .plugs-content::-webkit-scrollbar-track {
                background: rgba(255,255,255,0.03);
                border-radius: 10px;
            }
            
            .plugs-content::-webkit-scrollbar-thumb {
                background: linear-gradient(135deg, #0099ff 0%, #00d4ff 100%);
                border-radius: 10px;
                border: 2px solid #0f1419;
            }
            
            .plugs-content::-webkit-scrollbar-thumb:hover {
                background: linear-gradient(135deg, #00b4ff 0%, #00e4ff 100%);
            }
            
            .debug-item {
                background: linear-gradient(135deg, #1a1f2e 0%, #1e2533 100%);
                border-radius: 14px;
                margin-bottom: 24px;
                overflow: hidden;
                border: 1px solid rgba(0,153,255,0.2);
                transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            }
            
            .debug-item:hover {
                border-color: rgba(0,153,255,0.5);
                box-shadow: 0 12px 30px rgba(0,153,255,0.15);
                transform: translateY(-2px);
            }
            
            .item-header {
                background: linear-gradient(135deg, rgba(0,153,255,0.15) 0%, rgba(0,212,255,0.1) 100%);
                padding: 18px 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: pointer;
                user-select: none;
                border-bottom: 1px solid rgba(0,153,255,0.1);
            }
            
            .item-header:hover {
                background: linear-gradient(135deg, rgba(0,153,255,0.2) 0%, rgba(0,212,255,0.15) 100%);
            }
            
            .item-title {
                color: #fff;
                font-weight: 700;
                font-size: 15px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .item-badges {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .badge {
                padding: 5px 12px;
                border-radius: 14px;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                border: 1px solid transparent;
            }
            
            .badge-type { 
                background: rgba(0,212,255,0.15); 
                color: #00d4ff; 
                border-color: rgba(0,212,255,0.3);
            }
            .badge-memory { 
                background: rgba(52,211,153,0.15); 
                color: #34d399; 
                border-color: rgba(52,211,153,0.3);
            }
            .badge-warning { 
                background: rgba(251,191,36,0.15); 
                color: #fbbf24; 
                border-color: rgba(251,191,36,0.3);
            }
            .badge-danger { 
                background: rgba(239,68,68,0.15); 
                color: #ef4444; 
                border-color: rgba(239,68,68,0.3);
            }
            .badge-query { 
                background: rgba(59,130,246,0.15); 
                color: #3b82f6; 
                border-color: rgba(59,130,246,0.3);
            }
            .badge-success { 
                background: rgba(16,185,129,0.15); 
                color: #10b981; 
                border-color: rgba(16,185,129,0.3);
            }
            
            .item-body {
                padding: 24px;
            }
            
            .code-block {
                background: #0d1117;
                border-radius: 10px;
                padding: 20px;
                overflow-x: auto;
                border: 1px solid rgba(0,153,255,0.15);
                font-family: "Monaco", "Menlo", "Consolas", "Ubuntu Mono", monospace;
                font-size: 13px;
                line-height: 1.7;
                color: #e6edf3;
                box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);
            }
            
            .code-block::-webkit-scrollbar {
                height: 10px;
            }
            
            .code-block::-webkit-scrollbar-track {
                background: rgba(0,0,0,0.3);
                border-radius: 5px;
            }
            
            .code-block::-webkit-scrollbar-thumb {
                background: rgba(0,153,255,0.3);
                border-radius: 5px;
            }
            
            .code-block::-webkit-scrollbar-thumb:hover {
                background: rgba(0,153,255,0.5);
            }
            
            .stats-section {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid rgba(0,153,255,0.1);
            }
            
            .section-title {
                color: #00d4ff;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                margin-bottom: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 14px;
            }
            
            .stat-card {
                background: rgba(0,153,255,0.08);
                border: 1px solid rgba(0,153,255,0.2);
                border-radius: 10px;
                padding: 14px 16px;
                transition: all 0.2s ease;
            }
            
            .stat-card:hover {
                background: rgba(0,153,255,0.12);
                border-color: rgba(0,153,255,0.3);
                transform: translateY(-2px);
            }
            
            .stat-label {
                color: rgba(255,255,255,0.5);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                margin-bottom: 6px;
                letter-spacing: 0.8px;
            }
            
            .stat-value {
                color: #fff;
                font-size: 18px;
                font-weight: 700;
                font-family: "Monaco", "Menlo", "Consolas", monospace;
            }
            
            .alert {
                background: rgba(251,191,36,0.1);
                border-left: 4px solid #fbbf24;
                padding: 18px 20px;
                margin-top: 16px;
                border-radius: 8px;
                color: #fbbf24;
                font-size: 13px;
                line-height: 1.7;
            }
            
            .alert-danger {
                background: rgba(239,68,68,0.1);
                border-left-color: #ef4444;
                color: #fca5a5;
            }
            
            .alert-success {
                background: rgba(16,185,129,0.1);
                border-left-color: #10b981;
                color: #6ee7b7;
            }
            
            .alert-info {
                background: rgba(59,130,246,0.1);
                border-left-color: #3b82f6;
                color: #93c5fd;
            }
            
            .alert-title {
                font-weight: 700;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 14px;
            }
            
            .alert-content {
                color: rgba(255,255,255,0.8);
                line-height: 1.8;
            }
            
            .query-analysis {
                margin-top: 20px;
                padding: 20px;
                background: rgba(59,130,246,0.08);
                border: 1px solid rgba(59,130,246,0.2);
                border-radius: 10px;
            }
            
            .recommendations {
                margin-top: 16px;
                padding: 16px;
                background: rgba(16,185,129,0.08);
                border: 1px solid rgba(16,185,129,0.2);
                border-radius: 8px;
            }
            
            .recommendation-item {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 10px;
                color: rgba(255,255,255,0.8);
                font-size: 13px;
                line-height: 1.6;
            }
            
            .recommendation-item:last-child {
                margin-bottom: 0;
            }
            
            .recommendation-icon {
                color: #10b981;
                font-weight: 700;
                flex-shrink: 0;
            }
            
            .trace-info {
                background: rgba(0,0,0,0.2);
                border-radius: 8px;
                padding: 14px 16px;
                margin-top: 16px;
                font-size: 12px;
                color: rgba(255,255,255,0.6);
                font-family: "Monaco", "Menlo", "Consolas", monospace;
            }
            
            .trace-path {
                color: #00d4ff;
                word-break: break-all;
            }
            
            /* Syntax highlighting for printed values */
            .syntax-null { color: #ef4444; font-weight: 600; }
            .syntax-bool { color: #3b82f6; font-weight: 600; }
            .syntax-number { color: #34d399; }
            .syntax-string { color: #a5f3fc; }
            .syntax-array { color: #c084fc; }
            .syntax-object { color: #fb923c; }
            
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: rgba(255,255,255,0.4);
            }
            
            .empty-icon {
                font-size: 48px;
                margin-bottom: 16px;
                opacity: 0.5;
            }
        </style>';

        echo '<div class="plugs-debug">';
        
        // Header
        echo '<div class="plugs-header">';
        echo '<div class="plugs-logo">';
        echo '<div class="plugs-icon">🔌</div>';
        echo '<div>';
        echo '<div class="plugs-title">PLUGS FRAMEWORK</div>';
        echo '<div class="plugs-subtitle">Debug Console</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="plugs-badge">● Active</div>';
        echo '</div>';
        
        // Meta information
        echo '<div class="plugs-meta">';
        
        echo '<div class="meta-item">';
        echo '<div class="meta-label">📁 File Location</div>';
        echo '<div class="meta-value">' . htmlspecialchars(basename($file)) . '</div>';
        echo '</div>';
        
        echo '<div class="meta-item">';
        echo '<div class="meta-label">📍 Line Number</div>';
        echo '<div class="meta-value">' . $line . '</div>';
        echo '</div>';
        
        echo '<div class="meta-item">';
        echo '<div class="meta-label">💾 Current Memory</div>';
        echo '<div class="meta-value">' . formatBytes($memoryUsage) . '</div>';
        echo '</div>';
        
        echo '<div class="meta-item">';
        echo '<div class="meta-label">📊 Peak Memory</div>';
        echo '<div class="meta-value">' . formatBytes($peakMemory) . '</div>';
        echo '</div>';
        
        echo '<div class="meta-item">';
        echo '<div class="meta-label">⏱️ Execution Time</div>';
        echo '<div class="meta-value">' . number_format($executionTime * 1000, 2) . ' ms</div>';
        echo '</div>';
        
        echo '<div class="meta-item">';
        echo '<div class="meta-label">🕐 Timestamp</div>';
        echo '<div class="meta-value">' . date('H:i:s') . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Content
        echo '<div class="plugs-content">';

        if (empty($vars)) {
            echo '<div class="empty-state">';
            echo '<div class="empty-icon">📦</div>';
            echo '<div style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">No Variables Provided</div>';
            echo '<div>Pass variables to pd() to debug them</div>';
            echo '</div>';
        } else {
            foreach ($vars as $index => $var) {
                $varSize = getVariableSize($var);
                $type = gettype($var);
                $analysis = analyzeVariable($var);
                
                echo '<div class="debug-item">';
                echo '<div class="item-header" onclick="toggleDebugItem(this)">';
                echo '<div class="item-title">';
                echo '<span>📦</span>';
                echo '<span>Variable #' . ($index + 1) . '</span>';
                echo '</div>';
                echo '<div class="item-badges">';
                
                echo '<span class="badge badge-type">' . $type . '</span>';
                echo '<span class="badge badge-memory">' . formatBytes($varSize) . '</span>';
                
                if ($analysis['isQuery']) {
                    echo '<span class="badge badge-query">SQL</span>';
                }
                
                if ($varSize > 1024 * 1024) {
                    echo '<span class="badge badge-danger">Large</span>';
                } elseif ($varSize > 100 * 1024) {
                    echo '<span class="badge badge-warning">Medium</span>';
                } else {
                    echo '<span class="badge badge-success">Small</span>';
                }
                
                echo '</div>';
                echo '</div>';
                
                echo '<div class="item-body">';
                
                // Main content with formatted output
                echo '<div class="section-title">📄 Variable Content</div>';
                echo '<div class="code-block">';
                echo formatVariableOutput($var);
                echo '</div>';
                
                // Statistics section
                echo '<div class="stats-section">';
                echo '<div class="section-title">📊 Statistics</div>';
                echo '<div class="stats-grid">';
                
                echo '<div class="stat-card">';
                echo '<div class="stat-label">Data Type</div>';
                echo '<div class="stat-value">' . ucfirst($type) . '</div>';
                echo '</div>';
                
                echo '<div class="stat-card">';
                echo '<div class="stat-label">Memory Size</div>';
                echo '<div class="stat-value">' . formatBytes($varSize) . '</div>';
                echo '</div>';
                
                if (is_countable($var)) {
                    $count = count($var);
                    echo '<div class="stat-card">';
                    echo '<div class="stat-label">Item Count</div>';
                    echo '<div class="stat-value">' . number_format($count) . '</div>';
                    echo '</div>';
                }
                
                if (is_string($var)) {
                    echo '<div class="stat-card">';
                    echo '<div class="stat-label">String Length</div>';
                    echo '<div class="stat-value">' . number_format(strlen($var)) . '</div>';
                    echo '</div>';
                    
                    echo '<div class="stat-card">';
                    echo '<div class="stat-label">Word Count</div>';
                    echo '<div class="stat-value">' . str_word_count($var) . '</div>';
                    echo '</div>';
                }
                
                if (is_object($var)) {
                    echo '<div class="stat-card">';
                    echo '<div class="stat-label">Class Name</div>';
                    echo '<div class="stat-value" style="font-size: 13px;">' . get_class($var) . '</div>';
                    echo '</div>';
                }
                
                echo '</div>';
                echo '</div>';
                
                // Query analysis
                if ($analysis['isQuery']) {
                    echo '<div class="stats-section">';
                    echo '<div class="section-title">🔍 SQL Query Analysis</div>';
                    echo '<div class="query-analysis">';
                    
                    if (is_array($var)) {
                        $queryCount = count($var);
                        
                        echo '<div class="stats-grid">';
                        echo '<div class="stat-card">';
                        echo '<div class="stat-label">Total Queries</div>';
                        echo '<div class="stat-value">' . number_format($queryCount) . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stat-card">';
                        echo '<div class="stat-label">Estimated Time</div>';
                        echo '<div class="stat-value">' . ($queryCount * 2) . ' ms</div>';
                        echo '</div>';
                        echo '</div>';
                        
                        // N+1 Detection
                        $nPlusOne = detectNPlusOne($var);
                        if ($nPlusOne['detected']) {
                            echo '<div class="alert alert-danger" style="margin-top: 16px;">';
                            echo '<div class="alert-title">⚠️ N+1 Query Problem Detected</div>';
                            echo '<div class="alert-content">';
                            echo 'Found <strong>' . $nPlusOne['count'] . '</strong> similar queries executing in a loop. This is a classic N+1 problem.';
                            echo '</div>';
                            echo '</div>';
                            
                            echo '<div class="recommendations">';
                            echo '<div class="section-title" style="color: #10b981; margin-bottom: 12px;">💡 Recommended Solutions</div>';
                            echo '<div class="recommendation-item">';
                            echo '<span class="recommendation-icon">→</span>';
                            echo '<span>Use <strong>eager loading</strong> (e.g., <code>with()</code>) to load relationships upfront</span>';
                            echo '</div>';
                            echo '<div class="recommendation-item">';
                            echo '<span class="recommendation-icon">→</span>';
                            echo '<span>Implement <strong>batch queries</strong> using <code>whereIn()</code> to fetch multiple records at once</span>';
                            echo '</div>';
                            echo '<div class="recommendation-item">';
                            echo '<span class="recommendation-icon">→</span>';
                            echo '<span>Consider using <strong>query result caching</strong> for frequently accessed data</span>';
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        // Production readiness
                        if ($queryCount > 20) {
                            echo '<div class="alert alert-danger">';
                            echo '<div class="alert-title">❌ Not Production Ready</div>';
                            echo '<div class="alert-content">';
                            echo 'Too many queries (' . $queryCount . '). This will severely impact performance.<br>';
                            echo '<strong>Recommended:</strong> Reduce to less than 20 queries per request.';
                            echo '</div>';
                            echo '</div>';
                        } elseif ($queryCount > 10) {
                            echo '<div class="alert alert-warning">';
                            echo '<div class="alert-title">⚠️ Production Warning</div>';
                            echo '<div class="alert-content">';
                            echo 'Query count is borderline (' . $queryCount . '). Consider optimization before deploying.<br>';
                            echo '<strong>Target:</strong> Aim for less than 10 queries per request for optimal performance.';
                            echo '</div>';
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-success">';
                            echo '<div class="alert-title">✅ Production Ready</div>';
                            echo '<div class="alert-content">';
                            echo 'Query count (' . $queryCount . ') is well within acceptable limits for production deployment.';
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        // Single query analysis
                        echo '<div class="alert alert-info">';
                        echo '<div class="alert-title">ℹ️ Single Query</div>';
                        echo '<div class="alert-content">';
                        echo 'This is a single SQL query. Performance should be good for production.';
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                
                // Memory warnings
                if ($varSize > 5 * 1024 * 1024) {
                    echo '<div class="alert alert-danger">';
                    echo '<div class="alert-title">❌ Critical Memory Warning</div>';
                    echo '<div class="alert-content">';
                    echo 'Variable size (' . formatBytes($varSize) . ') is extremely large and will cause memory issues.<br>';
                    echo '<strong>Action Required:</strong> Implement pagination, chunking, or streaming for this data.';
                    echo '</div>';
                    echo '</div>';
                } elseif ($varSize > 1 * 1024 * 1024) {
                    echo '<div class="alert alert-warning">';
                    echo '<div class="alert-title">⚠️ Large Variable Warning</div>';
                    echo '<div class="alert-content">';
                    echo 'Variable size (' . formatBytes($varSize) . ') is significant. Monitor memory usage in production.<br>';
                    echo '<strong>Tip:</strong> Consider lazy loading or pagination if this grows larger.';
                    echo '</div>';
                    echo '</div>';
                }
                
                // Trace information
                echo '<div class="trace-info">';
                echo '<strong>Full Path:</strong> <span class="trace-path">' . htmlspecialchars($file) . '</span>';
                echo '</div>';
                
                echo '</div>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</div>';
        
        echo '<script>
            function toggleDebugItem(header) {
                const body = header.parentElement.querySelector(".item-body");
                if (body.style.display === "none") {
                    body.style.display = "block";
                } else {
                    body.style.display = "none";
                }
            }
            
            console.log("%c🔌 Plugs Framework Debug", "background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 10px 20px; border-radius: 5px; font-weight: bold; font-size: 14px;");
            console.log("%cDebug session active at line ' . $line . '", "color: #00d4ff; font-weight: 600;");
        </script>';
        
        exit(1);
    }
}

if (!function_exists('d')) {
    /**
     * Plugs Dump - Dump without dying
     * 
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function d(...$vars): void
    {
        ob_start();
        try {
            call_user_func_array('dd', $vars);
        } catch (\Throwable $e) {
            // Catch the exit
        }
        $output = ob_get_clean();
        
        // Remove the exit script
        $output = preg_replace('/<script>[\s\S]*?<\/script>\s*$/', '', $output);
        echo $output;
    }
}

/**
 * Format bytes to human readable format
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get approximate memory size of a variable
 */
function getVariableSize($var): int
{
    $startMemory = memory_get_usage();
    $tmp = unserialize(serialize($var));
    $size = memory_get_usage() - $startMemory;
    unset($tmp);
    
    return max($size, 0);
}

/**
 * Analyze variable for special characteristics
 */
function analyzeVariable($var): array
{
    $analysis = [
        'isQuery' => false,
        'hasQueries' => false,
    ];
    
    // Check if it's a query or array of queries
    if (is_string($var) && preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)/i', trim($var))) {
        $analysis['isQuery'] = true;
    }
    
    if (is_array($var)) {
        foreach ($var as $item) {
            if (is_string($item) && preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)/i', trim($item))) {
                $analysis['isQuery'] = true;
                $analysis['hasQueries'] = true;
                break;
            }
        }
    }
    
    return $analysis;
}

/**
 * Detect N+1 query problems
 */
function detectNPlusOne(array $queries): array
{
    if (!is_array($queries)) {
        return ['detected' => false, 'count' => 0];
    }
    
    $patterns = [];
    
    foreach ($queries as $query) {
        if (!is_string($query)) continue;
        
        // Normalize query (remove specific IDs and values)
        $normalized = preg_replace('/\b\d+\b/', '?', $query);
        $normalized = preg_replace('/(IN\s*\([^)]+\))/i', 'IN (?)', $normalized);
        $normalized = preg_replace('/(["\'])(?:(?=(\\?))\2.)*?\1/', '?', $normalized);
        
        if (!isset($patterns[$normalized])) {
            $patterns[$normalized] = 0;
        }
        $patterns[$normalized]++;
    }
    
    // If we find the same query pattern more than 5 times, it's likely N+1
    foreach ($patterns as $count) {
        if ($count > 5) {
            return ['detected' => true, 'count' => $count];
        }
    }
    
    return ['detected' => false, 'count' => 0];
}

/**
 * Format variable output with better readability
 */
function formatVariableOutput($var, int $depth = 0, int $maxDepth = 5): string
{
    if ($depth > $maxDepth) {
        return '... (max depth reached)';
    }
    
    $indent = str_repeat('  ', $depth);
    $output = '';
    
    if (is_null($var)) {
        return '<span class="syntax-null">NULL</span>';
    }
    
    if (is_bool($var)) {
        return '<span class="syntax-bool">' . ($var ? 'TRUE' : 'FALSE') . '</span>';
    }
    
    if (is_int($var) || is_float($var)) {
        return '<span class="syntax-number">' . $var . '</span>';
    }
    
    if (is_string($var)) {
        $escaped = htmlspecialchars($var);
        if (strlen($var) > 100 && $depth === 0) {
            // Format long strings nicely
            $wrapped = wordwrap($escaped, 80, "\n" . $indent);
            return '<span class="syntax-string">"' . $wrapped . '"</span>';
        }
        return '<span class="syntax-string">"' . $escaped . '"</span>';
    }
    
    if (is_array($var)) {
        if (empty($var)) {
            return '<span class="syntax-array">[]</span>';
        }
        
        $output .= '<span class="syntax-array">Array(' . count($var) . ')</span> [' . "\n";
        
        $isAssoc = array_keys($var) !== range(0, count($var) - 1);
        
        foreach ($var as $key => $value) {
            $output .= $indent . '  ';
            
            if ($isAssoc) {
                if (is_string($key)) {
                    $output .= '<span class="syntax-string">"' . htmlspecialchars($key) . '"</span>';
                } else {
                    $output .= '<span class="syntax-number">' . $key . '</span>';
                }
                $output .= ' => ';
            }
            
            $output .= formatVariableOutput($value, $depth + 1, $maxDepth) . "\n";
        }
        
        $output .= $indent . ']';
        return $output;
    }
    
    if (is_object($var)) {
        $className = get_class($var);
        $output .= '<span class="syntax-object">Object(' . $className . ')</span> {' . "\n";
        
        $reflection = new \ReflectionClass($var);
        $properties = $reflection->getProperties();
        
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = $property->getName();
            $value = $property->getValue($var);
            
            $output .= $indent . '  <span class="syntax-string">' . $name . '</span> => ';
            $output .= formatVariableOutput($value, $depth + 1, $maxDepth) . "\n";
        }
        
        $output .= $indent . '}';
        return $output;
    }
    
    return htmlspecialchars(print_r($var, true));
}