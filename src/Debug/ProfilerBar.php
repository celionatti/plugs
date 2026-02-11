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
    public static function render(array $profile, ?string $nonce = null): string
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
        $detailHtml = self::renderProfileDetails($profile, $nonce);

        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        return '
<!-- Plugs Profiler Bar -->
' . plugs_render_styles(true, $nonce) . '
<div id="plugs-profiler-bar" class="plugs-safe-scope" style="
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
    <style' . $nonceAttr . '>
        #plugs-profiler-bar * { box-sizing: border-box; margin: 0; padding: 0; }
        #plugs-profiler-bar .pbar-brand { 
            font-family: "Dancing Script", cursive, sans-serif;
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 12px;
            white-space: nowrap;
            filter: drop-shadow(0 0 8px rgba(167, 139, 250, 0.4));
        }
        #plugs-profiler-bar .pbar-items {
            display: flex;
            align-items: center;
            gap: 16px;
            overflow-x: auto;
            flex: 1;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        #plugs-profiler-bar .pbar-items::-webkit-scrollbar { display: none; }
        #plugs-profiler-bar .pbar-item { display: flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0; }
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
            white-space: nowrap;
        }
        #plugs-profiler-bar .pbar-link:hover { color: #a78bfa; }
        #plugs-profiler-bar .pbar-sep { 
            width: 1px; 
            height: 16px; 
            background: rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }
        #plugs-profiler-bar .pbar-right { margin-left: auto; display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
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
            bottom: 36px;
            background: rgba(10, 15, 29, 0.95);
            backdrop-filter: blur(10px);
            z-index: 999998;
            overflow-y: auto;
            padding: 20px;
        }
        #plugs-profiler-modal.active { display: block; }
        #plugs-profiler-modal .plugs-debug-wrapper { padding: 0; min-height: auto; max-width: 1400px; margin: 0 auto; }
        #plugs-profiler-modal .plugs-debug-header { margin-top: 0; }
        #plugs-profiler-bar { z-index: 999999 !important; }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            #plugs-profiler-bar {
                padding: 0 12px !important;
                gap: 12px !important;
                font-size: 11px !important;
            }
            #plugs-profiler-bar .pbar-brand {
                font-size: 14px;
            }
            #plugs-profiler-bar .pbar-label {
                display: none;
            }
            #plugs-profiler-bar .pbar-items {
                gap: 12px;
            }
            #plugs-profiler-bar .pbar-right {
                gap: 8px;
            }
            #plugs-profiler-bar .pbar-link span {
                display: none;
            }
            #plugs-profiler-modal {
                padding: 12px;
                bottom: 36px;
            }
            #plugs-profiler-modal .plugs-debug-header {
                padding: 16px !important;
            }
            #plugs-profiler-modal .plugs-tabs-nav {
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 4px;
                padding: 4px;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            #plugs-profiler-modal .plugs-tabs-nav::-webkit-scrollbar { display: none; }
            #plugs-profiler-modal .plugs-tab-btn {
                padding: 8px 12px;
                font-size: 11px;
                white-space: nowrap;
                flex-shrink: 0;
            }
            #plugs-profiler-modal .plugs-tab-content {
                padding: 16px !important;
            }
            #plugs-profiler-modal .plugs-stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px !important;
            }
            #plugs-profiler-modal .plugs-stat-card {
                padding: 12px !important;
            }
            #plugs-profiler-modal .plugs-stat-value {
                font-size: 14px !important;
            }
            #plugs-profiler-modal .timeline-row {
                flex-direction: column !important;
                gap: 8px !important;
            }
            #plugs-profiler-modal .timeline-info {
                width: 100% !important;
            }
        }
        
        @media (max-width: 480px) {
            #plugs-profiler-bar .pbar-sep {
                display: none;
            }
            #plugs-profiler-bar .pbar-items {
                gap: 8px;
            }
            #plugs-profiler-modal .plugs-stats-grid {
                grid-template-columns: 1fr !important;
            }
            #plugs-profiler-modal .plugs-header-top {
                flex-direction: column !important;
                gap: 12px !important;
                text-align: center;
            }
        }
        
        @import url("https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=JetBrains+Mono:wght@400;500&display=swap");
    </style>
    
    <span class="pbar-brand">⚡ Plugs</span>
    
    <div class="pbar-sep"></div>
    
    <div class="pbar-items">
        <div class="pbar-item">
            <span class="pbar-label">Time:</span>
            <span class="pbar-value ' . $durationClass . '">' . number_format($duration, 2) . 'ms</span>
        </div>
        
        <div class="pbar-item">
            <span class="pbar-label">Mem:</span>
            <span class="pbar-value">' . htmlspecialchars($memory) . '</span>
        </div>

        <div class="pbar-item">
            <span class="pbar-label">Route:</span>
            <span class="pbar-value" style="color: #60a5fa;">' . htmlspecialchars($profile['request']['route'] ?? 'unnamed') . '</span>
        </div>

        <div class="pbar-item">
            <span class="pbar-label">Mid:</span>
            <span class="pbar-value" style="color: #f59e0b;">' . number_format($profile['timeline']['middleware']['duration'] ?? 0, 2) . 'ms</span>
        </div>
        
        <div class="pbar-item">
            <span class="pbar-label">DB:</span>
            <span class="pbar-value" style="color: #a78bfa;">' . $queryCount . '</span>
            <span class="pbar-label">(' . $queryTime . 'ms)</span>
        </div>
        
        <div class="pbar-item">
            <span class="pbar-label">HTTP:</span>
            <span class="pbar-value ' . $statusClass . '">' . $statusCode . '</span>
        </div>
    </div>
    
    <div class="pbar-right">
        <button id="plugs-profiler-toggle" class="pbar-link">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <span>Profiler</span>
        </button>
        
        <button id="plugs-profiler-close" class="pbar-close" title="Close">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<!-- Plugs Profiler Modal -->
<div id="plugs-profiler-modal" class="plugs-safe-scope">
    ' . $detailHtml . '
</div>
<!-- End Plugs Profiler Bar -->';
    }

    /**
     * Render the profile details using dump.php renderer
     */
    protected static function renderProfileDetails(array $profile, ?string $nonce = null): string
    {
        ob_start();

        // Calculate granular timings
        $middlewareTime = 0;
        foreach ($profile['timeline'] as $key => $segment) {
            if (str_starts_with($key, 'mw_')) {
                $middlewareTime += ($segment['duration'] ?? 0);
            }
        }
        $controllerTime = $profile['timeline']['controller']['duration'] ?? 0;

        // Mock data structure for render_profile
        $data = [
            'execution_time_ms' => $profile['duration'] ?? 0,
            'memory_formatted' => $profile['memory']['peak_formatted'] ?? '0 B',
            'query_count' => $profile['database']['query_count'] ?? 0,
            'query_time_ms' => ($profile['database']['query_time_ms'] ?? ($profile['database']['total_time'] ?? 0) * 1000),
            'queries' => $profile['database']['queries'] ?? [],
            'stats' => $profile['database'],
            'middleware_time_ms' => $middlewareTime,
            'controller_time_ms' => $controllerTime,
            'routing_time_ms' => $profile['timeline']['routing']['duration'] ?? 0,
            'timeline' => $profile['timeline'] ?? [],
            'result' => null,
        ];

        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        echo '<div class="plugs-debug-wrapper" data-theme="dark">';

        // Header
        echo '<div class="plugs-debug-header">';
        echo '<div class="plugs-header-top">';
        echo '<div class="plugs-logo-section"><div class="plugs-brand">Plugs Profiler</div>';

        // Git Info in Header
        if (!empty($profile['git']['branch'])) {
            echo '<div class="git-badge" title="Commit: ' . ($profile['git']['hash'] ?? '') . '">';
            echo '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
            echo htmlspecialchars($profile['git']['branch']);
            echo ' <span style="opacity:0.6;font-size:0.9em">#' . ($profile['git']['short_hash'] ?? '') . '</span>';
            echo '</div>';
            echo '<style' . $nonceAttr . '>.git-badge { display:flex; align-items:center; background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px; color:#cbd5e1; font-family:monospace; margin-left:12px; }</style>';
        }

        echo '</div>'; // End Logo Section
        echo '<div class="plugs-header-controls"><button id="plugs-profiler-modal-close" class="plugs-action-btn">Close ✕</button></div></div>';

        // Tabs
        echo '<div class="plugs-tabs-nav">';
        echo '<button class="plugs-tab-btn active" data-tab="tab-overview">📊 Overview</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-timeline">⏱️ Timeline</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-queries">🔮 Queries (' . count($data['queries']) . ')</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-route">🛣️ Route</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-request">🌐 Request</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-app">🧠 Application</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-files">📂 Files (' . ($profile['files']['count'] ?? 0) . ')</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-history">📜 History</button>';
        echo '<button class="plugs-tab-btn" data-tab="tab-config">⚙️ Config</button>';
        echo '</div>';
        echo '</div>'; // End Header

        echo '<div class="plugs-debug-content">';

        // Tab: Overview
        echo '<div id="tab-overview" class="plugs-tab-content active" style="padding: 32px;">';
        echo plugs_render_profile($data);
        echo '</div>';

        // Tab: Timeline
        echo '<div id="tab-timeline" class="plugs-tab-content" style="padding: 32px;">';
        echo self::renderTimelineTab($profile);
        echo '</div>';

        // Tab: Queries
        echo '<div id="tab-queries" class="plugs-tab-content" style="padding: 0;">';
        echo plugs_render_queries($data);
        echo '</div>';

        // Tab: Route
        echo '<div id="tab-route" class="plugs-tab-content" style="padding: 32px;">';
        echo self::renderRouteTab($profile);
        echo '</div>';

        // Tab: Request
        echo '<div id="tab-request" class="plugs-tab-content" style="padding: 32px;">';
        echo '<div class="plugs-code-block">';
        echo plugs_format_value($profile['request'] ?? [], 0);
        echo '</div>';
        echo '</div>';

        // Tab: App (Models & Views)
        echo '<div id="tab-app" class="plugs-tab-content" style="padding: 32px;">';
        echo '<div class="plugs-tab-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:24px;">';

        // Models
        echo '<div class="plugs-info-group" style="background:rgba(255,255,255,0.02); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">';
        echo '<h3 style="color:#a78bfa; margin-bottom:16px; font-size:16px; display:flex; justify-content:space-between; align-items:center;">';
        echo '<span>🏗️ Model Lifecycle</span>';
        echo '<span class="plugs-badge" style="background:rgba(167,139,250,0.1); color:#a78bfa;">' . count($profile['models'] ?? []) . '</span>';
        echo '</h3>';

        if (!empty($profile['models'])) {
            echo '<table class="plugs-info-table" style="width:100%; border-collapse:collapse; font-size:13px;">';
            foreach ($profile['models'] as $m) {
                $eventColor = match (strtolower($m['event'])) {
                    'created', 'saved' => '#10b981',
                    'updated' => '#f59e0b',
                    'deleted' => '#ef4444',
                    default => '#a78bfa'
                };
                echo '<tr style="border-bottom:1px solid rgba(255,255,255,0.03);">';
                echo '<td style="padding:10px 0; color:#cbd5e1; font-weight:500;">' . basename(str_replace('\\', '/', $m['model'])) . '</td>';
                echo '<td style="padding:10px 0;"><span class="plugs-badge" style="background:' . $eventColor . '15; color:' . $eventColor . '; border:1px solid ' . $eventColor . '30;">' . strtoupper($m['event']) . '</span></td>';
                echo '<td style="padding:10px 0; text-align:right; font-family:monospace; color:#94a3b8;">+' . number_format($m['time_offset'], 2) . ' ms</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:#64748b; font-style:italic;">No model activity in this request.</p>';
        }
        echo '</div>';

        // Views
        echo '<div class="plugs-info-group" style="background:rgba(255,255,255,0.02); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">';
        echo '<h3 style="color:#10b981; margin-bottom:16px; font-size:16px; display:flex; justify-content:space-between; align-items:center;">';
        echo '<span>🖼️ Rendered Views</span>';
        echo '<span class="plugs-badge" style="background:rgba(16,185,129,0.1); color:#10b981;">' . count($profile['views'] ?? []) . '</span>';
        echo '</h3>';

        if (!empty($profile['views'])) {
            echo '<table class="plugs-info-table" style="width:100%; border-collapse:collapse; font-size:13px;">';
            foreach ($profile['views'] as $v) {
                echo '<tr style="border-bottom:1px solid rgba(255,255,255,0.03);">';
                echo '<td style="padding:10px 0; color:#cbd5e1;">' . htmlspecialchars($v['name']) . '</td>';
                echo '<td style="padding:10px 0; text-align:right; font-family:monospace; color:#10b981;">' . number_format($v['duration'], 2) . ' ms</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:#64748b; font-style:italic;">No templates were rendered.</p>';
        }
        echo '</div>';

        echo '</div></div>'; // End App Tab

        // Tab: Files
        echo '<div id="tab-files" class="plugs-tab-content" style="padding: 32px;">';
        echo self::renderFilesTab($profile, $nonce);
        echo '</div>';

        // Tab: History
        echo '<div id="tab-history" class="plugs-tab-content" style="padding: 32px;">';
        echo self::renderHistoryTab($nonce);
        echo '</div>';

        // Tab: Config
        echo '<div id="tab-config" class="plugs-tab-content" style="padding: 32px;">';
        echo self::renderConfigTab($profile);
        echo '</div>';

        echo '</div>'; // End Content
        echo '</div>'; // End Wrapper

        // Add scripts
        echo <<<JS
<script{$nonceAttr}>
    (function() {
        // Toggle Btn
        const toggleBtn = document.getElementById('plugs-profiler-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const modal = document.getElementById('plugs-profiler-modal');
                if (modal) modal.classList.toggle('active');
            });
        }

        // Close Bar Btn
        const closeBarBtn = document.getElementById('plugs-profiler-close');
        if (closeBarBtn) {
            closeBarBtn.addEventListener('click', function() {
                document.getElementById('plugs-profiler-bar')?.remove();
                document.getElementById('plugs-profiler-modal')?.remove();
            });
        }

        // Close Modal Btn
        const closeModalBtn = document.getElementById('plugs-profiler-modal-close');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                const modal = document.getElementById('plugs-profiler-modal');
                if (modal) modal.classList.remove('active');
            });
        }

        // History Management
        const clearHistoryBtn = document.getElementById('plugs-clear-history');
        if (clearHistoryBtn) {
            clearHistoryBtn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to clear all profiling history?')) return;
                try {
                    const response = await fetch('/plugs/profiler/clear', { method: 'POST' });
                    const result = await response.json();
                    if (result.success) {
                        alert(result.message);
                        location.reload();
                    }
                } catch (e) { console.error('Failed to clear history', e); }
            });
        }

        document.querySelectorAll('.plugs-delete-profile').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.getAttribute('data-id');
                if (!id || !confirm('Delete this profile?')) return;
                try {
                    const response = await fetch('/plugs/profiler/' + id, { method: 'DELETE' });
                    const result = await response.json();
                    if (result.success) {
                        const row = document.getElementById('profile-row-' + id);
                        if (row) row.remove();
                    }
                } catch (e) { console.error('Failed to delete profile', e); }
            });
        });

        // Tabs
        function plugsSwitchTab(btn) {
            const tabId = btn.getAttribute('data-tab');
            const modal = btn.closest('#plugs-profiler-modal, .plugs-debug-wrapper');
            if (!modal || !tabId) return;

            modal.querySelectorAll('.plugs-tab-btn, .tab-btn').forEach(b => b.classList.remove('active'));
            modal.querySelectorAll('.plugs-tab-content, .tab-content').forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            const target = modal.querySelector('#' + tabId);
            if (target) target.classList.add('active');
        }

        document.querySelectorAll('.plugs-tab-btn[data-tab]').forEach(btn => {
            btn.addEventListener('click', function() {
                plugsSwitchTab(this);
            });
        });
    })();
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
            if (($segment['duration'] ?? null) === null) {
                continue;
            }

            $percentage = min(100, ($segment['duration'] / $totalDuration) * 100);
            // Calculate start offset relative to request start
            $relativeStart = ($segment['start'] - ($profile['timeline']['total']['start'] ?? $segment['start'])) * 1000;
            $offsetPercent = min(100, ($relativeStart / $totalDuration) * 100);

            $color = '#8b5cf6'; // Default Purple
            if (str_contains(strtolower($name), 'middleware'))
                $color = '#f59e0b'; // Amber
            if (str_contains(strtolower($name), 'routing'))
                $color = '#3b82f6'; // Blue
            if (str_contains(strtolower($name), 'view'))
                $color = '#10b981'; // Emerald
            if ($name === 'total')
                $color = 'rgba(255,255,255,0.2)';

            $html .= sprintf(
                '
                <div class="timeline-row" style="display:flex; align-items:center; gap:24px; padding: 12px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.03)\'" onmouseout="this.style.background=\'transparent\'">
                    <div class="timeline-info" style="width:240px; font-size:13px;">
                        <span style="color:#f8fafc; font-weight:500; display:block; margin-bottom:2px;">%s</span>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <span style="color:#94a3b8; font-size:11px; font-family:monospace;">%s ms</span>
                            <span style="color:#64748b; font-size:10px;">• starts @ %s ms</span>
                        </div>
                    </div>
                    <div class="timeline-track" style="flex:1; height:10px; background:rgba(255,255,255,0.05); border-radius:10px; position:relative; overflow:hidden;">
                        <div class="timeline-bar" style="
                            position:absolute; 
                            height:100%%; 
                            background:%s; 
                            border-radius:10px;
                            width: %s%%; 
                            left: %s%%;
                            box-shadow: 0 0 15px %s;
                            opacity: 0.8;
                        "></div>
                    </div>
                </div>',
                htmlspecialchars($segment['label']),
                number_format((float) $segment['duration'], 4),
                number_format((float) $relativeStart, 2),
                $color,
                number_format($percentage, 2),
                number_format($offsetPercent, 2),
                str_replace(',0.2)', ',0.4)', $color)
            );
        }
        $html .= '</div>';

        return $html;
    }

    private static function renderFilesTab(array $profile, ?string $nonce = null): string
    {
        $files = $profile['files']['list'] ?? [];
        if (empty($files)) {
            return '<div style="text-align:center; padding:40px; color:#94a3b8;">No file list captured. Ensure <code>opcache</code> is not preventing file tracing.</div>';
        }

        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        $groups = ['App' => [], 'Vendor' => [], 'Framework' => [], 'Other' => []];
        $totalSize = 0;

        foreach ($files as $file) {
            $displayFile = $basePath ? str_replace($basePath, '', $file) : $file;
            $isVendor = str_contains($displayFile, 'vendor');
            $isFramework = str_contains($displayFile, 'plugs/src') || str_contains($displayFile, 'Plugs\\');

            $fileInfo = [
                'path' => $displayFile
            ];

            if ($isFramework)
                $groups['Framework'][] = $fileInfo;
            elseif ($isVendor)
                $groups['Vendor'][] = $fileInfo;
            elseif (str_contains($displayFile, 'app/'))
                $groups['App'][] = $fileInfo;
            else
                $groups['Other'][] = $fileInfo;
        }

        $html = '<div class="plugs-file-analytics" style="padding: 0 10px;">';

        // Summary Header
        $html .= '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:16px; margin-bottom:30px;">';
        $html .= '<div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:10px; border:1px solid rgba(255,255,255,0.05);">';
        $html .= '<div style="color:#94a3b8; font-size:11px; text-transform:uppercase; letter-spacing:1px;">Total Files</div>';
        $html .= '<div style="color:#f8fafc; font-size:20px; font-weight:600; margin-top:4px;">' . count($files) . '</div></div>';
        $html .= '</div>';

        $html .= '<input type="text" placeholder="Filter included files..." id="plugs-file-filter" style="width:100%; padding:12px 16px; margin-bottom:24px; background:rgba(0,0,0,0.22); border:1px solid rgba(255,255,255,0.08); color:white; border-radius:8px; font-family:monospace; font-size:14px; outline:none; transition:border-color 0.2s;" onfocus="this.style.borderColor=\'#3b82f6\'" onblur="this.style.borderColor=\'rgba(255,255,255,0.08)\'">';

        $html .= '<div class="plugs-files-groups" style="display:flex; flex-direction:column; gap:32px;">';
        foreach ($groups as $groupName => $groupFiles) {
            if (empty($groupFiles))
                continue;

            $html .= '<div class="plugs-file-group">';
            $html .= '<h4 style="color:#f8fafc; font-size:15px; margin-bottom:12px; display:flex; align-items:center; gap:10px;">';
            $html .= '<span>' . $groupName . '</span>';
            $html .= '<span style="font-size:11px; color:#64748b; background:rgba(255,255,255,0.05); padding:2px 8px; border-radius:100px;">' . count($groupFiles) . ' files</span>';
            $html .= '</h4>';

            $html .= '<div class="plugs-files-list-container" style="max-height:400px; overflow-y:auto; background:rgba(0,0,0,0.15); border-radius:10px; border:1px solid rgba(255,255,255,0.03); scrollbar-width:thin;">';
            foreach ($groupFiles as $f) {
                $html .= sprintf(
                    '<div class="plugs-file-item" style="padding:10px 16px; border-bottom:1px solid rgba(255,255,255,0.02); display:flex; justify-content:space-between; align-items:center; font-family:monospace; font-size:12px; transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.02)\'" onmouseout="this.style.background=\'transparent\'">
                        <span class="file-path" style="color:#cbd5e1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-right:20px;">%s</span>
                    </div>',
                    htmlspecialchars($f['path'])
                );
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';

        $html .= '<script' . $nonceAttr . '>
            document.getElementById("plugs-file-filter").addEventListener("input", function(e) {
                const val = e.target.value.toLowerCase();
                document.querySelectorAll(".plugs-file-item").forEach(el => {
                    const path = el.querySelector(".file-path").textContent.toLowerCase();
                    el.style.display = path.includes(val) ? "flex" : "none";
                });
                // Hide group headers if all files in them are hidden
                document.querySelectorAll(".plugs-file-group").forEach(group => {
                    const visible = group.querySelectorAll(".plugs-file-item[style*=\'display: flex\']").length > 0 || val === "";
                    group.style.display = visible ? "block" : "none";
                });
            });
        </script></div>';

        return $html;
    }

    private static function renderHistoryTab(?string $nonce = null): string
    {
        $profiles = Profiler::getProfiles(50);
        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        $html = '<div class="plugs-history-management">';
        $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">';
        $html .= '<h3 style="color:#f8fafc; font-size:18px;">Recent Requests</h3>';
        $html .= '<button id="plugs-clear-history" class="plugs-action-btn" style="background:#ef4444; border-color:#ef4444; color:white;">Clear All History</button>';
        $html .= '</div>';

        if (empty($profiles)) {
            $html .= '<div style="text-align:center; padding:60px; color:#64748b;">';
            $html .= '<div style="font-size:40px; margin-bottom:16px;">📜</div>';
            $html .= '<p>No profiling history found.</p>';
            $html .= '</div>';
        } else {
            $html .= '<div style="overflow-x:auto;"><table class="plugs-info-table" style="width:100%; border-collapse:collapse; font-size:13px;">';
            $html .= '<thead style="background:rgba(255,255,255,0.03); text-align:left;"><tr>';
            $html .= '<th style="padding:12px 16px; color:#94a3b8; font-weight:500;">Method</th>';
            $html .= '<th style="padding:12px 16px; color:#94a3b8; font-weight:500;">URI</th>';
            $html .= '<th style="padding:12px 16px; color:#94a3b8; font-weight:500;">Time</th>';
            $html .= '<th style="padding:12px 16px; color:#94a3b8; font-weight:500;">Status</th>';
            $html .= '<th style="padding:12px 16px; color:#94a3b8; font-weight:500; text-align:right;">Actions</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($profiles as $p) {
                $statusColor = match (true) {
                    $p['request']['status_code'] >= 500 => '#ef4444',
                    $p['request']['status_code'] >= 400 => '#f59e0b',
                    default => '#10b981'
                };

                $html .= sprintf(
                    '<tr id="profile-row-%s" style="border-bottom:1px solid rgba(255,255,255,0.03); transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.02)\'" onmouseout="this.style.background=\'transparent\'">
                        <td style="padding:12px 16px;"><span class="plugs-badge" style="background:rgba(255,255,255,0.05); color:#cbd5e1;">%s</span></td>
                        <td style="padding:12px 16px; color:#f8fafc; font-family:monospace; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">%s</td>
                        <td style="padding:12px 16px; color:#94a3b8;">%s ms</td>
                        <td style="padding:12px 16px;"><span style="color:%s;">● %d</span></td>
                        <td style="padding:12px 16px; text-align:right;">
                            <button class="plugs-delete-profile" data-id="%s" style="background:transparent; border:none; color:#64748b; cursor:pointer; font-size:16px;" title="Delete">✕</button>
                        </td>
                    </tr>',
                    htmlspecialchars($p['id']),
                    htmlspecialchars($p['request']['method'] ?? 'GET'),
                    htmlspecialchars($p['request']['uri'] ?? '/'),
                    number_format($p['duration'] ?? 0, 2),
                    $statusColor,
                    $p['request']['status_code'] ?? 200,
                    htmlspecialchars($p['id'])
                );
            }
            $html .= '</tbody></table></div>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function formatFilesize(int $bytes): string
    {
        if ($bytes <= 0)
            return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return number_format($bytes / pow(1024, $power), 1) . ' ' . $units[$power];
    }

    private static function renderConfigTab(array $profile): string
    {
        $data = [
            'PHP Version' => $profile['php']['version'] ?? PHP_VERSION,
            'SAPI' => $profile['php']['sapi'] ?? PHP_SAPI,
            'OS' => PHP_OS,
            'Framework' => 'Plugs Framework',
            'Environment' => config('app.env') ?? env('APP_ENV', 'production'),
            'Debug Mode' => (config('app.debug') ? 'Enabled' : 'Disabled'),
            'Timezone' => config('app.timezone') ?? date_default_timezone_get(),
            'Memory Limit' => ini_get('memory_limit'),
        ];

        $html = '<table class="plugs-info-table" style="width:100%; border-collapse:collapse;">';
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
            $html .= '<table class="plugs-info-table" style="width:100%; border-collapse:collapse;">';
            $html .= '<tr><td style="padding:12px; color:#94a3b8; width:200px;">Branch</td><td style="padding:12px; color:#f8fafc; font-family:monospace;">' . htmlspecialchars($profile['git']['branch']) . '</td></tr>';
            $html .= '<tr><td style="padding:12px; color:#94a3b8; width:200px;">Commit</td><td style="padding:12px; color:#f8fafc; font-family:monospace;">' . htmlspecialchars($profile['git']['hash']) . '</td></tr>';
            $html .= '</table>';
        }

        return $html;
    }

    /**
     * Inject profiler bar into HTML response
     */
    public static function injectIntoHtml(string $html, array $profile, ?string $nonce = null): string
    {
        // Only inject into HTML responses with closing body tag
        if (stripos($html, '</body>') === false) {
            return $html;
        }

        $bar = self::render($profile, $nonce);

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

    private static function renderRouteTab(array $profile): string
    {
        $route = $profile['request']['route'] ?? 'unnamed';
        $controller = $profile['request']['controller'] ?? 'Closure';
        $method = $profile['request']['method'] ?? 'GET';
        $uri = $profile['request']['uri'] ?? '/';

        $html = '<div class="plugs-route-info" style="display: flex; flex-direction: column; gap: 24px;">';

        $html .= '<div style="background: rgba(96, 165, 250, 0.05); border: 1px solid rgba(96, 165, 250, 0.2); padding: 24px; border-radius: 12px;">';
        $html .= '<h3 style="color: #60a5fa; margin-bottom: 20px;">🛣️ Route Details</h3>';

        $details = [
            'Name' => $route,
            'Controller' => $controller,
            'Method' => $method,
            'URI' => $uri,
        ];

        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($details as $label => $value) {
            $html .= sprintf(
                '<tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td style="padding: 12px 0; color: #94a3b8; width: 150px;">%s</td>
                    <td style="padding: 12px 0; color: #f8fafc; font-family: monospace;">%s</td>
                </tr>',
                $label,
                htmlspecialchars((string) $value)
            );
        }
        $html .= '</table>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
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
