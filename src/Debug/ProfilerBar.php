<?php

declare(strict_types=1);

namespace Plugs\Debug;

/**
 * Profiler Bar
 *
 * Injectable toolbar that displays profiling information at the bottom of HTML pages.
 */
class ProfilerBar
{
    /**
     * Render the profiler bar HTML
     */
    public static function render(array $profile): string
    {
        $duration = $profile['duration'] ?? 0;
        $memory = $profile['memory']['peak_formatted'] ?? '0 B';
        $queryCount = $profile['database']['query_count'] ?? 0;
        $queryTime = round($profile['database']['total_time'] ?? 0, 2);
        $statusCode = $profile['request']['status_code'] ?? 200;
        $profileId = $profile['id'] ?? '';

        $durationClass = self::getDurationClass($duration);
        $statusClass = self::getStatusClass($statusCode);

        return '
<!-- Plugs Profiler Bar -->
<div id="plugs-profiler-bar" style="
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 36px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    border-top: 1px solid rgba(139, 92, 246, 0.3);
    font-family: \'JetBrains Mono\', \'SF Mono\', Consolas, monospace;
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    padding: 0 16px;
    gap: 24px;
    z-index: 99999;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
">
    <style>
        #plugs-profiler-bar * { box-sizing: border-box; margin: 0; padding: 0; }
        #plugs-profiler-bar .pbar-brand { 
            font-family: "Dancing Script", cursive, sans-serif;
            font-size: 16px;
            font-weight: 700;
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 8px;
        }
        #plugs-profiler-bar .pbar-item { display: flex; align-items: center; gap: 6px; }
        #plugs-profiler-bar .pbar-label { color: #64748b; }
        #plugs-profiler-bar .pbar-value { color: #f8fafc; font-weight: 500; }
        #plugs-profiler-bar .pbar-fast { color: #10b981; }
        #plugs-profiler-bar .pbar-medium { color: #f59e0b; }
        #plugs-profiler-bar .pbar-slow { color: #ef4444; }
        #plugs-profiler-bar .pbar-success { color: #10b981; }
        #plugs-profiler-bar .pbar-warning { color: #f59e0b; }
        #plugs-profiler-bar .pbar-error { color: #ef4444; }
        #plugs-profiler-bar .pbar-link { 
            color: #8b5cf6; 
            text-decoration: none; 
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        #plugs-profiler-bar .pbar-link:hover { color: #a78bfa; }
        #plugs-profiler-bar .pbar-sep { 
            width: 1px; 
            height: 16px; 
            background: rgba(255, 255, 255, 0.1); 
        }
        #plugs-profiler-bar .pbar-right { margin-left: auto; display: flex; align-items: center; gap: 16px; }
        #plugs-profiler-bar .pbar-close {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
        }
        #plugs-profiler-bar .pbar-close:hover { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        @import url("https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=JetBrains+Mono:wght@400;500&display=swap");
    </style>
    
    <span class="pbar-brand">⚡ Plugs</span>
    
    <div class="pbar-sep"></div>
    
    <div class="pbar-item">
        <span class="pbar-label">Time:</span>
        <span class="pbar-value ' . $durationClass . '">' . number_format($duration, 2) . ' ms</span>
    </div>
    
    <div class="pbar-item">
        <span class="pbar-label">Memory:</span>
        <span class="pbar-value">' . htmlspecialchars($memory) . '</span>
    </div>
    
    <div class="pbar-item">
        <span class="pbar-label">Queries:</span>
        <span class="pbar-value">' . $queryCount . '</span>
        <span class="pbar-label">(' . $queryTime . ' ms)</span>
    </div>
    
    <div class="pbar-item">
        <span class="pbar-label">Status:</span>
        <span class="pbar-value ' . $statusClass . '">' . $statusCode . '</span>
    </div>
    
    <div class="pbar-right">
        <a href="/plugs/profiler/' . htmlspecialchars($profileId) . '" class="pbar-link" target="_blank">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Details
        </a>
        
        <a href="/plugs/profiler" class="pbar-link" target="_blank">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            Dashboard
        </a>
        
        <button class="pbar-close" onclick="document.getElementById(\'plugs-profiler-bar\').remove()" title="Close">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
<!-- End Plugs Profiler Bar -->';
    }

    /**
     * Inject profiler bar into HTML response
     */
    public static function injectIntoHtml(string $html, array $profile): string
    {
        // Only inject into HTML responses with closing body tag
        if (stripos($html, '</body>') === false) {
            return $html;
        }

        $bar = self::render($profile);

        return str_ireplace('</body>', $bar . '</body>', $html);
    }

    /**
     * Get duration CSS class
     */
    private static function getDurationClass(float $duration): string
    {
        if ($duration > 1000) {
            return 'pbar-slow';
        }
        if ($duration > 200) {
            return 'pbar-medium';
        }

        return 'pbar-fast';
    }

    /**
     * Get status CSS class
     */
    private static function getStatusClass(int $status): string
    {
        if ($status >= 500) {
            return 'pbar-error';
        }
        if ($status >= 400) {
            return 'pbar-warning';
        }

        return 'pbar-success';
    }
}
