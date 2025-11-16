<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plugs Framework Debug Utility - Laravel 12 Style
|--------------------------------------------------------------------------
|
| Enhanced debugging with query tracking, performance analysis, and beautiful UI
*/

if (!function_exists('dd')) {
    /**
     * Plugs Debug & Die - Dump and terminate execution
     * 
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function dd(...$vars): void
    {
        plugs_dump($vars, true);
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
        plugs_dump($vars, false);
    }
}

if (!function_exists('dq')) {
    /**
     * Dump Queries - Show all executed queries
     * 
     * @param bool $die Whether to terminate execution
     * @return void
     */
    function dq(bool $die = true): void
    {
        $modelClass = 'Plugs\\Base\\Model\\PlugModel';

        if (!class_exists($modelClass)) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<div style="padding: 20px; background: #fee; border: 2px solid #f00; color: #900; font-family: monospace;">';
            echo '<strong>Error:</strong> Model class not found. Make sure PlugModel is loaded.';
            echo '</div>';
            if ($die)
                exit(1);
            return;
        }

        try {
            $queries = call_user_func([$modelClass, 'getQueryLog']);

            if (!is_array($queries)) {
                $queries = [];
            }

            $totalTime = 0;
            foreach ($queries as $query) {
                $totalTime += $query['time'] ?? 0;
            }

            $data = [
                'queries' => $queries,
                'stats' => [
                    'total_queries' => count($queries),
                    'total_time' => $totalTime,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true),
                ]
            ];

            plugs_dump([$data], $die, 'query');
        } catch (\Exception $e) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<div style="padding: 20px; background: #fee; border: 2px solid #f00; color: #900; font-family: monospace;">';
            echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
            if ($die)
                exit(1);
        }
    }
}

if (!function_exists('dm')) {
    /**
     * Dump Model - Show model with relations and queries
     * 
     * @param mixed $model Model instance
     * @param bool $die Whether to terminate execution
     * @return void
     */
    function dm($model, bool $die = true): void
    {
        if (!is_object($model)) {
            plugs_dump([$model], $die);
            return;
        }

        $data = [
            'model' => $model,
            'queries' => method_exists($model, 'getQueryLog') ? $model::getQueryLog() : [],
        ];

        plugs_dump([$data], $die, 'model');
    }
}

/**
 * Core dump function with Laravel 12 styling
 */
function plugs_dump(array $vars, bool $die = false, string $mode = 'default'): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }

    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $backtrace[1] ?? $backtrace[0] ?? [];
    $file = $caller['file'] ?? 'unknown';
    $line = $caller['line'] ?? 'unknown';

    $memoryUsage = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    $executionTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

    // Get query statistics if model is available
    $queryStats = plugs_get_query_stats();

    echo plugs_render_styles();
    echo '<div class="plugs-debug-wrapper">';
    echo plugs_render_header($file, $line, $memoryUsage, $peakMemory, $executionTime, $queryStats);
    echo '<div class="plugs-debug-content">';

    if ($mode === 'query') {
        echo plugs_render_queries($vars[0]);
    } elseif ($mode === 'model') {
        echo plugs_render_model($vars[0]);
    } else {
        echo plugs_render_variables($vars);
    }

    echo '</div>';
    echo '</div>';

    if ($die) {
        exit(1);
    }
}

/**
 * Get query statistics from model
 */
function plugs_get_query_stats(): array
{
    $modelClass = 'Plugs\\Base\\Model\\PlugModel';

    if (!class_exists($modelClass)) {
        return [];
    }

    try {
        $queries = call_user_func([$modelClass, 'getQueryLog']);
        $totalTime = array_sum(array_column($queries, 'time'));

        return [
            'count' => count($queries),
            'time' => $totalTime,
            'queries' => $queries,
        ];
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Render CSS styles (Laravel 12 inspired)
 */
function plugs_render_styles(): string
{
    return <<<'HTML'
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
        background: #f8fafc;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #1e293b;
        line-height: 1.6;
    }
    
    .plugs-debug-wrapper {
        min-height: 100vh;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .plugs-debug-header {
        background: white;
        border-radius: 12px;
        padding: 24px 32px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .logo-section {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .logo {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .logo-text h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 2px;
    }
    
    .logo-text p {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
    }
    
    .status-badge {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    
    .stat-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        transition: all 0.2s ease;
    }
    
    .stat-card:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-2px);
    }
    
    .stat-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 8px;
        letter-spacing: 0.5px;
    }
    
    .stat-value {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        font-family: 'Monaco', 'Menlo', monospace;
    }
    
    .stat-icon {
        font-size: 14px;
        margin-right: 6px;
    }
    
    .plugs-debug-content {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .variables-grid {
        display: grid;
        gap: 20px;
        padding: 24px;
    }
    
    .var-item {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    
    .var-item:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
    }
    
    .var-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }
    
    .var-title {
        font-size: 15px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .var-badges {
        display: flex;
        gap: 8px;
    }
    
    .badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        backdrop-filter: blur(10px);
    }
    
    .var-body {
        padding: 24px;
    }
    
    .section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #667eea;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.5px;
    }
    
    .code-block {
        background: #1e293b;
        color: #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        overflow-x: auto;
        font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        font-size: 13px;
        line-height: 1.8;
        max-height: 600px;
        overflow-y: auto;
    }
    
    .code-block::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    
    .code-block::-webkit-scrollbar-track {
        background: #0f172a;
        border-radius: 5px;
    }
    
    .code-block::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 5px;
    }
    
    .code-line {
        display: block;
        padding: 2px 0;
        white-space: pre-wrap;
        word-break: break-word;
    }
    
    .code-indent {
        display: inline-block;
        width: 20px;
    }
    
    .syntax-key { color: #8b5cf6; font-weight: 600; }
    .syntax-string { color: #34d399; }
    .syntax-number { color: #60a5fa; }
    .syntax-bool { color: #f59e0b; }
    .syntax-null { color: #ef4444; }
    .syntax-array { color: #ec4899; }
    .syntax-object { color: #14b8a6; }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #e2e8f0;
    }
    
    .info-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
    }
    
    .info-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 6px;
    }
    
    .info-value {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
        font-family: 'Monaco', 'Menlo', monospace;
    }
    
    .alert {
        border-left: 4px solid;
        padding: 16px 20px;
        margin-top: 16px;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .alert-warning {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #92400e;
    }
    
    .alert-danger {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }
    
    .alert-success {
        background: #d1fae5;
        border-color: #10b981;
        color: #065f46;
    }
    
    .alert-info {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }
    
    .alert-title {
        font-weight: 700;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .query-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
    }
    
    .query-table th {
        background: #f8fafc;
        padding: 12px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .query-table td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 13px;
    }
    
    .query-table tr:hover {
        background: #f8fafc;
    }
    
    .query-sql {
        font-family: 'Monaco', 'Menlo', monospace;
        color: #667eea;
        font-size: 12px;
        max-width: 600px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .query-time {
        font-weight: 600;
        font-family: 'Monaco', 'Menlo', monospace;
    }
    
    .time-fast { color: #10b981; }
    .time-normal { color: #f59e0b; }
    .time-slow { color: #ef4444; }
    
    .trace-info {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        margin-top: 16px;
        font-size: 12px;
        color: #64748b;
        font-family: 'Monaco', 'Menlo', monospace;
    }
    
    .trace-path {
        color: #667eea;
        font-weight: 600;
    }
    
    @keyframes slideIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .plugs-debug-wrapper {
        animation: slideIn 0.3s ease-out;
    }
</style>
HTML;
}

/**
 * Render header section
 */
function plugs_render_header(string $file, $line, int $memoryUsage, int $peakMemory, float $executionTime, array $queryStats): string
{
    $queryCount = $queryStats['count'] ?? 0;
    $queryTime = $queryStats['time'] ?? 0;

    $html = '<div class="plugs-debug-header">';
    $html .= '<div class="header-top">';
    $html .= '<div class="logo-section">';
    $html .= '<div class="logo">🔌</div>';
    $html .= '<div class="logo-text">';
    $html .= '<h1>Plugs Framework</h1>';
    $html .= '<p>Debug Console</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="status-badge">● Active</div>';
    $html .= '</div>';

    $html .= '<div class="stats-grid">';

    // File & Line
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label"><span class="stat-icon">📁</span>Location</div>';
    $html .= '<div class="stat-value" style="font-size: 13px;">' . htmlspecialchars(basename($file)) . ':' . $line . '</div>';
    $html .= '</div>';

    // Memory Usage
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label"><span class="stat-icon">💾</span>Memory Usage</div>';
    $html .= '<div class="stat-value">' . plugs_format_bytes($memoryUsage) . '</div>';
    $html .= '</div>';

    // Peak Memory
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label"><span class="stat-icon">📊</span>Peak Memory</div>';
    $html .= '<div class="stat-value">' . plugs_format_bytes($peakMemory) . '</div>';
    $html .= '</div>';

    // Execution Time
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label"><span class="stat-icon">⏱️</span>Execution Time</div>';
    $html .= '<div class="stat-value">' . number_format($executionTime * 1000, 2) . ' ms</div>';
    $html .= '</div>';

    // Query Count
    if ($queryCount > 0) {
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-label"><span class="stat-icon">🔍</span>Queries</div>';
        $html .= '<div class="stat-value">' . $queryCount . '</div>';
        $html .= '</div>';

        // Query Time
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-label"><span class="stat-icon">⚡</span>Query Time</div>';
        $html .= '<div class="stat-value">' . number_format($queryTime * 1000, 2) . ' ms</div>';
        $html .= '</div>';
    }

    // Timestamp
    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label"><span class="stat-icon">🕐</span>Timestamp</div>';
    $html .= '<div class="stat-value" style="font-size: 16px;">' . date('H:i:s') . '</div>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Render variables
 */
function plugs_render_variables(array $vars): string
{
    $html = '<div class="variables-grid">';

    if (empty($vars)) {
        $html .= '<div style="text-align: center; padding: 60px; color: #64748b;">';
        $html .= '<div style="font-size: 48px; margin-bottom: 16px;">📦</div>';
        $html .= '<h3>No Variables Provided</h3>';
        $html .= '<p>Pass variables to d() or dd() to debug them</p>';
        $html .= '</div>';
    } else {
        foreach ($vars as $index => $var) {
            $html .= plugs_render_variable($var, $index);
        }
    }

    $html .= '</div>';
    return $html;
}

/**
 * Render single variable
 */
function plugs_render_variable($var, int $index): string
{
    $type = gettype($var);
    $size = plugs_get_variable_size($var);

    $html = '<div class="var-item">';
    $html .= '<div class="var-header">';
    $html .= '<div class="var-title">';
    $html .= '<span>📦</span>';
    $html .= '<span>Variable #' . ($index + 1) . '</span>';
    $html .= '</div>';
    $html .= '<div class="var-badges">';
    $html .= '<span class="badge">' . $type . '</span>';
    $html .= '<span class="badge">' . plugs_format_bytes($size) . '</span>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="var-body">';
    $html .= '<div class="section-title">📄 Value</div>';
    $html .= '<div class="code-block">';
    $html .= plugs_format_value($var, 0);
    $html .= '</div>';

    // Info grid
    $html .= '<div class="info-grid">';

    $html .= '<div class="info-card">';
    $html .= '<div class="info-label">Type</div>';
    $html .= '<div class="info-value">' . ucfirst($type) . '</div>';
    $html .= '</div>';

    $html .= '<div class="info-card">';
    $html .= '<div class="info-label">Size</div>';
    $html .= '<div class="info-value">' . plugs_format_bytes($size) . '</div>';
    $html .= '</div>';

    if (is_countable($var)) {
        $html .= '<div class="info-card">';
        $html .= '<div class="info-label">Count</div>';
        $html .= '<div class="info-value">' . count($var) . '</div>';
        $html .= '</div>';
    }

    if (is_string($var)) {
        $html .= '<div class="info-card">';
        $html .= '<div class="info-label">Length</div>';
        $html .= '<div class="info-value">' . strlen($var) . '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    // Warnings
    if ($size > 5 * 1024 * 1024) {
        $html .= '<div class="alert alert-danger">';
        $html .= '<div class="alert-title">❌ Critical Memory Warning</div>';
        $html .= 'Variable size is extremely large (' . plugs_format_bytes($size) . '). Consider pagination or chunking.';
        $html .= '</div>';
    } elseif ($size > 1 * 1024 * 1024) {
        $html .= '<div class="alert alert-warning">';
        $html .= '<div class="alert-title">⚠️ Large Variable</div>';
        $html .= 'Variable size is significant (' . plugs_format_bytes($size) . '). Monitor memory usage.';
        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Render queries
 */
function plugs_render_queries(array $data): string
{
    $queries = $data['queries'] ?? [];
    $stats = $data['stats'] ?? [];

    $html = '<div style="padding: 24px;">';

    // Stats cards
    $html .= '<div class="stats-grid" style="margin-bottom: 24px;">';

    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label">Total Queries</div>';
    $html .= '<div class="stat-value">' . ($stats['total_queries'] ?? 0) . '</div>';
    $html .= '</div>';

    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label">Total Time</div>';
    $html .= '<div class="stat-value">' . number_format(($stats['total_time'] ?? 0) * 1000, 2) . ' ms</div>';
    $html .= '</div>';

    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label">Avg Time</div>';
    $avgTime = $stats['total_queries'] > 0 ? ($stats['total_time'] / $stats['total_queries']) : 0;
    $html .= '<div class="stat-value">' . number_format($avgTime * 1000, 2) . ' ms</div>';
    $html .= '</div>';

    $html .= '</div>';

    // Performance assessment
    $totalQueries = $stats['total_queries'] ?? 0;
    if ($totalQueries > 20) {
        $html .= '<div class="alert alert-danger">';
        $html .= '<div class="alert-title">❌ Too Many Queries</div>';
        $html .= "Detected {$totalQueries} queries. This will impact performance. Consider eager loading or query optimization.";
        $html .= '</div>';
    } elseif ($totalQueries > 10) {
        $html .= '<div class="alert alert-warning">';
        $html .= '<div class="alert-title">⚠️ High Query Count</div>';
        $html .= "Found {$totalQueries} queries. Consider optimization before production.";
        $html .= '</div>';
    } else {
        $html .= '<div class="alert alert-success">';
        $html .= '<div class="alert-title">✅ Good Performance</div>';
        $html .= "Query count ({$totalQueries}) is within acceptable limits.";
        $html .= '</div>';
    }

    // N+1 detection
    $nPlusOne = plugs_detect_n_plus_one($queries);
    if ($nPlusOne['detected']) {
        $html .= '<div class="alert alert-danger" style="margin-top: 16px;">';
        $html .= '<div class="alert-title">⚠️ N+1 Query Problem Detected</div>';
        $html .= "Found {$nPlusOne['count']} similar queries. Use eager loading with <code>with()</code> to fix this.";
        $html .= '</div>';
    }

    // Query table
    $html .= '<div style="margin-top: 24px;">';
    $html .= '<div class="section-title">🔍 Executed Queries</div>';
    $html .= '<table class="query-table">';
    $html .= '<thead><tr>';
    $html .= '<th>#</th>';
    $html .= '<th>Query</th>';
    $html .= '<th>Bindings</th>';
    $html .= '<th>Time</th>';
    $html .= '<th>Timestamp</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    foreach ($queries as $index => $query) {
        $time = $query['time'] ?? 0;
        $timeClass = $time < 0.01 ? 'time-fast' : ($time < 0.05 ? 'time-normal' : 'time-slow');

        $html .= '<tr>';
        $html .= '<td>' . ($index + 1) . '</td>';
        $html .= '<td><div class="query-sql" title="' . htmlspecialchars($query['query'] ?? '') . '">' . htmlspecialchars($query['query'] ?? '') . '</div></td>';
        $html .= '<td><code>' . json_encode($query['bindings'] ?? []) . '</code></td>';
        $html .= '<td class="query-time ' . $timeClass . '">' . number_format($time * 1000, 2) . ' ms</td>';
        $html .= '<td>' . ($query['timestamp'] ?? '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}

/**
 * Render model
 */
function plugs_render_model(array $data): string
{
    $model = $data['model'] ?? null;
    $queries = $data['queries'] ?? [];

    $html = '<div style="padding: 24px;">';

    if ($model) {
        $html .= '<div class="section-title">📦 Model Data</div>';
        $html .= '<div class="code-block">';
        $html .= plugs_format_value($model, 0);
        $html .= '</div>';

        // Model info
        if (is_object($model)) {
            $html .= '<div class="info-grid">';

            $html .= '<div class="info-card">';
            $html .= '<div class="info-label">Class</div>';
            $html .= '<div class="info-value" style="font-size: 13px;">' . get_class($model) . '</div>';
            $html .= '</div>';

            if (method_exists($model, 'getTable')) {
                $html .= '<div class="info-card">';
                $html .= '<div class="info-label">Table</div>';
                $html .= '<div class="info-value">' . $model->getTable() . '</div>';
                $html .= '</div>';
            }

            if (method_exists($model, 'getKey')) {
                $html .= '<div class="info-card">';
                $html .= '<div class="info-label">Primary Key</div>';
                $html .= '<div class="info-value">' . ($model->getKey() ?? 'null') . '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }
    }

    // Show queries if available
    if (!empty($queries)) {
        $html .= '<div style="margin-top: 24px;">';
        $html .= '<div class="section-title">🔍 Related Queries</div>';
        $html .= '<div class="alert alert-info">';
        $html .= '<div class="alert-title">ℹ️ Query Count</div>';
        $html .= 'This model executed ' . count($queries) . ' database queries.';
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Format value for display with proper indentation and syntax highlighting
 */
function plugs_format_value($value, int $depth = 0, int $maxDepth = 10): string
{
    if ($depth > $maxDepth) {
        return '<span class="syntax-null">... (max depth)</span>';
    }

    $indent = str_repeat('  ', $depth);

    if (is_null($value)) {
        return '<span class="syntax-null">null</span>';
    }

    if (is_bool($value)) {
        return '<span class="syntax-bool">' . ($value ? 'true' : 'false') . '</span>';
    }

    if (is_int($value) || is_float($value)) {
        return '<span class="syntax-number">' . $value . '</span>';
    }

    if (is_string($value)) {
        $escaped = htmlspecialchars($value);
        if (strlen($value) > 100) {
            return '<span class="syntax-string">"' . substr($escaped, 0, 100) . '..."</span>';
        }
        return '<span class="syntax-string">"' . $escaped . '"</span>';
    }

    if (is_array($value)) {
        if (empty($value)) {
            return '<span class="syntax-array">[]</span>';
        }

        $html = '<span class="syntax-array">Array(' . count($value) . ')</span> [<br>';
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        $count = 0;
        foreach ($value as $key => $val) {
            if ($count > 50) {
                $html .= $indent . '  <span class="syntax-null">... (' . (count($value) - 50) . ' more items)</span><br>';
                break;
            }

            $html .= $indent . '  ';

            if ($isAssoc) {
                if (is_string($key)) {
                    $html .= '<span class="syntax-key">"' . htmlspecialchars($key) . '"</span> => ';
                } else {
                    $html .= '<span class="syntax-number">' . $key . '</span> => ';
                }
            }

            $html .= plugs_format_value($val, $depth + 1, $maxDepth);
            $html .= '<br>';
            $count++;
        }

        $html .= $indent . ']';
        return $html;
    }

    if (is_object($value)) {
        $className = get_class($value);
        $html = '<span class="syntax-object">Object(' . $className . ')</span> {<br>';

        try {
            $reflection = new \ReflectionClass($value);
            $properties = $reflection->getProperties();

            $count = 0;
            foreach ($properties as $property) {
                if ($count > 50) {
                    $html .= $indent . '  <span class="syntax-null">... (' . (count($properties) - 50) . ' more properties)</span><br>';
                    break;
                }

                $property->setAccessible(true);
                $propName = $property->getName();
                $propValue = $property->getValue($value);

                $html .= $indent . '  ';
                $html .= '<span class="syntax-key">' . htmlspecialchars($propName) . '</span> => ';
                $html .= plugs_format_value($propValue, $depth + 1, $maxDepth);
                $html .= '<br>';
                $count++;
            }
        } catch (\Exception $e) {
            $html .= $indent . '  <span class="syntax-null">Unable to reflect object</span><br>';
        }

        $html .= $indent . '}';
        return $html;
    }

    return '<span class="syntax-null">' . htmlspecialchars(print_r($value, true)) . '</span>';
}

/**
 * Format bytes to human readable
 */
function plugs_format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get variable memory size
 */
function plugs_get_variable_size($var): int
{
    $startMemory = memory_get_usage();
    $tmp = unserialize(serialize($var));
    $size = memory_get_usage() - $startMemory;
    unset($tmp);

    return max($size, 0);
}

/**
 * Detect N+1 query problems
 */
function plugs_detect_n_plus_one(array $queries): array
{
    if (empty($queries)) {
        return ['detected' => false, 'count' => 0];
    }

    $patterns = [];

    foreach ($queries as $query) {
        $sql = $query['query'] ?? '';

        if (!is_string($sql)) {
            continue;
        }

        // Normalize query
        $normalized = preg_replace('/\b\d+\b/', '?', $sql);
        $normalized = preg_replace('/(IN\s*\([^)]+\))/i', 'IN (?)', $normalized);
        $normalized = preg_replace('/(["\'])(?:(?=(\\?))\2.)*?\1/', '?', $normalized);

        if (!isset($patterns[$normalized])) {
            $patterns[$normalized] = 0;
        }
        $patterns[$normalized]++;
    }

    // If same pattern appears more than 5 times, it's likely N+1
    foreach ($patterns as $count) {
        if ($count > 5) {
            return ['detected' => true, 'count' => $count];
        }
    }

    return ['detected' => false, 'count' => 0];
}