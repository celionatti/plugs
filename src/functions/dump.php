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
            if ($die) {
                exit(1);
            }

            return;
        }

        try {
            $queries = call_user_func([$modelClass, 'getQueryLog']);



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
                ],
            ];

            plugs_dump([$data], $die, 'query');
        } catch (\Exception $e) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<div style="padding: 20px; background: #fee; border: 2px solid #f00; color: #900; font-family: monospace;">';
            echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
            if ($die) {
                exit(1);
            }
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

    echo <<<'JS'
<script>
    // Toggle collapsible variable view
    function toggleVar(header) {
        const body = header.nextElementSibling;
        const isCollapsed = body.style.display === 'none';
        body.style.display = isCollapsed ? 'block' : 'none';
        header.style.opacity = isCollapsed ? '1' : '0.7';
    }

    // Global toggle (Expand/Collapse All)
    function toggleAll(expand) {
        document.querySelectorAll('.var-body').forEach(body => {
            body.style.display = expand ? 'block' : 'none';
            body.previousElementSibling.style.opacity = expand ? '1' : '0.7';
        });
    }

    // Search & Filter
    document.getElementById('debug-search').addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.var-item').forEach(item => {
            const text = item.innerText.toLowerCase();
            item.classList.toggle('hidden', !text.includes(query));
        });
    });

    // Copy to Clipboard
    function copyValue(icon) {
        const container = icon.parentElement;
        // Try to get data-full-value from string spans, otherwise use textContent
        const stringSpan = container.querySelector('.syntax-string');
        const textToCopy = stringSpan ? stringSpan.getAttribute('data-full-value') : container.innerText.trim();
        
        navigator.clipboard.writeText(textToCopy).then(() => {
            const originalIcon = icon.innerText;
            icon.innerText = '✅';
            icon.style.color = '#10b981';
            setTimeout(() => {
                icon.innerText = originalIcon;
                icon.style.color = '';
            }, 1500);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            icon.innerText = '❌';
            setTimeout(() => icon.innerText = originalIcon, 1500);
        });
    }

    // Secret Masking
    function revealSecret(el) {
        const secret = el.getAttribute('data-secret');
        el.innerText = secret;
        el.classList.remove('masked-secret');
        el.style.color = '#10b981';
        el.style.fontStyle = 'normal';
        el.style.background = 'rgba(16, 185, 129, 0.1)';
    }

    // Breadcrumbs on hover
    const breadcrumbBar = document.getElementById('breadcrumb-bar');
    document.addEventListener('mouseover', (e) => {
        const keySpan = e.target.closest('.syntax-key');
        if (keySpan) {
            const path = keySpan.getAttribute('data-path');
            if (path) {
                breadcrumbBar.innerHTML = 'Current Path: <span class="breadcrumb-item">' + path + '</span>';
                breadcrumbBar.style.display = 'block';
            }
        } else if (!e.target.closest('.breadcrumbs')) {
            breadcrumbBar.style.display = 'none';
        }
    });

    // Handle string truncation toggle (optional improvement)
    document.querySelectorAll('.syntax-string').forEach(span => {
        if (span.innerText.endsWith('..."')) {
            span.style.cursor = 'pointer';
            span.title = 'Click to show full string';
            span.onclick = function() {
                const full = this.getAttribute('data-full-value');
                this.innerText = '"' + full + '"';
                this.style.cursor = 'default';
                this.title = '';
            };
        }
    });
</script>
JS;

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
 * Render CSS styles (Plugs Dark Premium)
 */
function plugs_render_styles(): string
{
    return <<<'HTML'
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
    @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Dancing+Script:wght@700&display=swap");

    :root {
        --bg-body: #0a0f1d;
        --bg-card: rgba(30, 41, 59, 0.7);
        --border-color: rgba(51, 65, 85, 0.5);
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --text-muted: #94a3b8;
        --accent-primary: #a855f7;
        --accent-secondary: #6366f1;
        --danger: #ef4444;
        --warning: #f59e0b;
        --success: #10b981;
        --code-bg: #0d1117;
        --glass-bg: rgba(15, 23, 42, 0.6);
        --glow: 0 0 20px rgba(168, 85, 247, 0.15);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
        background: var(--bg-body);
        font-family: 'Outfit', sans-serif;
        color: var(--text-primary);
        line-height: 1.6;
        font-size: 15px;
    }
    
    .plugs-debug-wrapper {
        min-height: 100vh;
        padding: 40px 20px 100px 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .plugs-debug-header {
        background: var(--bg-card);
        backdrop-filter: blur(12px);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 32px;
        margin-bottom: 24px;
        box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.5), var(--glow);
        position: relative;
        overflow: hidden;
    }
    
    .plugs-debug-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
    }
    
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .logo-section {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .brand {
        font-family: "Dancing Script", cursive;
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--text-primary);
        text-decoration: none;
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .logo-text p {
        font-size: 14px;
        color: var(--text-muted);
        font-weight: 500;
        margin-top: -8px;
    }

    .header-controls {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .search-input {
        background: rgba(15, 23, 42, 0.5);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 8px 16px;
        color: var(--text-primary);
        font-family: inherit;
        font-size: 13px;
        width: 250px;
        outline: none;
        transition: all 0.2s;
    }

    .search-input:focus {
        border-color: var(--accent-primary);
        box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2);
    }

    .global-actions {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .action-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-primary);
    }
    
    .status-badge {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        padding: 8px 16px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
    }
    
    .stat-card {
        background: rgba(15, 23, 42, 0.3);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: var(--accent-primary);
        background: rgba(15, 23, 42, 0.5);
    }
    
    .stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 12px;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .stat-value {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        font-family: 'JetBrains Mono', monospace;
    }
    
    .plugs-debug-content {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    }
    
    .variables-grid {
        display: grid;
        gap: 24px;
        padding: 32px;
    }
    
    .var-item {
        background: rgba(15, 23, 42, 0.2);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: opacity 0.3s;
    }

    .var-item.hidden {
        display: none;
    }
    
    .var-header {
        padding: 16px 24px;
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }
    
    .var-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .var-badges {
        display: flex;
        gap: 8px;
    }
    
    .badge {
        background: rgba(139, 92, 246, 0.1);
        color: var(--accent-primary);
        padding: 4px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid rgba(139, 92, 246, 0.2);
    }
    
    .var-body {
        padding: 24px;
    }
    
    .section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--accent-secondary);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.1em;
    }
    
    .code-block {
        background: var(--code-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 12px;
        padding: 24px;
        overflow-x: auto;
        font-family: 'JetBrains Mono', monospace;
        font-size: 14px;
        line-height: 1.7;
        position: relative;
    }
    
    .syntax-key { color: var(--accent-primary); font-weight: 500; cursor: help; }
    .syntax-key:hover { text-decoration: underline; }
    .syntax-string { color: var(--success); }
    .syntax-number { color: var(--accent-secondary); }
    .syntax-bool { color: var(--warning); }
    .syntax-null { color: var(--danger); }
    .syntax-array { color: var(--text-secondary); opacity: 0.8; }
    .syntax-object { color: var(--accent-secondary); font-weight: 600; }
    
    .copy-icon {
        position: absolute;
        right: 12px;
        top: 12px;
        color: var(--text-muted);
        cursor: pointer;
        opacity: 0;
        transition: all 0.2s;
        padding: 4px;
        border-radius: 4px;
    }

    .code-block:hover .copy-icon {
        opacity: 1;
    }

    .copy-icon:hover {
        color: var(--text-primary);
        background: rgba(255, 255, 255, 0.05);
    }

    .masked-secret {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        padding: 2px 6px;
        border-radius: 4px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-style: italic;
    }

    .breadcrumbs {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(30, 41, 59, 0.9);
        backdrop-filter: blur(12px);
        border: 1px solid var(--border-color);
        padding: 10px 24px;
        border-radius: 99px;
        font-size: 13px;
        color: var(--text-secondary);
        z-index: 1000;
        display: none;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
    }
    
    .breadcrumb-item {
        color: var(--accent-secondary);
        font-weight: 600;
    }

    .alert {
        padding: 16px 20px;
        margin-top: 20px;
        border-radius: 12px;
        font-size: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: #fbbf24; }
    .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
    .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
    
    .alert-title {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.05em;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid var(--border-color);
    }
    
    .info-card {
        background: rgba(15, 23, 42, 0.2);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 16px;
    }
    
    .info-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 0.05em;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        font-family: 'JetBrains Mono', monospace;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .plugs-debug-wrapper {
        animation: fadeIn 0.4s ease-out;
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
    $html .= '<div class="brand">Plugs</div>';
    $html .= '<div class="logo-text"><p>Debug Console</p></div>';
    $html .= '</div>';

    $html .= '<div class="header-controls">';
    $html .= '<input type="text" class="search-input" id="debug-search" placeholder="Search variables, keys, values...">';
    $html .= '<div class="global-actions">';
    $html .= '<button class="action-btn" onclick="toggleAll(true)"><span>Expand</span> ⊞</button>';
    $html .= '<button class="action-btn" onclick="toggleAll(false)"><span>Collapse</span> ⊟</button>';
    $html .= '</div>';
    $html .= '<div class="status-badge">Live Debugging</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="stats-grid">';
    $html .= '<div class="stat-card"><div class="stat-label">Location</div><div class="stat-value" style="font-size: 13px;">' . htmlspecialchars(basename($file)) . ':' . $line . '</div></div>';
    $html .= '<div class="stat-card"><div class="stat-label">Memory</div><div class="stat-value">' . plugs_format_bytes($memoryUsage) . '</div></div>';
    $html .= '<div class="stat-card"><div class="stat-label">Execution</div><div class="stat-value">' . number_format($executionTime * 1000, 2) . ' ms</div></div>';
    if ($queryCount > 0) {
        $html .= '<div class="stat-card"><div class="stat-label">Queries</div><div class="stat-value">' . $queryCount . ' (' . number_format($queryTime * 1000, 1) . 'ms)</div></div>';
    }
    $html .= '<div class="stat-card"><div class="stat-label">Time</div><div class="stat-value">' . date('H:i:s') . '</div></div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Render variables
 */
function plugs_render_variables(array $vars): string
{
    $html = '<div class="variables-grid" id="vars-container">';

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

    $html .= '</div><div id="breadcrumb-bar" class="breadcrumbs"></div>';

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
    $html .= '<div class="var-header" onclick="toggleVar(this)">';
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
    $html .= '<div class="copy-icon" title="Copy to clipboard" onclick="copyValue(this)">📋</div>';
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

    $html = '<div style="padding: 32px;">';
    $html .= '<div class="section-title" style="margin-bottom: 24px; font-size: 18px; color: var(--text-primary);">📊 Query Insights</div>';

    // Stats cards
    $html .= '<div class="stats-grid" style="margin-bottom: 32px;">';

    $html .= '<div class="stat-card" style="background: linear-gradient(145deg, rgba(168, 85, 247, 0.1), transparent);">';
    $html .= '<div class="stat-label">✨ Total Queries</div>';
    $html .= '<div class="stat-value">' . ($stats['total_queries'] ?? 0) . '</div>';
    $html .= '</div>';

    $html .= '<div class="stat-card" style="background: linear-gradient(145deg, rgba(99, 102, 241, 0.1), transparent);">';
    $html .= '<div class="stat-label">⏱️ Total Time</div>';
    $html .= '<div class="stat-value">' . number_format(($stats['total_time'] ?? 0) * 1000, 2) . ' ms</div>';
    $html .= '</div>';

    $html .= '<div class="stat-card">';
    $html .= '<div class="stat-label">🧠 Memory Peak</div>';
    $html .= '<div class="stat-value">' . plugs_format_bytes($stats['peak_memory'] ?? memory_get_peak_usage(true)) . '</div>';
    $html .= '</div>';

    $html .= '</div>';

    // Performance assessment
    $totalQueries = $stats['total_queries'] ?? 0;
    if ($totalQueries > 20) {
        $html .= '<div class="alert alert-danger" style="animation: pulse 2s infinite;">';
        $html .= '<div class="alert-title">🔥 Critical Warning</div>';
        $html .= "High query volume ({$totalQueries}). This will significantly slow down production response times.";
        $html .= '</div>';
    } elseif ($totalQueries > 10) {
        $html .= '<div class="alert alert-warning">';
        $html .= '<div class="alert-title">⚡ Optimization Recommended</div>';
        $html .= "Multiple queries detected. Use eager loading (with()) to reduce database roundtrips.";
        $html .= '</div>';
    }

    // Query List
    $html .= '<div style="margin-top: 40px;">';
    $html .= '<div class="section-title">🔮 Execution Log</div>';

    foreach ($queries as $index => $query) {
        $time = $query['time'] ?? 0;
        $ms = number_format($time * 1000, 2);
        $isSlow = $time > 0.05;

        $html .= '<div class="var-item" style="margin-bottom: 16px;">';
        $html .= '<div class="var-header" onclick="toggleVar(this)">';
        $html .= '<div class="var-title"><span>#' . ($index + 1) . '</span> <code style="color: var(--accent-secondary);">' . substr(htmlspecialchars($query['query']), 0, 60) . (strlen($query['query']) > 60 ? '...' : '') . '</code></div>';
        $html .= '<div class="var-badges">';
        if ($isSlow)
            $html .= '<span class="badge" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">SLOW</span>';
        $html .= '<span class="badge">' . $ms . ' ms</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="var-body" style="display: none;">';
        $html .= '<div class="code-block">';
        $html .= '<code class="syntax-string">' . htmlspecialchars($query['query']) . '</code>';
        $html .= '</div>';
        if (!empty($query['bindings'])) {
            $html .= '<div style="margin-top: 12px; font-size: 13px; color: var(--text-muted);">Bindings: <code>' . json_encode($query['bindings']) . '</code></div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Add CSS Animation
 */
function plugs_append_animations(): string
{
    return "
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.01); }
        100% { transform: scale(1); }
    }
    ";
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
function plugs_format_value($value, int $depth = 0, array $path = []): string
{
    if ($depth > 12) {
        return '<span class="syntax-null">... (depth limit)</span>';
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

        return '<span class="syntax-string" data-full-value="' . $escaped . '">"' . (strlen($value) > 200 ? substr($escaped, 0, 200) . '...' : $escaped) . '"</span>';
    }

    if (is_array($value)) {
        if (empty($value)) {
            return '<span class="syntax-array">[]</span>';
        }
        $html = '<span class="syntax-array">Array(' . count($value) . ')</span> [<br>';
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        foreach ($value as $key => $val) {
            $currentPath = array_merge($path, [$key]);
            $isSecret = is_string($key) && preg_match('/(password|secret|key|token|auth|pass|cred)/i', $key);

            $html .= $indent . '  ';
            if ($isAssoc) {
                $html .= '<span class="syntax-key" data-path="' . implode(' → ', $currentPath) . '">' . (is_string($key) ? '"' . htmlspecialchars($key) . '"' : $key) . '</span> => ';
            }

            if ($isSecret && !empty($val)) {
                $html .= '<span class="masked-secret" onclick="revealSecret(this)" data-secret="' . htmlspecialchars(is_string($val) ? (string) $val : json_encode($val)) . '">🔒 [masked secret]</span>';
            } else {
                $html .= plugs_format_value($val, $depth + 1, $currentPath);
            }
            $html .= '<br>';
        }
        $html .= $indent . ']';

        return $html;
    }

    if (is_object($value)) {
        $className = get_class($value);
        $html = '<span class="syntax-object">Object(' . $className . ')</span> {<br>';

        try {
            // Check if object has __debugInfo() method - use it for cleaner output
            if (method_exists($value, '__debugInfo')) {
                $debugData = $value->__debugInfo();
                foreach ($debugData as $name => $val) {
                    $currentPath = array_merge($path, [$name]);
                    $isSecret = is_string($name) && preg_match('/(password|secret|key|token|auth|pass|cred)/i', $name);

                    $html .= $indent . '  <span class="syntax-key" data-path="' . implode(' → ', $currentPath) . '">' . htmlspecialchars($name) . '</span> => ';

                    if ($isSecret && !empty($val)) {
                        $html .= '<span class="masked-secret" onclick="revealSecret(this)" data-secret="' . htmlspecialchars(is_string($val) ? (string) $val : json_encode($val)) . '">🔒 [masked secret]</span>';
                    } else {
                        $html .= plugs_format_value($val, $depth + 1, $currentPath);
                    }
                    $html .= '<br>';
                }
            } else {
                // Fallback to reflection for objects without __debugInfo
                $reflection = new \ReflectionClass($value);
                $properties = $reflection->getProperties();
                foreach ($properties as $property) {
                    $property->setAccessible(true);
                    $name = $property->getName();
                    $val = $property->getValue($value);
                    $currentPath = array_merge($path, [$name]);
                    $html .= $indent . '  <span class="syntax-key" data-path="' . implode(' → ', $currentPath) . '">' . htmlspecialchars($name) . '</span> => ' . plugs_format_value($val, $depth + 1, $currentPath) . '<br>';
                }
            }
        } catch (\Exception $e) {
            $html .= $indent . '  <span class="syntax-null">Unable to reflect</span><br>';
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
