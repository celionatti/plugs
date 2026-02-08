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

if (!function_exists('de')) {
    /**
     * Dump Exception - Show exception with beautiful stack trace
     *
     * @param Throwable $exception Exception to dump
     * @param bool $die Whether to terminate execution
     * @return void
     */
    function de(Throwable $exception, bool $die = true): void
    {
        $data = [
            'exception' => $exception,
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
            'previous' => $exception->getPrevious(),
        ];

        plugs_dump([$data], $die, 'exception');
    }
}

if (!function_exists('dh')) {
    /**
     * Dump HTTP - Show HTTP response with headers, body, and timing
     *
     * @param mixed $response HTTP response object or array
     * @param bool $die Whether to terminate execution
     * @return void
     */
    function dh($response, bool $die = true): void
    {
        $data = [];

        if (is_object($response)) {
            // Handle Plugs HTTPResponse
            if (method_exists($response, 'getStatusCode')) {
                $data['status_code'] = $response->getStatusCode();
            }
            if (method_exists($response, 'getHeaders')) {
                $data['headers'] = $response->getHeaders();
            }
            if (method_exists($response, 'getBody')) {
                $body = $response->getBody();
                $data['body'] = $body;
                // Try to detect and parse JSON
                if (is_string($body)) {
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['body_parsed'] = $decoded;
                    }
                }
            }
            if (method_exists($response, 'getReasonPhrase')) {
                $data['reason'] = $response->getReasonPhrase();
            }
            if (method_exists($response, 'getRequestTime')) {
                $data['request_time'] = $response->getRequestTime();
            }
            if (method_exists($response, 'getUrl')) {
                $data['url'] = $response->getUrl();
            }
            $data['response_class'] = get_class($response);
        } else {
            $data = ['response' => $response];
        }

        plugs_dump([$data], $die, 'http');
    }
}

if (!function_exists('dp')) {
    /**
     * Dump Profile - Profile a callback and dump results with query stats
     *
     * @param callable $callback The code to profile
     * @param bool $die Whether to terminate execution
     * @return void
     */
    function dp(callable $callback, bool $die = true): void
    {
        $modelClass = 'Plugs\\Base\\Model\\PlugModel';

        /** @phpstan-ignore-next-line */
        if (class_exists($modelClass) && method_exists($modelClass, 'profile')) {
            $profile = call_user_func([$modelClass, 'profile'], $callback);
            plugs_dump([$profile], $die, 'profile');
        } else {
            // Fallback basic profiling
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);

            $result = $callback();

            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $profile = [
                'result' => $result,
                'execution_time' => $endTime - $startTime,
                'execution_time_ms' => ($endTime - $startTime) * 1000,
                'memory_used' => $endMemory - $startMemory,
                'queries' => [],
                'query_count' => 0,
            ];

            plugs_dump([$profile], $die, 'profile');
        }
    }
}

// Global theme setting
global $plugs_debug_theme;
$plugs_debug_theme = 'dark';

if (!function_exists('dt')) {
    /**
     * Set Debug Theme
     *
     * @param string $theme Theme name: 'dark', 'light', 'dracula', 'monokai'
     * @return void
     */
    function dt(string $theme): void
    {
        global $plugs_debug_theme;
        $validThemes = ['dark', 'light', 'dracula', 'monokai'];
        if (in_array($theme, $validThemes)) {
            $plugs_debug_theme = $theme;
        }
    }
}

/**
 * Render profile dump
 */
function plugs_dump_profile(array $profile, bool $die = false, ?string $nonce = null): void
{
    plugs_dump([$profile], $die, 'profile', $nonce);
}

/**
 * Core dump function with Laravel 12 styling
 */
function plugs_dump(array $vars, bool $die = false, string $mode = 'default', ?string $nonce = null): void
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

    global $plugs_debug_theme;
    $theme = $plugs_debug_theme ?? 'dark';

    $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

    echo plugs_render_styles(!$die, $nonce);
    // Add plugs-safe-scope class if not dying (which implies we are injecting into an existing page)
    $scopeClass = $die ? '' : 'plugs-safe-scope';
    echo '<div class="plugs-debug-wrapper ' . $scopeClass . '" data-theme="' . $theme . '">';
    if ($die) {
        echo '<script' . $nonceAttr . '>document.body.setAttribute("data-theme", "' . $theme . '");</script>';
    }
    echo plugs_render_header($file, $line, $memoryUsage, $peakMemory, $executionTime, $queryStats);
    echo '<div class="plugs-debug-content">';

    if ($mode === 'query') {
        echo plugs_render_queries($vars[0]);
    } elseif ($mode === 'model') {
        echo plugs_render_model($vars[0]);
    } elseif ($mode === 'exception') {
        echo plugs_render_exception($vars[0]);
    } elseif ($mode === 'http') {
        echo plugs_render_http($vars[0]);
    } elseif ($mode === 'profile') {
        echo plugs_render_profile($vars[0]);
    } else {
        echo plugs_render_variables($vars);
    }

    echo '</div>';
    echo '</div>';

    echo <<<JS
<script{$nonceAttr}>

    (function() {
        // Event Delegation for Plugs Debugging
        document.addEventListener('click', function(e) {
            // Tab Switching
            const tabBtn = e.target.closest('.plugs-tab-btn, .tab-btn');
            if (tabBtn) {
                const tabId = tabBtn.getAttribute('data-tab') || (tabBtn.getAttribute('onclick') ? tabBtn.getAttribute('onclick').match(/'([^']+)'/)[1] : null);
                if (tabId) {
                    const container = tabBtn.closest('.plugs-debug-wrapper, #plugs-profiler-modal');
                    if (container) {
                        container.querySelectorAll('.tab-btn, .plugs-tab-btn').forEach(b => b.classList.remove('active'));
                        container.querySelectorAll('.tab-content, .plugs-tab-content').forEach(c => c.classList.remove('active'));
                        tabBtn.classList.add('active');
                        const target = container.querySelector('#' + tabId);
                        if (target) target.classList.add('active');
                    }
                }
                return;
            }

            // Toggle Var Header
            const varHeader = e.target.closest('.var-header');
            if (varHeader) {
                const body = varHeader.nextElementSibling;
                if (body) {
                    const isCollapsed = body.style.display === 'none';
                    body.style.display = isCollapsed ? 'block' : 'none';
                    varHeader.style.opacity = isCollapsed ? '1' : '0.7';
                }
                return;
            }

            // Copy Value
            const copyIcon = e.target.closest('.plugs-copy-icon');
            if (copyIcon) {
                const container = copyIcon.parentElement;
                const stringSpan = container.querySelector('.plugs-syntax-string');
                const textToCopy = stringSpan ? stringSpan.getAttribute('data-full-value') : container.innerText.trim();
                
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const originalText = copyIcon.innerText;
                    copyIcon.innerText = '✅';
                    copyIcon.style.color = '#10b981';
                    setTimeout(() => {
                        copyIcon.innerText = originalText;
                        copyIcon.style.color = '';
                    }, 1500);
                }).catch(err => console.error('Failed to copy: ', err));
                return;
            }

            // Reveal Secret
            const secret = e.target.closest('.plugs-masked-secret');
            if (secret) {
                const val = secret.getAttribute('data-secret');
                if (val) {
                    secret.innerText = val;
                    secret.classList.remove('plugs-masked-secret');
                    secret.style.color = '#10b981';
                    secret.style.fontStyle = 'normal';
                    secret.style.background = 'rgba(16, 185, 129, 0.1)';
                }
                return;
            }
            
            // Expand/Collapse All
            const expandBtn = e.target.closest('.plugs-action-btn[data-action="expand"]');
            if (expandBtn) {
                plugsToggleAll(true);
                return;
            }
            
            const collapseBtn = e.target.closest('.plugs-action-btn[data-action="collapse"]');
            if (collapseBtn) {
                plugsToggleAll(false);
                return;
            }

            // Truncated String Click
            const truncatedStr = e.target.closest('.plugs-syntax-string[data-truncated="true"]');
            if (truncatedStr) {
                const full = truncatedStr.getAttribute('data-full-value');
                truncatedStr.innerText = '"' + full + '"';
                truncatedStr.style.cursor = 'default';
                truncatedStr.removeAttribute('title');
                truncatedStr.removeAttribute('data-truncated');
            }
        });

        // Search & Filter
        const debugSearch = document.getElementById('debug-search');
        if (debugSearch) {
            debugSearch.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase();
                document.querySelectorAll('.plugs-tab-content.active .plugs-var-item, .plugs-debug-content .plugs-var-item').forEach(item => {
                    const text = item.innerText.toLowerCase();
                    if (item.classList.contains('var-item') || item.classList.contains('plugs-var-item')) {
                        item.style.display = text.includes(query) ? 'block' : 'none';
                    }
                });
            });
        }

        // Breadcrumbs
        const breadcrumbBar = document.getElementById('breadcrumb-bar');
        if (breadcrumbBar) {
            document.addEventListener('mouseover', (e) => {
                const keySpan = e.target.closest('.plugs-syntax-key');
                if (keySpan) {
                    const path = keySpan.getAttribute('data-path');
                    if (path) {
                        breadcrumbBar.innerHTML = 'Current Path: <span class="plugs-breadcrumb-item">' + path + '</span>';
                        breadcrumbBar.style.display = 'block';
                    }
                } else if (!e.target.closest('.plugs-breadcrumbs')) {
                    breadcrumbBar.style.display = 'none';
                }
            });
        }

        function plugsToggleAll(expand) {
            document.querySelectorAll('.var-body, .plugs-var-body').forEach(body => {
                const parent = body.closest('.plugs-tab-content.active, .plugs-debug-content');
                if (parent && body.closest('.plugs-debug-wrapper').style.display !== 'none') {
                    body.style.display = expand ? 'block' : 'none';
                    if (body.previousElementSibling) {
                        body.previousElementSibling.style.opacity = expand ? '1' : '0.7';
                    }
                }
            });
        }
    })();
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
 * Render CSS styles (Plugs Debug - Multi-theme)
 */
function plugs_render_styles(bool $scoped = false, ?string $nonce = null): string
{
    global $plugs_debug_theme;
    $theme = $plugs_debug_theme ?? 'dark';

    $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

    // Core variables (Always included, but maybe scoped if needed. Keeping global for now as variables don't hurt)
    $css = <<<HTML
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style{$nonceAttr}>
    @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Dancing+Script:wght@700&display=swap");

    /* Dark Theme (Default) */
    /* Scoping and Variables */
    .plugs-debug-wrapper, .plugs-safe-scope, #plugs-profiler-modal {
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

    .plugs-safe-scope .plugs-debug-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
    }

    /* Light Theme */
    [data-theme="light"] {
        --bg-body: #f8fafc;
        --bg-card: rgba(255, 255, 255, 0.9);
        --border-color: rgba(148, 163, 184, 0.3);
        --text-primary: #1e293b;
        --text-secondary: #475569;
        --text-muted: #64748b;
        --accent-primary: #7c3aed;
        --accent-secondary: #4f46e5;
        --danger: #dc2626;
        --warning: #d97706;
        --success: #059669;
        --code-bg: #f1f5f9;
        --glass-bg: rgba(255, 255, 255, 0.8);
        --glow: 0 0 20px rgba(124, 58, 237, 0.1);
    }

    /* Dracula Theme */
    [data-theme="dracula"] {
        --bg-body: #282a36;
        --bg-card: rgba(68, 71, 90, 0.7);
        --border-color: rgba(98, 114, 164, 0.5);
        --text-primary: #f8f8f2;
        --text-secondary: #bd93f9;
        --text-muted: #6272a4;
        --accent-primary: #ff79c6;
        --accent-secondary: #8be9fd;
        --danger: #ff5555;
        --warning: #ffb86c;
        --success: #50fa7b;
        --code-bg: #1e1f29;
        --glass-bg: rgba(40, 42, 54, 0.8);
        --glow: 0 0 20px rgba(255, 121, 198, 0.15);
    }

    /* Monokai Theme */
    [data-theme="monokai"] {
        --bg-body: #272822;
        --bg-card: rgba(61, 61, 52, 0.7);
        --border-color: rgba(117, 113, 94, 0.5);
        --text-primary: #f8f8f2;
        --text-secondary: #e6db74;
        --text-muted: #75715e;
        --accent-primary: #f92672;
        --accent-secondary: #66d9ef;
        --danger: #f92672;
        --warning: #e6db74;
        --success: #a6e22e;
        --code-bg: #1e1f1c;
        --glass-bg: rgba(39, 40, 34, 0.8);
        --glow: 0 0 20px rgba(249, 38, 114, 0.15);
    }
HTML
        . "\n    body[data-theme=\"{$theme}\"], body.theme-{$theme} { }";

    // If scoped, override :root and body to be more specific
    if ($scoped) {
        $css = str_replace([':root', 'body {'], ['.plugs-safe-scope', '.plugs-debug-wrapper {'], $css);
    }

    if (!$scoped) {
        $css .= <<<'HTML'

    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
        background: var(--bg-body);
        font-family: 'Outfit', sans-serif;
        color: var(--text-primary);
        line-height: 1.6;
        font-size: 15px;
    }
HTML;
    } else {
        $css .= <<<'HTML'

    /* Scoped Reset */
    .plugs-debug-wrapper, #plugs-profiler-modal, #plugs-profiler-bar {
        font-family: 'Outfit', sans-serif;
        color: var(--text-primary);
        line-height: 1.6;
        font-size: 15px;
    }
    
    .plugs-debug-wrapper *, #plugs-profiler-modal *, #plugs-profiler-bar * {
        box-sizing: border-box;
    }
    
    #plugs-profiler-modal {
        /* Ensure modal text color is correct against dark bg if not inherited */
        color: var(--text-primary);
    }
HTML;
    }

    $css .= <<<'HTML'

    
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
    
    .plugs-header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .plugs-logo-section {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .plugs-brand {
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
    
    .plugs-logo-text p {
        font-size: 14px;
        color: var(--text-muted);
        font-weight: 500;
        margin-top: -8px;
    }

    .plugs-header-controls {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .tabs-nav {
        display: flex;
        gap: 2px;
        background: rgba(15, 23, 42, 0.5);
        padding: 4px;
        border-radius: 12px;
        margin-top: 24px;
        border: 1px solid var(--border-color);
    }

    .tab-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-family: inherit;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-btn:hover {
        color: var(--text-primary);
        background: rgba(255, 255, 255, 0.05);
    }

    .tab-btn.active {
        background: var(--accent-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(168, 85, 247, 0.3);
    }

    .plugs-tab-content {
        display: none !important;
        animation: fadeIn 0.3s ease-out;
    }

    .plugs-tab-content.active {
        display: block !important;
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

    .plugs-global-actions {
        display: flex;
        gap: 8px;
    }

    .plugs-action-btn {
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
    
    .plugs-status-badge {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        padding: 8px 16px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    
    .plugs-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
    }
    
    .plugs-stat-card {
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
    
    .plugs-stat-label {
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
    
    .plugs-stat-value {
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
    
    .plugs-badge {
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
    
    .plugs-code-block {
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
    
    .plugs-syntax-key { color: var(--accent-primary); font-weight: 500; cursor: help; }
    .plugs-syntax-key:hover { text-decoration: underline; }
    .plugs-syntax-string { color: var(--success); }
    .plugs-syntax-number { color: var(--accent-secondary); }
    .plugs-syntax-bool { color: var(--warning); }
    .plugs-syntax-null { color: var(--danger); }
    .plugs-syntax-array { color: var(--text-secondary); opacity: 0.8; }
    .plugs-syntax-object { color: var(--accent-secondary); font-weight: 600; }
    
    .plugs-copy-icon {
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

    .plugs-masked-secret {
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

    .plugs-alert {
        padding: 16px 20px;
        margin-top: 20px;
        border-radius: 12px;
        font-size: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .plugs-alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: #fbbf24; }
    .plugs-alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
    .plugs-alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
    
    .plugs-alert-title {
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
    
    /* Responsive Styles */
    @media (max-width: 1024px) {
        .plugs-debug-wrapper {
            padding: 24px 16px 80px 16px;
        }
        
        .plugs-debug-header {
            padding: 24px;
        }
        
        .plugs-stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
    }
    
    @media (max-width: 768px) {
        .plugs-debug-wrapper {
            padding: 16px 12px 60px 12px;
        }
        
        .plugs-debug-header {
            padding: 16px;
            border-radius: 12px;
        }
        
        .plugs-header-top {
            flex-direction: column;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 16px;
        }
        
        .plugs-header-controls {
            width: 100%;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .search-input {
            width: 100%;
            order: -1;
        }
        
        .plugs-brand {
            font-size: 1.75rem;
        }
        
        .plugs-stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .plugs-stat-card {
            padding: 12px;
        }
        
        .plugs-stat-value {
            font-size: 14px;
        }
        
        .plugs-stat-label {
            font-size: 9px;
            margin-bottom: 8px;
        }
        
        .tabs-nav, .plugs-tabs-nav {
            overflow-x: auto;
            gap: 4px;
            padding: 4px;
            border-radius: 8px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .tabs-nav::-webkit-scrollbar, .plugs-tabs-nav::-webkit-scrollbar {
            display: none;
        }
        
        .tab-btn, .plugs-tab-btn {
            padding: 8px 14px;
            font-size: 11px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .variables-grid {
            padding: 16px;
            gap: 16px;
        }
        
        .var-header {
            padding: 12px 16px;
        }
        
        .var-body {
            padding: 16px;
        }
        
        .plugs-code-block {
            padding: 16px;
            font-size: 12px;
            border-radius: 8px;
        }
        
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
        }
        
        .info-card {
            padding: 10px;
        }
        
        .info-label {
            font-size: 8px;
        }
        
        .info-value {
            font-size: 12px;
        }
        
        .plugs-global-actions {
            flex: 1;
            justify-content: flex-end;
        }
        
        .plugs-action-btn {
            padding: 6px 10px;
            font-size: 11px;
        }
        
        .plugs-action-btn span {
            display: none;
        }
        
        .plugs-status-badge {
            padding: 6px 12px;
            font-size: 10px;
        }
    }
    
    @media (max-width: 480px) {
        .plugs-debug-wrapper {
            padding: 12px 8px 50px 8px;
        }
        
        .plugs-debug-header {
            padding: 12px;
            border-radius: 8px;
        }
        
        .plugs-brand {
            font-size: 1.5rem;
        }
        
        .plugs-stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .plugs-stat-card {
            padding: 10px;
        }
        
        .plugs-stat-value {
            font-size: 12px;
        }
        
        .var-title span:first-child {
            display: none;
        }
        
        .var-badges {
            gap: 4px;
        }
        
        .plugs-badge {
            padding: 2px 8px;
            font-size: 10px;
        }
        
        .plugs-code-block {
            padding: 12px;
            font-size: 11px;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .breadcrumbs {
            bottom: 44px;
            padding: 8px 16px;
            font-size: 11px;
            max-width: calc(100% - 24px);
        }
        
        .plugs-alert {
            padding: 12px;
            font-size: 12px;
        }
        
        .plugs-header-top {
            gap: 12px;
        }
        
        .search-input {
            padding: 6px 12px;
            font-size: 12px;
        }
    }
</style>
HTML;

    return $css;
}

/**
 * Render header section
 */
function plugs_render_header(string $file, $line, int $memoryUsage, int $peakMemory, float $executionTime, array $queryStats): string
{
    $queryCount = $queryStats['count'] ?? 0;
    $queryTime = $queryStats['time'] ?? 0;

    $html = '<div class="plugs-debug-header">';
    $html .= '<div class="plugs-header-top">';
    $html .= '<div class="plugs-logo-section">';
    $html .= '<div class="plugs-brand">Plugs</div>';
    $html .= '<div class="plugs-logo-text"><p>Debug Console</p></div>';
    $html .= '</div>';

    $html .= '<div class="plugs-header-controls">';
    $html .= '<input type="text" class="search-input" id="debug-search" placeholder="Search variables, keys, values...">';
    $html .= '<div class="plugs-global-actions">';
    $html .= '<button class="plugs-action-btn" data-action="expand"><span>Expand</span> ⊞</button>';
    $html .= '<button class="plugs-action-btn" data-action="collapse"><span>Collapse</span> ⊟</button>';
    $html .= '</div>';
    $html .= '<div class="plugs-status-badge">Live Debugging</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="plugs-stats-grid">';
    $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">Location</div><div class="plugs-stat-value" style="font-size: 13px;">' . htmlspecialchars(basename($file)) . ':' . $line . '</div></div>';
    $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">Memory</div><div class="plugs-stat-value">' . plugs_format_bytes($memoryUsage) . '</div></div>';
    $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">Execution</div><div class="plugs-stat-value">' . number_format($executionTime * 1000, 2) . ' ms</div></div>';
    if ($queryCount > 0) {
        $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">Queries</div><div class="plugs-stat-value">' . $queryCount . ' (' . number_format($queryTime * 1000, 1) . 'ms)</div></div>';
    }
    $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">Time</div><div class="plugs-stat-value">' . date('H:i:s') . '</div></div>';
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
    $html .= '<div class="var-header">';
    $html .= '<div class="var-title">';
    $html .= '<span>📦</span>';
    $html .= '<span>Variable #' . ($index + 1) . '</span>';
    $html .= '</div>';
    $html .= '<div class="var-badges">';
    $html .= '<span class="plugs-badge">' . $type . '</span>';
    $html .= '<span class="plugs-badge">' . plugs_format_bytes($size) . '</span>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="var-body">';
    $html .= '<div class="section-title">📄 Value</div>';
    $html .= '<div class="plugs-code-block">';
    $html .= '<div class="plugs-copy-icon" title="Copy to clipboard">📋</div>';
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
        $html .= '<div class="plugs-alert plugs-alert-danger">';
        $html .= '<div class="plugs-alert-title">❌ Critical Memory Warning</div>';
        $html .= 'Variable size is extremely large (' . plugs_format_bytes($size) . '). Consider pagination or chunking.';
        $html .= '</div>';
    } elseif ($size > 1 * 1024 * 1024) {
        $html .= '<div class="plugs-alert plugs-alert-warning">';
        $html .= '<div class="plugs-alert-title">⚠️ Large Variable</div>';
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
    $html .= '<div class="plugs-stats-grid" style="margin-bottom: 32px;">';

    $html .= '<div class="plugs-stat-card" style="background: linear-gradient(145deg, rgba(168, 85, 247, 0.1), transparent);">';
    $html .= '<div class="plugs-stat-label">✨ Total Queries</div>';
    $html .= '<div class="plugs-stat-value">' . ($stats['total_queries'] ?? 0) . '</div>';
    $html .= '</div>';

    $html .= '<div class="plugs-stat-card" style="background: linear-gradient(145deg, rgba(99, 102, 241, 0.1), transparent);">';
    $html .= '<div class="plugs-stat-label">⏱️ Total Time</div>';
    $html .= '<div class="plugs-stat-value">' . number_format(($stats['total_time'] ?? 0) * 1000, 2) . ' ms</div>';
    $html .= '</div>';

    $html .= '<div class="plugs-stat-card">';
    $html .= '<div class="plugs-stat-label">🧠 Memory Peak</div>';
    $html .= '<div class="plugs-stat-value">' . plugs_format_bytes($stats['peak_memory'] ?? memory_get_peak_usage(true)) . '</div>';
    $html .= '</div>';

    $html .= '</div>';

    // Performance assessment
    $totalQueries = $stats['total_queries'] ?? 0;
    if ($totalQueries > 20) {
        $html .= '<div class="plugs-alert plugs-alert-danger" style="animation: pulse 2s infinite;">';
        $html .= '<div class="plugs-alert-title">🔥 Critical Warning</div>';
        $html .= "High query volume ({$totalQueries}). This will significantly slow down production response times.";
        $html .= '</div>';
    } elseif ($totalQueries > 10) {
        $html .= '<div class="plugs-alert plugs-alert-warning">';
        $html .= '<div class="plugs-alert-title">⚡ Optimization Recommended</div>';
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
        $html .= '<div class="var-header">';
        $html .= '<div class="var-title"><span>#' . ($index + 1) . '</span> <code style="color: var(--accent-secondary);">' . substr(htmlspecialchars($query['query']), 0, 60) . (strlen($query['query']) > 60 ? '...' : '') . '</code></div>';
        $html .= '<div class="var-badges">';
        if ($isSlow) {
            $html .= '<span class="plugs-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">SLOW</span>';
        }
        $html .= '<span class="plugs-badge">' . $ms . ' ms</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="var-body" style="display: none;">';
        $html .= '<div class="plugs-code-block">';
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
        $html .= '<div class="plugs-code-block">';
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
        $html .= '<div class="plugs-alert plugs-alert-info">';
        $html .= '<div class="plugs-alert-title">ℹ️ Query Count</div>';
        $html .= 'This model executed ' . count($queries) . ' database queries.';
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render exception dump
 */
function plugs_render_exception(array $data): string
{
    $exception = $data['exception'] ?? null;
    $class = $data['class'] ?? 'Exception';
    $message = $data['message'] ?? 'Unknown error';
    $code = $data['code'] ?? 0;
    $file = $data['file'] ?? 'unknown';
    $line = $data['line'] ?? 0;
    $trace = $data['trace'] ?? [];
    $previous = $data['previous'] ?? null;

    $html = '<div style="padding: 32px;">';

    // Exception header
    $html .= '<div class="plugs-alert plugs-alert-danger" style="margin-bottom: 24px;">';
    $html .= '<div class="plugs-alert-title">💥 ' . htmlspecialchars($class) . '</div>';
    $html .= '<div style="font-size: 18px; margin-top: 8px;">' . htmlspecialchars($message) . '</div>';
    if ($code) {
        $html .= '<div style="margin-top: 8px; opacity: 0.8;">Code: ' . $code . '</div>';
    }
    $html .= '</div>';

    // Location
    $html .= '<div class="stats-grid" style="margin-bottom: 24px;">';
    $html .= '<div class="stat-card"><div class="stat-label">📍 File</div><div class="stat-value" style="font-size: 13px;">' . htmlspecialchars(basename($file)) . '</div></div>';
    $html .= '<div class="stat-card"><div class="stat-label">📍 Line</div><div class="stat-value">' . $line . '</div></div>';
    $html .= '<div class="stat-card"><div class="stat-label">📍 Full Path</div><div class="stat-value" style="font-size: 11px; word-break: break-all;">' . htmlspecialchars($file) . '</div></div>';
    $html .= '</div>';

    // Code snippet
    if (file_exists($file)) {
        $html .= '<div class="section-title">📄 Code Context</div>';
        $html .= '<div class="code-block" style="margin-bottom: 24px;">';
        $lines = file($file);
        $start = max(0, $line - 6);
        $end = min(count($lines), $line + 5);
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $isErrorLine = $lineNum === $line;
            $lineContent = htmlspecialchars($lines[$i] ?? '');
            $style = $isErrorLine ? 'background: rgba(239, 68, 68, 0.2); display: block; padding: 2px 8px; margin: 0 -8px; border-radius: 4px;' : '';
            $html .= '<span style="color: var(--text-muted); margin-right: 16px;">' . str_pad((string) $lineNum, 4, ' ', STR_PAD_LEFT) . '</span>';
            $html .= '<span style="' . $style . '">' . $lineContent . '</span>';
        }
        $html .= '</div>';
    }

    // Stack trace
    if (!empty($trace)) {
        $html .= '<div class="section-title">📚 Stack Trace</div>';
        foreach ($trace as $index => $frame) {
            $frameFile = $frame['file'] ?? 'unknown';
            $frameLine = $frame['line'] ?? 0;
            $frameClass = $frame['class'] ?? '';
            $frameType = $frame['type'] ?? '';
            $frameFunction = $frame['function'] ?? '';
            $call = $frameClass . $frameType . $frameFunction . '()';

            $html .= '<div class="var-item" style="margin-bottom: 8px;">';
            $html .= '<div class="var-header">';
            $html .= '<div class="var-title"><span>#' . $index . '</span> <code style="color: var(--accent-secondary);">' . htmlspecialchars($call) . '</code></div>';
            $html .= '<div class="var-badges"><span class="plugs-badge">' . htmlspecialchars(basename($frameFile)) . ':' . $frameLine . '</span></div>';
            $html .= '</div>';
            $html .= '<div class="var-body" style="display: none; padding: 16px; font-size: 12px; color: var(--text-muted);">';
            $html .= htmlspecialchars($frameFile);
            $html .= '</div>';
            $html .= '</div>';
        }
    }

    // Previous exception
    if ($previous) {
        $html .= '<div style="margin-top: 24px;">';
        $html .= '<div class="section-title">🔗 Previous Exception</div>';
        $html .= '<div class="plugs-alert plugs-alert-warning">';
        $html .= '<div class="plugs-alert-title">' . get_class($previous) . '</div>';
        $html .= htmlspecialchars($previous->getMessage());
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render HTTP response dump
 */
function plugs_render_http(array $data): string
{
    $html = '<div style="padding: 32px;">';
    $html .= '<div class="section-title" style="margin-bottom: 24px; font-size: 18px; color: var(--text-primary);">🌐 HTTP Response</div>';

    // Status
    $statusCode = $data['status_code'] ?? 0;
    $statusClass = $statusCode >= 200 && $statusCode < 300 ? 'plugs-alert-success' : ($statusCode >= 400 ? 'plugs-alert-danger' : 'plugs-alert-warning');

    $html .= '<div class="plugs-alert ' . $statusClass . '" style="margin-bottom: 24px;">';
    $html .= '<div class="plugs-alert-title">Status: ' . $statusCode . ' ' . ($data['reason'] ?? '') . '</div>';
    if (isset($data['url'])) {
        $html .= '<div style="margin-top: 8px;">' . htmlspecialchars($data['url']) . '</div>';
    }
    $html .= '</div>';

    // Stats
    $html .= '<div class="stats-grid" style="margin-bottom: 24px;">';
    if (isset($data['status_code'])) {
        $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">Status</div><div class="plugs-stat-value">' . $data['status_code'] . '</div></div>';
    }
    if (isset($data['request_time'])) {
        $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">⏱️ Time</div><div class="plugs-stat-value">' . number_format($data['request_time'] * 1000, 2) . ' ms</div></div>';
    }
    if (isset($data['response_class'])) {
        $html .= '<div class="plugs-stat-card"><div class="plugs-stat-label">Class</div><div class="plugs-stat-value" style="font-size: 12px;">' . $data['response_class'] . '</div></div>';
    }
    $html .= '</div>';

    // Headers
    if (isset($data['headers']) && !empty($data['headers'])) {
        $html .= '<div class="section-title">📋 Headers</div>';
        $html .= '<div class="code-block" style="margin-bottom: 24px;">';
        foreach ($data['headers'] as $name => $value) {
            $val = is_array($value) ? implode(', ', $value) : $value;
            $html .= '<span class="syntax-key">' . htmlspecialchars($name) . '</span>: <span class="syntax-string">' . htmlspecialchars($val) . '</span><br>';
        }
        $html .= '</div>';
    }

    // Body
    if (isset($data['body_parsed'])) {
        $html .= '<div class="section-title">📄 Body (JSON)</div>';
        $html .= '<div class="plugs-code-block">';
        $html .= plugs_format_value($data['body_parsed'], 0);
        $html .= '</div>';
    } elseif (isset($data['body'])) {
        $html .= '<div class="section-title">📄 Body</div>';
        $html .= '<div class="plugs-code-block">';
        $body = $data['body'];
        if (is_string($body) && strlen($body) > 2000) {
            $html .= htmlspecialchars(substr($body, 0, 2000)) . '... (truncated)';
        } else {
            $html .= htmlspecialchars(is_string($body) ? $body : print_r($body, true));
        }
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render profile dump
 */
function plugs_render_profile(array $data): string
{
    $html = '<div style="padding: 32px;">';
    $html .= '<div class="section-title" style="margin-bottom: 24px; font-size: 18px; color: var(--text-primary);">⚡ Performance Profile</div>';

    // Stats cards
    $html .= '<div class="plugs-stats-grid" style="margin-bottom: 32px;">';

    $html .= '<div class="plugs-stat-card" style="background: linear-gradient(145deg, rgba(16, 185, 129, 0.1), transparent);">';
    $html .= '<div class="plugs-stat-label">⏱️ Execution Time</div>';
    $html .= '<div class="plugs-stat-value">' . number_format(($data['execution_time_ms'] ?? ($data['execution_time'] ?? 0) * 1000), 2) . ' ms</div>';
    $html .= '</div>';

    $html .= '<div class="plugs-stat-card" style="background: linear-gradient(145deg, rgba(99, 102, 241, 0.1), transparent);">';
    $html .= '<div class="plugs-stat-label">🧠 Memory Used</div>';
    $html .= '<div class="plugs-stat-value">' . ($data['memory_formatted'] ?? plugs_format_bytes($data['memory_used'] ?? 0)) . '</div>';
    $html .= '</div>';

    $html .= '<div class="plugs-stat-card" style="background: linear-gradient(145deg, rgba(168, 85, 247, 0.1), transparent);">';
    $html .= '<div class="plugs-stat-label">🔍 Query Count</div>';
    $html .= '<div class="plugs-stat-value">' . ($data['query_count'] ?? 0) . '</div>';
    $html .= '</div>';

    $html .= '<div class="plugs-stat-card">';
    $html .= '<div class="plugs-stat-label">⚙️ Query Time</div>';
    $html .= '<div class="plugs-stat-value">' . number_format(($data['query_time_ms'] ?? ($data['query_time'] ?? 0) * 1000), 2) . ' ms</div>';
    $html .= '</div>';

    $html .= '</div>';

    // Performance assessment
    $execTime = $data['execution_time_ms'] ?? ($data['execution_time'] ?? 0) * 1000;
    $queryCount = $data['query_count'] ?? 0;

    if ($execTime > 1000 || $queryCount > 20) {
        $html .= '<div class="plugs-alert plugs-alert-danger" style="margin-bottom: 24px;">';
        $html .= '<div class="plugs-alert-title">🔥 Performance Warning</div>';
        if ($execTime > 1000) {
            $html .= 'Execution time exceeds 1 second. Consider optimizing your code.<br>';
        }
        if ($queryCount > 20) {
            $html .= 'High query count (' . $queryCount . '). Use eager loading with with() to reduce queries.';
        }
        $html .= '</div>';
    } elseif ($execTime > 500 || $queryCount > 10) {
        $html .= '<div class="plugs-alert plugs-alert-warning" style="margin-bottom: 24px;">';
        $html .= '<div class="plugs-alert-title">⚡ Optimization Recommended</div>';
        $html .= 'Consider reviewing performance. ';
        if ($queryCount > 10) {
            $html .= 'Multiple queries detected.';
        }
        $html .= '</div>';
    } else {
        $html .= '<div class="plugs-alert plugs-alert-success" style="margin-bottom: 24px;">';
        $html .= '<div class="plugs-alert-title">✅ Performance Good</div>';
        $html .= 'Execution time and query count are within acceptable limits.';
        $html .= '</div>';
    }

    // Queries
    $queries = $data['queries'] ?? [];
    if (!empty($queries)) {
        $html .= '<div class="section-title">🔮 Queries Executed</div>';
        foreach ($queries as $index => $query) {
            $time = $query['time'] ?? 0;
            $ms = number_format($time * 1000, 2);
            $isSlow = $time > 0.05;

            $html .= '<div class="var-item" style="margin-bottom: 8px;">';
            $html .= '<div class="var-header">';
            $html .= '<div class="var-title"><span>#' . ($index + 1) . '</span> <code style="color: var(--accent-secondary);">' . substr(htmlspecialchars($query['query'] ?? ''), 0, 60) . (strlen($query['query'] ?? '') > 60 ? '...' : '') . '</code></div>';
            $html .= '<div class="var-badges">';
            if ($isSlow) {
                $html .= '<span class="plugs-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">SLOW</span>';
            }
            $html .= '<span class="plugs-badge">' . $ms . ' ms</span>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="var-body" style="display: none;">';
            $html .= '<div class="code-block"><code class="syntax-string">' . htmlspecialchars($query['query'] ?? '') . '</code></div>';
            if (!empty($query['bindings'])) {
                $html .= '<div style="margin-top: 12px; font-size: 13px; color: var(--text-muted);">Bindings: <code>' . json_encode($query['bindings']) . '</code></div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
    }

    // Result
    if (isset($data['result'])) {
        $html .= '<div style="margin-top: 24px;">';
        $html .= '<div class="section-title">📦 Result</div>';
        $html .= '<div class="plugs-code-block">';
        $html .= plugs_format_value($data['result'], 0);
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
        return '<span class="plugs-syntax-null">... (depth limit)</span>';
    }
    $indent = str_repeat('  ', $depth);

    if (is_null($value)) {
        return '<span class="plugs-syntax-null">null</span>';
    }
    if (is_bool($value)) {
        return '<span class="plugs-syntax-bool">' . ($value ? 'true' : 'false') . '</span>';
    }
    if (is_int($value) || is_float($value)) {
        return '<span class="plugs-syntax-number">' . $value . '</span>';
    }
    if (is_string($value)) {
        $escaped = htmlspecialchars($value);
        $isTruncated = strlen($value) > 200;
        $truncatedAttr = $isTruncated ? ' data-truncated="true" style="cursor:pointer" title="Click to expand"' : '';

        return '<span class="plugs-syntax-string" data-full-value="' . $escaped . '"' . $truncatedAttr . '>"' . ($isTruncated ? substr($escaped, 0, 200) . '...' : $escaped) . '"</span>';
    }

    if (is_array($value)) {
        if (empty($value)) {
            return '<span class="plugs-syntax-array">[]</span>';
        }
        $html = '<span class="plugs-syntax-array">Array(' . count($value) . ')</span> [<br>';
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        foreach ($value as $key => $val) {
            $currentPath = array_merge($path, [$key]);
            $isSecret = is_string($key) && preg_match('/(password|secret|key|token|auth|pass|cred)/i', $key);

            $html .= $indent . '  ';
            if ($isAssoc) {
                $html .= '<span class="plugs-syntax-key" data-path="' . implode(' → ', $currentPath) . '">' . (is_string($key) ? '"' . htmlspecialchars($key) . '"' : $key) . '</span> => ';
            }

            if ($isSecret && !empty($val)) {
                $html .= '<span class="plugs-masked-secret" data-secret="' . htmlspecialchars(is_string($val) ? (string) $val : json_encode($val)) . '">🔒 [masked secret]</span>';
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
        $html = '<span class="plugs-syntax-object">Object(' . $className . ')</span> {<br>';

        try {
            // Check if object has __debugInfo() method - use it for cleaner output
            if (method_exists($value, '__debugInfo')) {
                $debugData = $value->__debugInfo();
                foreach ($debugData as $name => $val) {
                    $currentPath = array_merge($path, [$name]);
                    $isSecret = is_string($name) && preg_match('/(password|secret|key|token|auth|pass|cred)/i', $name);

                    $html .= $indent . '  <span class="plugs-syntax-key" data-path="' . implode(' → ', $currentPath) . '">' . htmlspecialchars($name) . '</span> => ';

                    if ($isSecret && !empty($val)) {
                        $html .= '<span class="plugs-masked-secret" data-secret="' . htmlspecialchars(is_string($val) ? (string) $val : json_encode($val)) . '">🔒 [masked secret]</span>';
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
                    $html .= $indent . '  <span class="plugs-syntax-key" data-path="' . implode(' → ', $currentPath) . '">' . htmlspecialchars($name) . '</span> => ' . plugs_format_value($val, $depth + 1, $currentPath) . '<br>';
                }
            }
        } catch (\Exception $e) {
            $html .= $indent . '  <span class="plugs-syntax-null">Unable to reflect</span><br>';
        }
        $html .= $indent . '}';

        return $html;
    }

    return '<span class="plugs-syntax-null">' . htmlspecialchars(print_r($value, true)) . '</span>';
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
