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

        // Prepare full profile detail HTML
        $detailHtml = self::renderProfileDetails($profile);

        return '
<!-- Plugs Profiler Bar -->
' . plugs_render_styles(true) . '
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
            background: transparent;
            border: none;
            color: #8b5cf6; 
            text-decoration: none; 
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            font-family: inherit;
            font-size: inherit;
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
        
        /* Modal Styles */
        #plugs-profiler-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 36px; /* Height of bar */
            background: rgba(10, 15, 29, 0.95);
            backdrop-filter: blur(10px);
            z-index: 99998;
            overflow-y: auto;
            padding: 40px;
        }
        #plugs-profiler-modal.active { display: block; }
        #plugs-profiler-modal .plugs-debug-wrapper { padding: 0; min-height: auto; max-width: 1400px; }
        #plugs-profiler-modal .plugs-debug-header { margin-top: 0; }
        
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
        <button onclick="document.getElementById(\'plugs-profiler-modal\').classList.toggle(\'active\')" class="pbar-link">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            Profiler
        </button>
        
        <button class="pbar-close" onclick="document.getElementById(\'plugs-profiler-bar\').remove(); document.getElementById(\'plugs-profiler-modal\').remove()" title="Close">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<!-- Plugs Profiler Modal -->
<div id="plugs-profiler-modal">
    ' . $detailHtml . '
</div>
<!-- End Plugs Profiler Bar -->';
    }

    /**
     * Render the profile details using dump.php renderer
     */
    protected static function renderProfileDetails(array $profile): string
    {
        ob_start();

        // Mock data structure for render_profile
        $data = [
            'execution_time_ms' => $profile['duration'] ?? 0,
            'memory_formatted' => $profile['memory']['peak_formatted'] ?? '0 B',
            'query_count' => $profile['database']['query_count'] ?? 0,
            'query_time_ms' => ($profile['database']['total_time'] ?? 0),
            'queries' => $profile['database']['queries'] ?? [],
            'result' => null
        ];

        echo '<div class="plugs-debug-wrapper" data-theme="dark">';

        // Header
        echo '<div class="plugs-debug-header">';
        echo '<div class="header-top">';
        echo '<div class="logo-section"><div class="brand">Plugs Profiler</div>';

        // Git Info in Header
        if (!empty($profile['git']['branch'])) {
            echo '<div class="git-badge" title="Commit: ' . ($profile['git']['hash'] ?? '') . '">';
            echo '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
            echo htmlspecialchars($profile['git']['branch']);
            echo ' <span style="opacity:0.6;font-size:0.9em">#' . ($profile['git']['short_hash'] ?? '') . '</span>';
            echo '</div>';
            echo '<style>.git-badge { display:flex; align-items:center; background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px; color:#cbd5e1; font-family:monospace; margin-left:12px; }</style>';
        }

        echo '</div>'; // End Logo Section
        echo '<div class="header-controls"><button class="action-btn" onclick="document.getElementById(\'plugs-profiler-modal\').classList.remove(\'active\')">Close ✕</button></div></div>';

        // Tabs
        echo '<div class="tabs-nav">';
        echo '<button class="tab-btn active" onclick="switchTab(this, \'tab-overview\')">📊 Overview</button>';
        echo '<button class="tab-btn" onclick="switchTab(this, \'tab-timeline\')">⏱️ Timeline</button>';
        echo '<button class="tab-btn" onclick="switchTab(this, \'tab-queries\')">🔮 Queries (' . count($data['queries']) . ')</button>';
        echo '<button class="tab-btn" onclick="switchTab(this, \'tab-request\')">🌐 Request</button>';
        echo '<button class="tab-btn" onclick="switchTab(this, \'tab-app\')">🧠 Application</button>';
        echo '<button class="tab-btn" onclick="switchTab(this, \'tab-files\')">📂 Files (' . ($profile['files']['count'] ?? 0) . ')</button>';
        echo '<button class="tab-btn" onclick="switchTab(this, \'tab-config\')">⚙️ Config</button>';
        echo '</div>';
        echo '</div>'; // End Header

        echo '<div class="plugs-debug-content">';

        // Tab: Overview
        echo '<div id="tab-overview" class="tab-content active" style="padding: 32px;">';
        echo plugs_render_profile($data);
        echo '</div>';

        // Tab: Timeline
        echo '<div id="tab-timeline" class="tab-content" style="padding: 32px;">';
        echo self::renderTimelineTab($profile);
        echo '</div>';

        // Tab: Queries
        echo '<div id="tab-queries" class="tab-content" style="padding: 0;">';
        echo plugs_render_queries($data);
        echo '</div>';

        // Tab: Request
        echo '<div id="tab-request" class="tab-content" style="padding: 32px;">';
        echo '<div class="code-block">';
        echo plugs_format_value($profile['request'] ?? [], 0);
        echo '</div>';
        echo '</div>';

        // Tab: App (Models & Views)
        echo '<div id="tab-app" class="tab-content" style="padding: 32px;">';
        echo '<div class="tab-grid" style="display:grid; gap:24px;">';

        // Models
        echo '<div class="info-group"><h3>Model Events (' . count($profile['models'] ?? []) . ')</h3>';
        if (!empty($profile['models'])) {
            echo '<table class="info-table" style="width:100%; border-collapse:collapse;">';
            foreach ($profile['models'] as $m) {
                echo '<tr style="border-bottom:1px solid rgba(255,255,255,0.05);">';
                echo '<td style="padding:8px; color:#cbd5e1;">' . class_basename($m['model']) . '</td>';
                echo '<td style="padding:8px;"><span class="badge" style="background:rgba(139,92,246,0.1); color:#a78bfa; padding:2px 6px; border-radius:4px; font-size:11px;">' . $m['event'] . '</span></td>';
                echo '<td style="padding:8px; text-align:right; font-family:monospace;">+' . number_format($m['time_offset'], 2) . ' ms</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:#64748b;">No model events recorded.</p>';
        }
        echo '</div>';

        // Views
        echo '<div class="info-group"><h3>Views Rendered (' . count($profile['views'] ?? []) . ')</h3>';
        if (!empty($profile['views'])) {
            echo '<table class="info-table" style="width:100%; border-collapse:collapse;">';
            foreach ($profile['views'] as $v) {
                echo '<tr style="border-bottom:1px solid rgba(255,255,255,0.05);">';
                echo '<td style="padding:8px; color:#cbd5e1;">' . htmlspecialchars($v['name']) . '</td>';
                echo '<td style="padding:8px; text-align:right; font-family:monospace;">' . number_format($v['duration'], 2) . ' ms</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:#64748b;">No views rendered.</p>';
        }
        echo '</div>';

        echo '</div></div>'; // End App Tab

        // Tab: Files
        echo '<div id="tab-files" class="tab-content" style="padding: 32px;">';
        echo self::renderFilesTab($profile);
        echo '</div>';

        // Tab: Config
        echo '<div id="tab-config" class="tab-content" style="padding: 32px;">';
        echo self::renderConfigTab($profile);
        echo '</div>';

        echo '</div>'; // End Content
        echo '</div>'; // End Wrapper

        // Add scripts
        echo <<<'JS'
<script>
    function switchTab(btn, tabId) {
        const modal = btn.closest('#plugs-profiler-modal');
        modal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        modal.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        modal.querySelector('#' + tabId).classList.add('active');
    }
</script>
JS;

        return ob_get_clean();
    }

    private static function renderTimelineTab(array $profile): string
    {
        $timeline = $profile['timeline'] ?? [];
        if (empty($timeline)) {
            return '<p style="color:#94a3b8;">No timeline data available.</p>';
        }

        $totalDuration = max($profile['duration'] ?? 1, 1);
        $html = '<div class="timeline-v2" style="display:flex; flex-direction:column; gap:16px;">';

        foreach ($timeline as $name => $segment) {
            if (($segment['duration'] ?? null) === null)
                continue;

            $percentage = min(100, ($segment['duration'] / $totalDuration) * 100);
            $startOffset = 0;
            // Calculate start offset relative to request start
            $relativeStart = ($segment['start'] - ($profile['timeline']['total']['start'] ?? $segment['start'])) * 1000;
            $offsetPercent = min(100, ($relativeStart / $totalDuration) * 100);

            $html .= sprintf(
                '
                <div class="timeline-row" style="display:flex; align-items:center; gap:24px;">
                    <div class="timeline-info" style="width:200px; font-size:13px;">
                        <span style="color:#f8fafc; font-weight:500;">%s</span>
                        <div style="color:#94a3b8; font-size:12px; font-family:monospace;">%s ms</div>
                    </div>
                    <div class="timeline-track" style="flex:1; height:8px; background:rgba(255,255,255,0.05); border-radius:4px; position:relative;">
                        <div class="timeline-bar" style="
                            position:absolute; 
                            height:100%%; 
                            background:linear-gradient(90deg, #8b5cf6, #3b82f6); 
                            border-radius:4px;
                            width: %s%%; 
                            left: %s%%;
                            box-shadow: 0 0 10px rgba(139, 92, 246, 0.3);
                        "></div>
                    </div>
                </div>',
                htmlspecialchars($segment['label']),
                number_format((float) $segment['duration'], 2),
                number_format($percentage, 2),
                number_format($offsetPercent, 2)
            );
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderFilesTab(array $profile): string
    {
        $files = $profile['files']['list'] ?? [];
        if (empty($files)) {
            return '<div style="text-align:center; padding:20px; color:#94a3b8;">No file list captured.</div>';
        }

        $html = '<div class="file-list">';
        $html .= '<input type="text" placeholder="Filter files..." id="plugs-file-filter" style="width:100%; padding:10px; margin-bottom:20px; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:white; border-radius:6px; font-family:monospace;">';
        $html .= '<div class="files-container" style="max-height:600px; overflow-y:auto; font-family:monospace; font-size:13px;">';

        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        foreach ($files as $file) {
            $displayFile = $basePath ? str_replace($basePath, '', $file) : $file;
            $isVendor = str_contains($displayFile, 'vendor');

            $html .= sprintf(
                '<div class="file-item" style="padding:6px 10px; border-bottom:1px solid rgba(255,255,255,0.05); color:%s;">%s</div>',
                $isVendor ? '#94a3b8' : '#e2e8f0',
                htmlspecialchars($displayFile)
            );
        }
        $html .= '</div>';
        $html .= '<script>
            document.getElementById("plugs-file-filter").addEventListener("input", function(e) {
                const val = e.target.value.toLowerCase();
                document.querySelectorAll(".file-item").forEach(el => {
                    el.style.display = el.textContent.toLowerCase().includes(val) ? "block" : "none";
                });
            });
        </script>';
        $html .= '</div>';

        return $html;
    }

    private static function renderConfigTab(array $profile): string
    {
        $data = [
            'PHP Version' => $profile['php']['version'] ?? PHP_VERSION,
            'SAPI' => $profile['php']['sapi'] ?? PHP_SAPI,
            'OS' => PHP_OS,
            'Framework' => 'Plugs Framework',
            'Environment' => getenv('APP_ENV') ?: 'production',
            'Debug Mode' => (getenv('APP_DEBUG') ? 'Enabled' : 'Disabled'),
            'Timezone' => date_default_timezone_get(),
            'Memory Limit' => ini_get('memory_limit'),
        ];

        $html = '<table class="info-table" style="width:100%; border-collapse:collapse;">';
        foreach ($data as $k => $v) {
            $html .= sprintf(
                '<tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                    <td style="padding:12px; color:#94a3b8; width:200px;">%s</td>
                    <td style="padding:12px; color:#f8fafc; font-family:monospace;">%s</td>
                </tr>',
                htmlspecialchars($k),
                htmlspecialchars((string) $v)
            );
        }
        $html .= '</table>';

        if (isset($profile['git']['branch'])) {
            $html .= '<h3 style="margin:24px 0 12px; color:#cbd5e1; font-size:16px;">Source Control</h3>';
            $html .= '<table class="info-table" style="width:100%; border-collapse:collapse;">';
            $html .= '<tr><td style="padding:12px; color:#94a3b8; width:200px;">Branch</td><td style="padding:12px; color:#f8fafc; font-family:monospace;">' . htmlspecialchars($profile['git']['branch']) . '</td></tr>';
            $html .= '<tr><td style="padding:12px; color:#94a3b8; width:200px;">Commit</td><td style="padding:12px; color:#f8fafc; font-family:monospace;">' . htmlspecialchars($profile['git']['hash']) . '</td></tr>';
            $html .= '</table>';
        }

        return $html;
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
