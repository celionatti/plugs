<?php

declare(strict_types=1);

namespace Plugs\Debug;

/**
 * Plugs Framework Debug Dumper - PSR-4 Refactored
 * 
 * Handles beautiful UI rendering for all Plugs debug utilities.
 */
class Dumper
{
    /**
     * @var string Global theme setting
     */
    protected static string $theme = 'dark';

    /**
     * Set the debug theme.
     * 
     * @param string $theme
     * @return void
     */
    public static function setTheme(string $theme): void
    {
        $validThemes = ['dark', 'light', 'dracula', 'monokai'];
        if (in_array($theme, $validThemes)) {
            static::$theme = $theme;
        }
    }

    /**
     * Get the current theme.
     * 
     * @return string
     */
    public static function getTheme(): string
    {
        return static::$theme;
    }

    /**
     * Core dump method.
     * 
     * @param array $vars Variables to dump
     * @param bool $die Whether to terminate execution
     * @param string $mode Rendering mode (default, query, model, exception, http, profile)
     * @param string|null $nonce CSP nonce
     * @return void
     */
    public function dump(array $vars, bool $die = false, string $mode = 'default', ?string $nonce = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        // Adjust caller detection because of the extra layer in the Dumper class
        $caller = $backtrace[2] ?? $backtrace[1] ?? $backtrace[0] ?? [];
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 'unknown';

        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $executionTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $queryStats = $this->getQueryStats();

        $theme = static::$theme;
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        echo $this->renderStyles(!$die, $nonce);

        $scopeClass = $die ? '' : 'plugs-safe-scope';
        echo '<div class="plugs-debug-wrapper ' . $scopeClass . '" data-theme="' . $theme . '">';

        if ($die) {
            echo '<script' . $nonceAttr . '>document.body.setAttribute("data-theme", "' . $theme . '");</script>';
        }

        echo $this->renderHeader($file, $line, $memoryUsage, $peakMemory, $executionTime, $queryStats);
        echo '<div class="plugs-debug-content">';

        switch ($mode) {
            case 'query':
                echo $this->renderQueries($vars[0] ?? []);
                break;
            case 'model':
                echo $this->renderModel($vars[0] ?? []);
                break;
            case 'exception':
                echo $this->renderException($vars[0] ?? []);
                break;
            case 'http':
                echo $this->renderHttp($vars[0] ?? []);
                break;
            case 'profile':
                echo $this->renderProfile($vars[0] ?? []);
                break;
            default:
                echo $this->renderVariables($vars);
                break;
        }

        echo '</div>';
        echo '</div>';
        echo $this->renderScripts($nonce);

        if ($die) {
            exit(1);
        }
    }

    /**
     * Render the fallback error page.
     * 
     * @param string $message
     * @param bool $die
     * @return void
     */
    public function renderFallbackError(string $message, bool $die = true): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $this->renderStyles($die);
        $theme = static::$theme;

        echo '<div class="plugs-debug-wrapper" data-theme="' . $theme . '">';
        echo '<div class="plugs-dbg-alert plugs-dbg-alert-danger" style="margin: 20px;">';
        echo '<div class="plugs-dbg-alert-title">❌ Error</div>';
        echo '<div>' . htmlspecialchars($message) . '</div>';
        echo '</div>';
        echo '</div>';

        if ($die) {
            exit(1);
        }
    }

    /**
     * Get query statistics.
     * 
     * @return array
     */
    protected function getQueryStats(): array
    {
        $modelClass = 'Plugs\\Base\\Model\\PlugModel';

        if (!class_exists($modelClass)) {
            return [];
        }

        try {
            /** @var mixed $modelClass */
            $queries = $modelClass::getQueryLog();
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
     * Render CSS styles.
     * 
     * @param bool $scoped
     * @param string|null $nonce
     * @return string
     */
    public function renderStyles(bool $scoped = false, ?string $nonce = null): string
    {
        $theme = static::$theme;
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        $css = <<<HTML
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style{$nonceAttr}>
    @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Dancing+Script:wght@700&display=swap");

    .plugs-debug-wrapper, .plugs-safe-scope, #plugs-profiler-modal {
        --bg-body: #18171B;
        --bg-card: #222222;
        --border-color: #444444;
        --text-primary: #FFFFFF;
        --text-secondary: #999999;
        --text-muted: #707070;
        --accent-primary: #FF8400;
        --accent-secondary: #1299DA;
        --danger: #FF5555;
        --warning: #FFB86C;
        --success: #56DB3A;
        --code-bg: #1e1e1e;
        --glass-bg: rgba(24, 23, 27, 0.95);
        --glow: 0 0 15px rgba(255, 132, 0, 0.1);
    }

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

    .plugs-debug-wrapper {
        min-height: 100vh; padding: 40px 20px 100px 20px; max-width: 1200px; margin: 0 auto;
        background-color: var(--bg-body); color: var(--text-primary); font-family: 'Outfit', sans-serif;
    }
    .plugs-dbg-header {
        background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border-color);
        border-radius: 20px; padding: 32px; margin-bottom: 24px; box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.5), var(--glow); position: relative; overflow: hidden;
    }
    .plugs-dbg-header::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
        background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
    }
    .plugs-dbg-header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); }
    .plugs-dbg-logo-section { display: flex; align-items: center; gap: 16px; }
    .plugs-dbg-brand { font-family: "Dancing Script", cursive; font-size: 2.25rem; font-weight: 700; background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .plugs-logo-text p { font-size: 14px; color: var(--text-muted); font-weight: 500; margin-top: -8px; }
    .plugs-dbg-header-controls { display: flex; align-items: center; gap: 16px; }
    .plugs-dbg-search-input { background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border-color); border-radius: 8px; padding: 8px 16px; color: var(--text-primary); font-family: inherit; font-size: 13px; width: 250px; outline: none; }
    .plugs-dbg-global-actions { display: flex; gap: 8px; }
    .plugs-dbg-action-btn { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
    .plugs-dbg-status-badge { background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 8px 16px; border-radius: 99px; font-size: 12px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.2); }
    .plugs-dbg-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
    .plugs-dbg-stat-card { background: rgba(15, 23, 42, 0.3); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
    .plugs-dbg-stat-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; letter-spacing: 0.05em; display: flex; align-items: center; gap: 8px; }
    .plugs-dbg-stat-value { font-size: 18px; font-weight: 600; color: var(--text-primary); font-family: 'JetBrains Mono', monospace; }
    .plugs-dbg-content { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); }
    .plugs-dbg-variables-grid { display: grid; gap: 24px; padding: 32px; }
    .plugs-dbg-var-item { background: rgba(15, 23, 42, 0.2); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; }
    .plugs-dbg-var-header { padding: 16px 24px; background: rgba(255, 255, 255, 0.03); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
    .plugs-dbg-var-title { font-size: 15px; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }
    .plugs-dbg-badge { background: rgba(139, 92, 246, 0.1); color: var(--accent-primary); padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; border: 1px solid rgba(139, 92, 246, 0.2); }
    .plugs-dbg-var-body { padding: 24px; }
    .plugs-dbg-section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--accent-secondary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; letter-spacing: 0.1em; }
    .plugs-dbg-code-block { background: var(--code-bg); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 12px; padding: 24px; overflow-x: auto; font-family: 'JetBrains Mono', monospace; font-size: 14px; line-height: 1.7; position: relative; }
    .plugs-dbg-syntax-key { color: var(--accent-primary); font-weight: 500; cursor: help; }
    .plugs-dbg-syntax-string { color: var(--success); }
    .plugs-dbg-syntax-number { color: var(--accent-secondary); }
    .plugs-dbg-syntax-bool { color: var(--warning); }
    .plugs-dbg-syntax-null { color: var(--danger); }
    .plugs-dbg-syntax-array { color: var(--text-secondary); opacity: 0.8; }
    .plugs-dbg-syntax-object { color: var(--accent-secondary); font-weight: 600; }
    .plugs-dbg-copy-icon { position: absolute; right: 12px; top: 12px; color: var(--text-muted); cursor: pointer; opacity: 0; transition: all 0.2s; padding: 4px; border-radius: 4px; }
    .plugs-dbg-code-block:hover .plugs-dbg-copy-icon { opacity: 1; }
    .plugs-dbg-masked-secret { background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 2px 6px; border-radius: 4px; cursor: pointer; font-style: italic; }
    .plugs-dbg-breadcrumbs { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: rgba(30, 41, 59, 0.9); backdrop-filter: blur(12px); border: 1px solid var(--border-color); padding: 10px 24px; border-radius: 99px; font-size: 13px; color: var(--text-secondary); z-index: 1000; display: none; }
    .plugs-dbg-alert { padding: 16px 20px; margin-top: 20px; border-radius: 12px; font-size: 14px; display: flex; flex-direction: column; gap: 8px; }
    .plugs-dbg-alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: #fbbf24; }
    .plugs-dbg-alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
    .plugs-dbg-alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
    .plugs-dbg-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color); }
    .plugs-dbg-info-card { background: rgba(15, 23, 42, 0.2); border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; }
    .plugs-dbg-info-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
    .plugs-dbg-info-value { font-size: 14px; font-weight: 500; color: var(--text-secondary); font-family: 'JetBrains Mono', monospace; }

    /* Tabs (used by ProfilerBar modal) */
    .plugs-dbg-tabs-nav {
        display: flex; gap: 4px; padding: 16px 32px; background: rgba(15, 23, 42, 0.4);
        border-bottom: 1px solid var(--border-color); overflow-x: auto; scrollbar-width: none;
        -ms-overflow-style: none; flex-wrap: wrap;
    }
    .plugs-dbg-tabs-nav::-webkit-scrollbar { display: none; }
    .plugs-dbg-tab-btn {
        background: transparent; border: 1px solid transparent; color: var(--text-muted);
        padding: 10px 16px; border-radius: 8px; cursor: pointer; font-size: 12px;
        font-weight: 600; font-family: inherit; white-space: nowrap; transition: all 0.2s;
        display: flex; align-items: center; gap: 6px;
    }
    .plugs-dbg-tab-btn:hover { color: var(--text-secondary); background: rgba(255, 255, 255, 0.05); }
    .plugs-dbg-tab-btn.active {
        color: var(--accent-primary); background: rgba(139, 92, 246, 0.1);
        border-color: rgba(139, 92, 246, 0.3); box-shadow: 0 0 12px rgba(139, 92, 246, 0.1);
    }
    .plugs-dbg-tab-content { display: none; }
    .plugs-dbg-tab-content.active { display: block; }

    /* Action / utility buttons */
    .plugs-action-btn {
        background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color);
        color: var(--text-secondary); padding: 8px 16px; border-radius: 8px; cursor: pointer;
        font-size: 12px; font-weight: 600; font-family: inherit; transition: all 0.2s;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .plugs-action-btn:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }

    /* Logo section layout for profiler header */
    .plugs-logo-section { display: flex; align-items: center; gap: 16px; }
</style>
HTML;
        return $css;
    }

    /**
     * Render JS scripts.
     * 
     * @param string|null $nonce
     * @return string
     */
    protected function renderScripts(?string $nonce = null): string
    {
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        return <<<JS
<script{$nonceAttr}>
    (function() {
        document.addEventListener('click', function(e) {
            const varHeader = e.target.closest('.plugs-dbg-var-header');
            if (varHeader) {
                const body = varHeader.nextElementSibling;
                if (body) {
                    const isCollapsed = body.style.display === 'none';
                    body.style.display = isCollapsed ? 'block' : 'none';
                    varHeader.style.opacity = isCollapsed ? '1' : '0.7';
                }
                return;
            }

            const copyIcon = e.target.closest('.plugs-dbg-copy-icon');
            if (copyIcon) {
                const container = copyIcon.parentElement;
                const stringSpan = container.querySelector('.plugs-dbg-syntax-string');
                const textToCopy = stringSpan ? (stringSpan.getAttribute('data-full-value') || stringSpan.innerText) : container.innerText.replace('📋', '').trim();
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const originalText = copyIcon.innerText;
                    copyIcon.innerText = '✅';
                    setTimeout(() => copyIcon.innerText = originalText, 1500);
                });
                return;
            }

            const secret = e.target.closest('.plugs-dbg-masked-secret');
            if (secret) {
                const val = secret.getAttribute('data-secret');
                if (val) {
                    secret.innerText = val;
                    secret.classList.remove('plugs-dbg-masked-secret');
                    secret.style.color = '#10b981';
                }
                return;
            }
            
            const expandBtn = e.target.closest('.plugs-dbg-action-btn[data-action="expand"]');
            if (expandBtn) { plugsToggleAll(true); return; }
            
            const collapseBtn = e.target.closest('.plugs-dbg-action-btn[data-action="collapse"]');
            if (collapseBtn) { plugsToggleAll(false); return; }
        });

        const debugSearch = document.getElementById('debug-search');
        if (debugSearch) {
            debugSearch.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase();
                document.querySelectorAll('.plugs-dbg-var-item').forEach(item => {
                    const text = item.innerText.toLowerCase();
                    item.style.display = text.includes(query) ? 'block' : 'none';
                });
            });
        }

        function plugsToggleAll(expand) {
            document.querySelectorAll('.plugs-dbg-var-body').forEach(body => {
                body.style.display = expand ? 'block' : 'none';
                if (body.previousElementSibling) body.previousElementSibling.style.opacity = expand ? '1' : '0.7';
            });
        }
    })();
</script>
JS;
    }

    /**
     * Render header section.
     */
    protected function renderHeader(string $file, $line, int $memoryUsage, int $peakMemory, float $executionTime, array $queryStats): string
    {
        $queryCount = $queryStats['count'] ?? 0;
        $queryTime = $queryStats['time'] ?? 0;

        $html = '<div class="plugs-dbg-header">';
        $html .= '<div class="plugs-dbg-header-top">';
        $html .= '<div class="plugs-dbg-logo-section">';
        $html .= '<div class="plugs-dbg-brand">Plugs</div>';
        $html .= '<div class="plugs-dbg-logo-text"><p>Debug Console</p></div>';
        $html .= '</div>';
        $html .= '<div class="plugs-dbg-header-controls">';
        $html .= '<input type="text" class="plugs-dbg-search-input" id="debug-search" placeholder="Search variables...">';
        $html .= '<div class="plugs-dbg-global-actions">';
        $html .= '<button class="plugs-dbg-action-btn" data-action="expand"><span>Expand</span> ⊞</button>';
        $html .= '<button class="plugs-dbg-action-btn" data-action="collapse"><span>Collapse</span> ⊟</button>';
        $html .= '</div>';
        $html .= '<div class="plugs-dbg-status-badge">Live Debugging</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="plugs-dbg-stats-grid">';
        $html .= '<div class="plugs-dbg-stat-card"><div class="plugs-dbg-stat-label">Location</div><div class="plugs-dbg-stat-value" style="font-size: 13px;">' . htmlspecialchars(basename($file)) . ':' . $line . '</div></div>';
        $html .= '<div class="plugs-dbg-stat-card"><div class="plugs-dbg-stat-label">Memory</div><div class="plugs-dbg-stat-value" style="color: var(--accent-primary);">' . $this->formatBytes($memoryUsage) . '</div></div>';
        $html .= '<div class="plugs-dbg-stat-card"><div class="plugs-dbg-stat-label">Execution</div><div class="plugs-dbg-stat-value" style="color: var(--success);">' . number_format($executionTime * 1000, 2) . ' ms</div></div>';
        if ($queryCount > 0) {
            $html .= '<div class="plugs-dbg-stat-card"><div class="plugs-dbg-stat-label">Queries</div><div class="plugs-dbg-stat-value" style="color: var(--danger);">' . $queryCount . ' (' . number_format($queryTime * 1000, 1) . 'ms)</div></div>';
        }
        $html .= '<div class="plugs-dbg-stat-card"><div class="plugs-dbg-stat-label">Time</div><div class="plugs-dbg-stat-value">' . date('H:i:s') . '</div></div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render variables list.
     */
    protected function renderVariables(array $vars): string
    {
        $html = '<div class="plugs-dbg-variables-grid">';
        foreach ($vars as $index => $var) {
            $html .= $this->renderVariable($var, $index);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render single variable.
     */
    protected function renderVariable($var, int $index): string
    {
        $type = gettype($var);
        $size = $this->getVariableSize($var);

        $html = '<div class="plugs-dbg-var-item">';
        $html .= '<div class="plugs-dbg-var-header">';
        $html .= '<div class="plugs-dbg-var-title"><span>📦</span><span>Variable #' . ($index + 1) . '</span></div>';
        $html .= '<div class="plugs-dbg-var-badges"><span class="plugs-dbg-badge">' . $type . '</span><span class="plugs-dbg-badge">' . $this->formatBytes($size) . '</span></div>';
        $html .= '</div>';
        $html .= '<div class="plugs-dbg-var-body">';
        $html .= '<div class="plugs-dbg-section-title">📄 Value</div>';
        $html .= '<div class="plugs-dbg-code-block"><div class="plugs-dbg-copy-icon">📋</div>';
        $html .= $this->formatValue($var, 0);
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Render queries section.
     */
    public function renderQueries(array $data): string
    {
        $queries = $data['queries'] ?? [];
        $stats = $data['stats'] ?? [];

        $html = '<div style="padding: 32px;">';
        $html .= '<div class="plugs-dbg-section-title" style="margin-bottom: 24px; font-size: 18px; color: var(--text-primary);">📊 Query Insights</div>';

        $html .= '<div class="plugs-dbg-stats-grid" style="margin-bottom: 32px;">';
        $html .= '<div class="plugs-dbg-stat-card"> <div class="plugs-dbg-stat-label">✨ Total Queries</div> <div class="plugs-dbg-stat-value">' . ($stats['total_queries'] ?? 0) . '</div> </div>';
        $html .= '<div class="plugs-dbg-stat-card"> <div class="plugs-dbg-stat-label">⏱️ Total Time</div> <div class="plugs-dbg-stat-value">' . number_format(($stats['total_time'] ?? 0) * 1000, 2) . ' ms</div> </div>';
        $html .= '<div class="plugs-dbg-stat-card"> <div class="plugs-dbg-stat-label">🧠 Memory Peak</div> <div class="plugs-dbg-stat-value">' . $this->formatBytes($stats['peak_memory'] ?? memory_get_peak_usage(true)) . '</div> </div>';
        $html .= '</div>';

        foreach ($queries as $index => $query) {
            $html .= '<div class="plugs-dbg-var-item" style="margin-bottom: 16px;">';
            $html .= '<div class="plugs-dbg-var-header"> <div class="plugs-dbg-var-title"><span>#' . ($index + 1) . '</span> <code style="color: var(--accent-secondary);">' . substr(htmlspecialchars($query['query']), 0, 80) . '...</code></div> <div class="plugs-dbg-var-badges"><span class="plugs-dbg-badge">' . number_format(($query['time'] ?? 0) * 1000, 2) . ' ms</span></div> </div>';
            $html .= '<div class="plugs-dbg-var-body" style="display: none;"> <div class="plugs-dbg-code-block"><code class="plugs-dbg-syntax-string">' . htmlspecialchars($query['query']) . '</code></div> </div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render model section.
     */
    protected function renderModel(array $data): string
    {
        $model = $data['model'] ?? null;
        $html = '<div style="padding: 24px;">';
        if ($model) {
            $html .= '<div class="plugs-dbg-section-title">📦 Model Data</div>';
            $html .= '<div class="plugs-dbg-code-block">' . $this->formatValue($model, 0) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render exception section.
     */
    protected function renderException(array $data): string
    {
        $class = $data['class'] ?? 'Exception';
        $message = $data['message'] ?? '';
        $file = $data['file'] ?? '';
        $line = $data['line'] ?? 0;
        $trace = $data['trace'] ?? [];

        $html = '<div style="padding: 32px;">';
        $html .= '<div class="plugs-dbg-alert plugs-dbg-alert-danger"> <div class="plugs-dbg-alert-title">💥 ' . htmlspecialchars($class) . '</div> <div style="font-size: 18px; margin-top: 8px;">' . htmlspecialchars($message) . '</div> </div>';
        $html .= '<div class="plugs-dbg-stats-grid" style="margin-top: 24px;"> <div class="plugs-dbg-stat-card"><div class="plugs-dbg-stat-label">📍 Location</div><div class="plugs-dbg-stat-value" style="font-size: 13px;">' . basename($file) . ':' . $line . '</div></div> </div>';

        if (!empty($trace)) {
            $html .= '<div class="plugs-dbg-section-title" style="margin-top: 32px;">📚 Stack Trace</div>';
            foreach (array_slice($trace, 0, 10) as $index => $frame) {
                $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()';
                $html .= '<div class="plugs-dbg-var-item" style="margin-bottom: 8px;"> <div class="plugs-dbg-var-header"> <div class="plugs-dbg-var-title"><span>#' . $index . '</span> <code>' . htmlspecialchars($call) . '</code></div> <div class="plugs-dbg-var-badges"><span class="plugs-dbg-badge">' . basename($frame['file'] ?? 'internal') . ':' . ($frame['line'] ?? '-') . '</span></div> </div> </div>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render HTTP response section.
     */
    protected function renderHttp(array $data): string
    {
        $statusCode = $data['status_code'] ?? 0;
        $html = '<div style="padding: 32px;">';
        $html .= '<div class="plugs-dbg-section-title" style="font-size: 18px; color: var(--text-primary);">🌐 HTTP Response</div>';
        $html .= '<div class="plugs-dbg-alert plugs-dbg-alert-success" style="margin-top: 16px;"> <div class="plugs-dbg-alert-title">Status: ' . $statusCode . ' ' . ($data['reason'] ?? '') . '</div> </div>';

        if (isset($data['headers'])) {
            $html .= '<div class="plugs-dbg-section-title" style="margin-top: 24px;">📋 Headers</div>';
            $html .= '<div class="plugs-dbg-code-block">';
            foreach ($data['headers'] as $name => $value) {
                $html .= '<span class="plugs-dbg-syntax-key">' . htmlspecialchars($name) . '</span>: <span class="plugs-dbg-syntax-string">"' . htmlspecialchars(is_array($value) ? implode(', ', $value) : $value) . '"</span><br>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render profile section.
     */
    public function renderProfile(array $data): string
    {
        $html = '<div style="padding: 32px;">';
        $html .= '<div class="plugs-dbg-section-title" style="font-size: 18px; color: var(--text-primary);">⚡ Performance Profile</div>';
        $html .= '<div class="plugs-dbg-stats-grid" style="margin-top: 24px;">';
        $html .= '<div class="plugs-dbg-stat-card" style="border-left: 4px solid #10b981;"> <div class="plugs-dbg-stat-label">⏱️ Total Execution</div> <div class="plugs-dbg-stat-value" style="color: #10b981;">' . number_format($data['execution_time_ms'] ?? 0, 2) . ' ms</div> </div>';
        $html .= '<div class="plugs-dbg-stat-card" style="border-left: 4px solid #6366f1;"> <div class="plugs-dbg-stat-label">🧠 Memory Peak</div> <div class="plugs-dbg-stat-value" style="color: #6366f1;">' . $this->formatBytes($data['memory_used'] ?? 0) . '</div> </div>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Format value for display.
     */
    protected function formatValue($value, int $depth = 0, array $path = []): string
    {
        if ($depth > 12)
            return '<span class="plugs-dbg-syntax-null">... (depth limit)</span>';
        $indent = str_repeat('  ', $depth);

        if ($value === null)
            return '<span class="plugs-dbg-syntax-null">null</span>';
        if (is_bool($value))
            return '<span class="plugs-dbg-syntax-bool">' . ($value ? 'true' : 'false') . '</span>';
        if (is_int($value) || is_float($value))
            return '<span class="plugs-dbg-syntax-number">' . $value . '</span>';
        if (is_string($value)) {
            $escaped = htmlspecialchars($value);
            return '<span class="plugs-dbg-syntax-string">"' . $escaped . '"</span>';
        }

        if (is_array($value)) {
            if (empty($value))
                return '<span class="plugs-dbg-syntax-array">[]</span>';
            $html = '<span class="plugs-dbg-syntax-array">Array(' . count($value) . ')</span> [<br>';
            foreach ($value as $key => $val) {
                $html .= $indent . '  <span class="plugs-dbg-syntax-key">' . (is_string($key) ? '"' . htmlspecialchars($key) . '"' : $key) . '</span> => ';
                $html .= $this->formatValue($val, $depth + 1);
                $html .= '<br>';
            }
            $html .= $indent . ']';
            return $html;
        }

        if (is_object($value)) {
            $className = get_class($value);
            $html = '<span class="plugs-dbg-syntax-object">Object(' . $className . ')' . '</span> {<br>';

            // Simplified object property traversal for now
            if (method_exists($value, '__debugInfo')) {
                foreach ($value->__debugInfo() as $key => $val) {
                    $html .= $indent . '  <span class="plugs-dbg-syntax-key">' . htmlspecialchars((string) $key) . '</span> => ' . $this->formatValue($val, $depth + 1) . '<br>';
                }
            } else {
                $html .= $indent . '  <span class="plugs-dbg-syntax-null">...</span><br>';
            }

            $html .= $indent . '}';
            return $html;
        }

        return '<span class="plugs-dbg-syntax-null">unknown</span>';
    }

    /**
     * Utility: Format bytes.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++)
            $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Utility: Get variable size.
     */
    protected function getVariableSize($var): int
    {
        return strlen(serialize($var));
    }
}
