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
        $cacheHits = $profile['cache']['hits'] ?? 0;
        $cacheMisses = $profile['cache']['misses'] ?? 0;
        $eventCount = count($profile['events'] ?? []);
        $statusCode = $profile['request']['status_code'] ?? 200;
        $profileId = $profile['id'] ?? '';

        $durationClass = self::getDurationClass($duration);
        $statusClass = self::getStatusClass($statusCode);

        // Prepare full profile detail HTML
        $detailHtml = self::renderProfileDetails($profile, $nonce);

        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';

        // Calculate true middleware overhead
        $controllerTime = $profile['timeline']['controller']['duration'] ?? 0;
        $middlewareTime = max(0, $duration - $controllerTime);

        // Find slowest middleware
        $slowestMwName = '';
        $slowestMwTime = 0;
        foreach ($profile['timeline'] as $key => $segment) {
            if (str_starts_with($key, 'mw_') && ($segment['duration'] ?? 0) > $slowestMwTime) {
                $slowestMwTime = $segment['duration'];
                // Clean up name: mw_Plugs\Http\Middleware\SecurityShieldMiddleware -> SecurityShield
                $parts = explode('\\', substr($key, 3));
                $slowestMwName = end($parts);
                $slowestMwName = str_replace('Middleware', '', $slowestMwName);
            }
        }

        $mwClass = $middlewareTime > 150 ? 'pbar-slow' : ($middlewareTime > 50 ? 'pbar-medium' : 'pbar-value');
        $mwLabel = number_format($middlewareTime, 2) . 'ms';

        // Append slowest MW info if significant
        if ($slowestMwTime > 10) {
            $mwLabel .= ' <span style="font-size:10px; opacity:0.7;">(' . substr($slowestMwName, 0, 15) . ')</span>';
        }

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
            background: rgba(10, 15, 29, 0.98);
            backdrop-filter: blur(20px);
            z-index: 999998;
            overflow-y: scroll; /* Force scrollbar to prevent layout shift */
        }
        #plugs-profiler-modal.active { display: block; }
        #plugs-profiler-modal .plugs-debug-wrapper { padding: 0; min-height: 100%; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; }
        
        #plugs-profiler-modal .plugs-debug-header { 
            margin-top: 0; 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            background: #0f172a; 
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
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
            #plugs-profiler-modal .plugs-dbg-tabs-nav {
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 4px;
                padding: 4px;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            #plugs-profiler-modal .plugs-dbg-tabs-nav::-webkit-scrollbar { display: none; }
            #plugs-profiler-modal .plugs-dbg-tab-btn {
                padding: 8px 12px;
                font-size: 11px;
                white-space: nowrap;
                flex-shrink: 0;
            }
            #plugs-profiler-modal .plugs-dbg-tab-content {
                padding: 16px !important;
            }
            #plugs-profiler-modal .plugs-dbg-stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px !important;
            }
            #plugs-profiler-modal .plugs-dbg-stat-card {
                padding: 12px !important;
            }
            #plugs-profiler-modal .plugs-dbg-stat-value {
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
            #plugs-profiler-modal .plugs-dbg-stats-grid {
                grid-template-columns: 1fr !important;
            }
            #plugs-profiler-modal .plugs-dbg-header-top {
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
            <span class="' . $mwClass . '">' . $mwLabel . '</span>
        </div>
        
        <div class="pbar-item">
            <span class="pbar-label">DB:</span>
            <span class="pbar-value" style="color: #a78bfa;">' . $queryCount . '</span>
            <span class="pbar-label">(' . $queryTime . 'ms)</span>
        </div>

        <div class="pbar-item">
            <span class="pbar-label">Cache:</span>
            <span class="pbar-value" style="color: #10b981;" title="Cache Hits">' . $cacheHits . 'H</span>
            <span style="color:#64748b; margin:0 2px;">/</span>
            <span class="pbar-value" style="color: #ef4444;" title="Cache Misses">' . $cacheMisses . 'M</span>
        </div>

        <div class="pbar-item">
            <span class="pbar-label">Events:</span>
            <span class="pbar-value" style="color: #f59e0b;">' . $eventCount . '</span>
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

        $totalDuration = $profile['duration'] ?? 0;
        $controllerTime = $profile['timeline']['controller']['duration'] ?? 0;
        $middlewareTime = max(0, $totalDuration - $controllerTime);
        $cacheHits = $profile['cache']['hits'] ?? 0;
        $cacheMisses = $profile['cache']['misses'] ?? 0;
        $eventCount = count($profile['events'] ?? []);

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
        echo '<div class="plugs-dbg-header">';
        echo '<div class="plugs-dbg-header-top">';
        echo '<div class="plugs-logo-section"><div class="plugs-dbg-brand">Plugs Profiler</div>';

        if (!empty($profile['git']['branch'])) {
            echo '<div class="pbar-git-badge" title="Commit: ' . ($profile['git']['hash'] ?? '') . '">';
            echo '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
            echo htmlspecialchars($profile['git']['branch']);
            echo ' <span style="opacity:0.6;font-size:0.9em">#' . ($profile['git']['short_hash'] ?? '') . '</span>';
            echo '</div>';
            echo '<style' . $nonceAttr . '>.pbar-git-badge { display:flex; align-items:center; background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px; color:#cbd5e1; font-family:monospace; margin-left:12px; }</style>';
        }

        echo '</div>'; // End Logo Section
        echo '<div class="plugs-dbg-header-controls"><button id="plugs-profiler-modal-close" class="plugs-dbg-action-btn">Close ✕</button></div></div>';

        echo '<div class="plugs-dbg-tabs-nav">';
        echo '<button class="plugs-dbg-tab-btn active" data-tab="tab-overview">📊 Overview</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-timeline">⏱️ Timeline</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-queries">🔮 Queries (' . count($data['queries']) . ')</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-cache">💾 Cache (' . ($cacheHits + $cacheMisses) . ')</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-events">⚡ Events (' . $eventCount . ')</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-route">🛣️ Route</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-request">🌐 Request</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-app">🧠 Application</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-files">📂 Files (' . ($profile['files']['count'] ?? 0) . ')</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-history">📜 History</button>';
        echo '<button class="plugs-dbg-tab-btn" data-tab="tab-config">⚙️ Config</button>';
        echo '</div>';
        echo '</div>'; // End Header

        echo '<div class="plugs-dbg-content">';

        // Tab: Overview
        echo '<div id="tab-overview" class="plugs-dbg-tab-content active" style="padding: 32px;">';
        if (config('security.ai_profiler.enabled', true)) {
            echo '<div style="margin-bottom: 24px; padding: 16px; background: rgba(139, 92, 246, 0.1); border: 1px dashed rgba(139, 92, 246, 0.4); border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">';
            echo '<div>';
            echo '<div style="color: #a78bfa; font-weight: 600; font-size: 14px; margin-bottom: 4px;">✨ AI Performance Insights</div>';
            echo '<div style="color: #94a3b8; font-size: 12px;">Let AI analyze this request and suggest optimizations.</div>';
            echo '</div>';
            echo '<button id="plugs-ai-analyze-request" class="plugs-action-btn" style="background: #8b5cf6; border-color: #8b5cf6; color: white; padding: 8px 16px; font-weight: 600;">Analyze Request</button>';
            echo '</div>';
        }
        echo plugs_render_profile($data);
        echo '</div>';

        // Tab: Timeline
        echo '<div id="tab-timeline" class="plugs-dbg-tab-content" style="padding: 32px;">';
        echo self::renderTimelineTab($profile);
        echo '</div>';

        // Tab: Queries
        echo '<div id="tab-queries" class="plugs-dbg-tab-content" style="padding: 0;">';
        echo plugs_render_queries($data);
        echo '</div>';

        // Tab: Cache
        echo '<div id="tab-cache" class="plugs-dbg-tab-content" style="padding: 32px;">';
        echo self::renderCacheTab($profile);
        echo '</div>';

        // Tab: Events
        echo '<div id="tab-events" class="plugs-dbg-tab-content" style="padding: 32px;">';
        echo self::renderEventsTab($profile);
        echo '</div>';

        // Tab: Route, Request, App, Files, History, Config
        echo '<div id="tab-route" class="plugs-dbg-tab-content" style="padding: 32px;">' . self::renderRouteTab($profile) . '</div>';
        echo '<div id="tab-request" class="plugs-dbg-tab-content" style="padding: 32px;">' . self::renderRequestTab($profile) . '</div>';

        // Tab: App (Models & Views)
        echo '<div id="tab-app" class="plugs-dbg-tab-content" style="padding: 32px;">';
        echo '<div class="pbar-tab-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:24px;">';

        // Models
        echo '<div class="pbar-info-group" style="background:rgba(255,255,255,0.02); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">';
        echo '<h3 style="color:#a78bfa; margin-bottom:16px; font-size:16px; display:flex; justify-content:space-between; align-items:center;">';
        echo '<span>🏗️ Model Lifecycle</span>';
        echo '<span class="plugs-dbg-badge" style="background:rgba(167,139,250,0.1); color:#a78bfa;">' . count($profile['models'] ?? []) . '</span>';
        echo '</h3>';

        if (!empty($profile['models'])) {
            echo '<table class="plugs-dbg-info-table" style="width:100%; border-collapse:collapse; font-size:13px;">';
            foreach ($profile['models'] as $m) {
                $eventColor = match (strtolower($m['event'] ?? '')) {
                    'created', 'saved' => '#10b981',
                    'updated' => '#f59e0b',
                    'deleted' => '#ef4444',
                    default => '#a78bfa'
                };
                echo '<tr style="border-bottom:1px solid rgba(255,255,255,0.03);">';
                echo '<td style="padding:10px 0; color:#cbd5e1; font-weight:500;">' . basename(str_replace('\\', '/', $m['model'])) . '</td>';
                echo '<td style="padding:10px 0;"><span class="plugs-dbg-badge" style="background:' . $eventColor . '15; color:' . $eventColor . '; border:1px solid ' . $eventColor . '30;">' . strtoupper($m['event'] ?? 'UNKNOWN') . '</span></td>';
                echo '<td style="padding:10px 0; text-align:right; font-family:monospace; color:#94a3b8;">+' . number_format($m['time_offset'] ?? 0, 2) . ' ms</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:#64748b; font-style:italic;">No model activity in this request.</p>';
        }
        echo '</div>';

        // Views
        echo '<div class="pbar-info-group" style="background:rgba(255,255,255,0.02); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">';
        echo '<h3 style="color:#10b981; margin-bottom:16px; font-size:16px; display:flex; justify-content:space-between; align-items:center;">';
        echo '<span>🖼️ Rendered Views</span>';
        echo '<span class="plugs-dbg-badge" style="background:rgba(16,185,129,0.1); color:#10b981;">' . count($profile['views'] ?? []) . '</span>';
        echo '</h3>';

        if (!empty($profile['views'])) {
            echo '<table class="plugs-dbg-info-table" style="width:100%; border-collapse:collapse; font-size:13px;">';
            foreach ($profile['views'] as $v) {
                echo '<tr style="border-bottom:1px solid rgba(255,255,255,0.03);">';
                echo '<td style="padding:10px 0; color:#cbd5e1;">' . htmlspecialchars($v['name'] ?? 'Unknown') . '</td>';
                echo '<td style="padding:10px 0; text-align:right; font-family:monospace; color:#10b981;">' . number_format($v['duration'] ?? 0, 2) . ' ms</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:#64748b; font-style:italic;">No templates were rendered.</p>';
        }
        echo '</div>';

        echo '</div>'; // End grid and tab-app
        echo '</div>'; // End tab-app
        echo '<div id="tab-files" class="plugs-dbg-tab-content" style="padding: 32px;">' . self::renderFilesTab($profile, $nonce) . '</div>';
        echo '<div id="tab-history" class="plugs-dbg-tab-content" style="padding: 32px;">' . self::renderHistoryTab($nonce) . '</div>';
        echo '<div id="tab-config" class="plugs-dbg-tab-content" style="padding: 32px;">' . self::renderConfigTab($profile) . '</div>';

        echo '</div></div>'; // End content and wrapper

        $dataJson = json_encode($data);
        echo <<<JS
<script{$nonceAttr}>
    (function() {
        const toggleBtn = document.getElementById('plugs-profiler-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const modal = document.getElementById('plugs-profiler-modal');
                if (modal) modal.classList.toggle('active');
            });
        }

        const closeBarBtn = document.getElementById('plugs-profiler-close');
        if (closeBarBtn) {
            closeBarBtn.addEventListener('click', function() {
                document.getElementById('plugs-profiler-bar')?.remove();
                document.getElementById('plugs-profiler-modal')?.remove();
            });
        }

        const closeModalBtn = document.getElementById('plugs-profiler-modal-close');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                document.getElementById('plugs-profiler-modal')?.classList.remove('active');
            });
        }

        const clearHistoryBtn = document.getElementById('plugs-clear-history');
        if (clearHistoryBtn) {
            clearHistoryBtn.addEventListener('click', async function() {
                if (!confirm('Clear profiling history?')) return;
                try {
                    const response = await fetch('/plugs/profiler/clear', { method: 'POST' });
                    const result = await response.json();
                    if (result.success) { alert(result.message); location.reload(); }
                } catch (e) { console.error(e); }
            });
        }

        document.querySelectorAll('.plugs-dbg-tab-btn[data-tab]').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                const wrapper = this.closest('.plugs-debug-wrapper');
                wrapper.querySelectorAll('.plugs-dbg-tab-btn').forEach(b => b.classList.remove('active'));
                wrapper.querySelectorAll('.plugs-dbg-tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                wrapper.querySelector('#' + tabId).classList.add('active');
            });
        });

        const aiModal = document.createElement('div');
        aiModal.id = 'plugs-ai-insights-modal';
        aiModal.style.cssText = 'display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:600px; max-width:90%; background:#1e293b; border:2px solid #8b5cf6; border-radius:12px; z-index:1000000; box-shadow:0 10px 40px rgba(0,0,0,0.5); padding:24px; color:#f8fafc; font-family:sans-serif;';
        aiModal.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; color:#a78bfa; font-size:18px; display:flex; align-items:center; gap:8px;">✨ <span>AI Insights</span></h3>
                <button onclick="this.closest('#plugs-ai-insights-modal').style.display='none'" style="background:none; border:none; color:#64748b; cursor:pointer; font-size:20px;">✕</button>
            </div>
            <div id="plugs-ai-content" style="line-height:1.6; font-size:14px; max-height:400px; overflow-y:auto; padding-right:8px; color: #cbd5e1;"></div>
            <div style="margin-top:24px; text-align:right;">
                <button onclick="this.closest('#plugs-ai-insights-modal').style.display='none'" style="background:#334155; color:white; border:none; padding:8px 24px; border-radius:6px; cursor:pointer; font-weight:600;">Dismiss</button>
            </div>
        `;
        document.body.appendChild(aiModal);

        const showAiInsight = (title, content) => {
            aiModal.querySelector('h3 span').textContent = title;
            document.getElementById('plugs-ai-content').innerHTML = content.replace(/\\n/g, '<br>').replace(/\\*\\*(.*?)\\*\\*/g, '<strong>$1</strong>');
            aiModal.style.display = 'block';
        };

        const showLoading = (title) => {
            aiModal.querySelector('h3 span').textContent = title;
            document.getElementById('plugs-ai-content').innerHTML = '<div style="display:flex; align-items:center; gap:12px; height:100px; justify-content:center; color:#94a3b8;"><svg class="plugs-dbg-animate-spin" width="24" height="24" fill="none" viewBox="0 0 24 24"><circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> AI is analyzing...</div>';
            aiModal.style.display = 'block';
        };

        const analyzeBtn = document.getElementById('plugs-ai-analyze-request');
        if (analyzeBtn) {
            analyzeBtn.addEventListener('click', async function() {
                showLoading('Performance Analysis');
                try {
                    const response = await fetch('/plugs/profiler/analyze-request', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ profile: {$dataJson} })
                    });
                    const result = await response.json();
                    if (result.success) showAiInsight('Performance Analysis', result.analysis);
                    else showAiInsight('Analysis Failed', '❌ ' + (result.error || 'Unknown error'));
                } catch (e) { showAiInsight('Error', '❌ Connection failed.'); }
            });
        }

        document.addEventListener('click', async function(e) {
            if (e.target.classList.contains('plugs-ai-explain-sql')) {
                e.preventDefault();
                showLoading('SQL Insights');
                try {
                    const response = await fetch('/plugs/profiler/analyze-sql', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ sql: e.target.getAttribute('data-sql') })
                    });
                    const result = await response.json();
                    if (result.success) showAiInsight('SQL Insights', result.analysis);
                    else showAiInsight('Explain Failed', '❌ ' + (result.error || 'Unknown error'));
                } catch (e) { showAiInsight('Error', '❌ Connection failed.'); }
            }
        });

        const style = document.createElement('style');
        style.textContent = '.plugs-dbg-animate-spin { animation: plugsDbgSpin 1s linear infinite; } @keyframes plugsDbgSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
    })();
</script>
JS;

        return ob_get_clean();
    }

    private static function renderTimelineTab(array $profile): string
    {
        $timeline = $profile['timeline'] ?? [];
        if (empty($timeline))
            return '<p style="color:#94a3b8;">No timeline data available.</p>';
        $totalDuration = max($profile['duration'] ?? 1, 1);
        $html = '<div class="timeline-v2" style="display:flex; flex-direction:column; gap:16px;">';
        foreach ($timeline as $name => $segment) {
            if (!isset($segment['duration']))
                continue;
            $percentage = min(100, ($segment['duration'] / $totalDuration) * 100);
            $relativeStart = ($segment['start'] - ($profile['timeline']['total']['start'] ?? $segment['start'])) * 1000;
            $offsetPercent = min(100, ($relativeStart / $totalDuration) * 100);
            $color = '#8b5cf6';
            if (str_contains(strtolower($name), 'middleware'))
                $color = '#f59e0b';
            if (str_contains(strtolower($name), 'routing'))
                $color = '#3b82f6';
            if (str_contains(strtolower($name), 'view'))
                $color = '#10b981';
            $html .= sprintf('<div class="timeline-row" style="display:flex; align-items:center; gap:24px; padding: 12px; border-radius: 8px;">
                <div style="width:240px; font-size:13px;"><span style="color:#f8fafc; font-weight:500;">%s</span><br><span style="color:#94a3b8; font-size:11px;">%s ms</span></div>
                <div style="flex:1; height:10px; background:rgba(255,255,255,0.05); border-radius:10px; position:relative;">
                    <div style="position:absolute; height:100%%; background:%s; border-radius:10px; width:%s%%; left:%s%%; box-shadow:0 0 10px %s;"></div>
                </div></div>', htmlspecialchars($segment['label']), number_format($segment['duration'], 2), $color, number_format($percentage, 2), number_format($offsetPercent, 2), $color);
        }
        return $html . '</div>';
    }

    private static function renderFilesTab(array $profile, ?string $nonce = null): string
    {
        $files = $profile['files']['list'] ?? [];
        if (empty($files)) {
            return '<div style="text-align:center; padding:60px; color:#94a3b8;"><div style="font-size:40px; margin-bottom:16px;">📂</div>No file list captured. Ensure <code>opcache</code> is not preventing file tracing.</div>';
        }

        $nonceAttr = $nonce ? ' nonce="' . $nonce . '"' : '';
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        $groups = ['🚀 App' => [], '📦 Vendor' => [], '⚡ Framework' => [], '📁 Other' => []];

        foreach ($files as $file) {
            $displayFile = $basePath ? str_replace($basePath, '', $file) : $file;
            $isVendor = str_contains($displayFile, 'vendor');
            $isFramework = str_contains($displayFile, 'plugs/src') || str_contains($displayFile, 'Plugs\\');

            $fileInfo = ['path' => $displayFile];

            if ($isFramework)
                $groups['⚡ Framework'][] = $fileInfo;
            elseif ($isVendor)
                $groups['📦 Vendor'][] = $fileInfo;
            elseif (str_contains($displayFile, 'app/'))
                $groups['🚀 App'][] = $fileInfo;
            else
                $groups['📁 Other'][] = $fileInfo;
        }

        $html = '<div class="plugs-file-dashboard" style="padding: 32px; display: flex; flex-direction: column; gap: 24px;">';

        // Search & Stats Header
        $html .= '<div style="display: flex; gap: 20px; align-items: center; background: rgba(255,255,255,0.02); padding: 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">';
        $html .= '<div style="flex: 1;"><input type="text" placeholder="Filter ' . count($files) . ' included files..." id="pbar-file-filter" style="width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.3); border: 1px solid rgba(139, 92, 246, 0.3); color: white; border-radius: 8px; font-family: \'JetBrains Mono\', monospace; font-size: 13px; outline: none;"></div>';
        $html .= '<div style="text-align: center; padding: 0 20px; border-left: 1px solid rgba(255,255,255,0.1);"><div style="color: #94a3b8; font-size: 10px; text-transform: uppercase;">Total Files</div><div style="color: #a78bfa; font-size: 18px; font-weight: 600;">' . count($files) . '</div></div>';
        $html .= '</div>';

        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(600px, 1fr)); gap: 24px;">';

        foreach ($groups as $groupName => $groupFiles) {
            if (empty($groupFiles))
                continue;
            $html .= '<div class="pbar-file-group" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px;">';
            $html .= '<h4 style="color: #cbd5e1; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; font-size: 15px;">';
            $html .= '<span>' . $groupName . '</span>';
            $html .= '<span style="font-size: 11px; background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 100px;">' . count($groupFiles) . '</span>';
            $html .= '</h4>';

            $html .= '<div class="file-list-container">';
            foreach ($groupFiles as $f) {
                $html .= '<div class="pbar-file-item" style="padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: \'JetBrains Mono\', monospace; font-size: 11px; color: #94a3b8; word-break: break-all;">';
                $html .= '<span class="pbar-file-path">' . htmlspecialchars($f['path']) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>'; // End grid

        $html .= '<script' . $nonceAttr . '>
            document.getElementById("pbar-file-filter")?.addEventListener("input", function(e) {
                const val = e.target.value.toLowerCase();
                document.querySelectorAll(".pbar-file-item").forEach(el => {
                    const path = el.querySelector(".pbar-file-path").textContent.toLowerCase();
                    el.style.display = path.includes(val) ? "block" : "none";
                });
                
                // Hide empty groups
                document.querySelectorAll(".pbar-file-group").forEach(group => {
                    const visibleItems = group.querySelectorAll(".pbar-file-item[style*=\'display: block\'], .pbar-file-item:not([style*=\'display: none\'])").length;
                    group.style.display = visibleItems > 0 ? "block" : "none";
                });
            });
        </script></div>';
        return $html;
    }

    private static function renderHistoryTab(?string $nonce = null): string
    {
        $profiles = Profiler::getProfiles(50);
        $html = '<div class="plugs-dbg-history-management" style="padding:20px;">';
        $html .= '<div style="display:flex; justify-content:space-between; margin-bottom:20px;"><h3 style="color:#f8fafc;">Recent Requests</h3><button id="plugs-clear-history" class="plugs-dbg-action-btn" style="background:#ef4444; border-color:#ef4444; color:white;">Clear All</button></div>';

        if (empty($profiles))
            return $html . '<p style="color:#64748b;">No history found.</p></div>';

        $html .= '<div style="overflow-x:auto;"><table class="plugs-dbg-info-table" style="width:100%; border-collapse:collapse; font-size:13px;">';
        $html .= '<thead style="background:rgba(255,255,255,0.03);"><tr><th style="padding:10px; text-align:left;">Method</th><th style="padding:10px; text-align:left;">URI</th><th style="padding:10px; text-align:left;">Time</th><th style="padding:10px; text-align:left;">Status</th></tr></thead><tbody>';

        foreach ($profiles as $p) {
            $html .= sprintf('<tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                <td style="padding:10px;"><span class="plugs-dbg-badge">%s</span></td>
                <td style="padding:10px; color:#f8fafc; font-family:monospace;">%s</td>
                <td style="padding:10px; color:#94a3b8;">%s ms</td>
                <td style="padding:10px; color:#10b981;">%d</td>
            </tr>', htmlspecialchars($p['request']['method'] ?? 'GET'), htmlspecialchars($p['request']['uri'] ?? '/'), number_format($p['duration'] ?? 0, 2), $p['request']['status_code'] ?? 200);
        }
        return $html . '</tbody></table></div></div>';
    }

    private static function renderConfigTab(array $profile): string
    {
        $groups = [
            '🚀 Environment' => [
                'Environment' => config('app.env', 'production'),
                'Debug Mode' => config('app.debug') ? 'Enabled' : 'Disabled',
                'Timezone' => config('app.timezone') ?? date_default_timezone_get(),
                'Framework' => 'Plugs (v1.2.0)', // Mock version or pull if available
                'Locale' => config('app.locale', 'en_US'),
                'URL' => config('app.url', 'localhost'),
            ],
            '🐘 PHP Settings' => [
                'Version' => PHP_VERSION,
                'Memory Limit' => ini_get('memory_limit'),
                'Max Execution Time' => ini_get('max_execution_time') . 's',
                'Upload Max Filesize' => ini_get('upload_max_filesize'),
                'Post Max Size' => ini_get('post_max_size'),
                'OPcache' => extension_loaded('Zend OPcache') ? 'Enabled' : 'Disabled',
                'Xdebug' => extension_loaded('xdebug') ? 'Enabled' : 'Disabled',
            ],
            '🖥️ Server Info' => [
                'OS' => PHP_OS,
                'SAPI' => PHP_SAPI,
                'Interface' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'Protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'N/A',
                'IP Address' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            ]
        ];

        $html = '<div class="pbar-config-dashboard" style="padding: 32px; display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px;">';

        foreach ($groups as $title => $items) {
            $html .= '<div class="pbar-config-group" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">';
            $html .= '<h3 style="color: #a78bfa; font-size: 16px; margin-bottom: 20px; border-bottom: 1px solid rgba(167, 139, 250, 0.2); padding-bottom: 12px;">' . $title . '</h3>';
            $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';

            foreach ($items as $label => $value) {
                $valClass = '';
                if ($value === 'Enabled' || $value === 'production')
                    $valClass = 'color: #10b981;';
                if ($value === 'Disabled' || $value === 'local')
                    $valClass = 'color: #f59e0b;';

                $html .= '<tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.03);">';
                $html .= '<td style="padding: 10px 0; color: #94a3b8; width: 140px;">' . $label . '</td>';
                $html .= '<td style="padding: 10px 0; color: #f8fafc; font-family: monospace; text-align: right; ' . $valClass . '">' . htmlspecialchars((string) $value) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table></div>';
        }

        $html .= '</div>';

        // Git Section if available
        if (!empty($profile['git']['branch'])) {
            $html .= '<div style="margin: 0 32px 32px; padding: 20px; background: rgba(139, 92, 246, 0.05); border: 1px dashed rgba(139, 92, 246, 0.3); border-radius: 12px; display: flex; align-items: center; justify-content: space-between;">';
            $html .= '<div style="display: flex; align-items: center; gap: 12px;">';
            $html .= '<div style="font-size: 24px;">🌿</div>';
            $html .= '<div>';
            $html .= '<div style="color: #a78bfa; font-weight: 600; font-size: 14px;">Git Repository Info</div>';
            $html .= '<div style="color: #94a3b8; font-size: 12px;">Working on branch <strong>' . htmlspecialchars($profile['git']['branch']) . '</strong></div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div style="font-family: monospace; font-size: 12px; color: #cbd5e1; background: rgba(0,0,0,0.2); padding: 6px 12px; border-radius: 6px;">' . ($profile['git']['hash'] ?? 'N/A') . '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    private static function renderRouteTab(array $profile): string
    {
        $html = '<div style="padding:32px; background:rgba(255,255,255,0.02); border-radius:12px; margin:20px;">';
        $html .= '<h3 style="color:#60a5fa; margin-bottom:16px;">Route Information</h3>';
        $html .= '<table style="width:100%; border-collapse:collapse;">';
        $details = [
            'Name' => $profile['request']['route'] ?? 'unnamed',
            'Controller' => $profile['request']['controller'] ?? 'Closure',
            'Method' => $profile['request']['method'] ?? 'GET',
            'URI' => $profile['request']['uri'] ?? '/',
        ];
        foreach ($details as $k => $v) {
            $html .= sprintf('<tr style="border-bottom:1px solid rgba(255,255,255,0.05);"><td style="padding:12px; color:#94a3b8; width:150px;">%s</td><td style="padding:12px; color:#f8fafc; font-family:monospace;">%s</td></tr>', $k, htmlspecialchars((string) $v));
        }
        return $html . '</table></div>';
    }

    private static function renderRequestTab(array $profile): string
    {
        $request = $profile['request'] ?? [];
        $html = '<div class="plugs-request-dashboard" style="display: flex; flex-direction: column; gap: 32px;">';

        // 1. Overview Card
        $html .= '<div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 12px; padding: 24px;">';
        $html .= '<h3 style="color: #60a5fa; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">🌐 Request Overview</h3>';
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">';
        $overview = [
            'Method' => '<span class="plugs-badge" style="background: rgba(139, 92, 246, 0.1); color: #a78bfa;">' . ($request['method'] ?? 'GET') . '</span>',
            'URI' => '<span style="color: #cbd5e1; font-family: monospace;">' . htmlspecialchars($request['uri'] ?? '/') . '</span>',
            'Protocol' => '<span style="color: #94a3b8;">' . ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . '</span>',
            'IP Address' => '<span style="color: #94a3b8;">' . ($request['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . '</span>',
        ];
        foreach ($overview as $label => $val) {
            $html .= '<div><div style="color: #64748b; font-size: 11px; text-transform: uppercase; margin-bottom: 4px;">' . $label . '</div><div style="font-size: 14px;">' . $val . '</div></div>';
        }
        $html .= '</div></div>';

        // 2. Main Content Grid
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">';

        // Headers
        $html .= self::renderRequestCard('Headers', $request['headers'] ?? [], '📥');

        // Parameters (Combined Query & Input)
        $params = array_merge($request['query'] ?? [], $request['input'] ?? $request['post'] ?? []);
        $html .= self::renderRequestCard('Parameters', $params, '🔍');

        // Cookies
        $html .= self::renderRequestCard('Cookies', $request['cookies'] ?? [], '🍪');

        // Session
        $html .= self::renderRequestCard('Session', $request['session'] ?? [], '💾');

        $html .= '</div></div>';
        return $html;
    }

    private static function renderRequestCard(string $title, array $data, string $icon): string
    {
        $html = '<div class="request-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px;">';
        $html .= '<h4 style="color: #cbd5e1; margin-bottom: 16px; font-size: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">';
        $html .= '<span>' . $icon . ' ' . $title . '</span>';
        $html .= '<span style="font-size: 11px; color: #64748b; background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 100px; margin-left: auto;">' . count($data) . ' items</span>';
        $html .= '</h4>';

        if (empty($data)) {
            $html .= '<div style="color: #64748b; font-style: italic; font-size: 13px; text-align: center; padding: 20px;">No ' . strtolower($title) . ' found.</div>';
        } else {
            $html .= '<div style="display: flex; flex-direction: column; gap: 8px;">';
            foreach ($data as $key => $value) {
                $html .= '<div style="display: flex; gap: 12px; font-size: 12px; align-items: flex-start; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.02);">';
                $html .= '<div style="color: #94a3b8; font-family: monospace; min-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' . htmlspecialchars((string) $key) . '">' . htmlspecialchars((string) $key) . '</div>';

                $finalValue = is_array($value) ? '<span style="color: #8b5cf6;">Array(' . count($value) . ')</span>' : htmlspecialchars((string) $value);
                $html .= '<div style="color: #f8fafc; font-family: monospace; word-break: break-all; flex: 1;">' . $finalValue . '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function injectIntoHtml(string $html, array $profile, ?string $nonce = null): string
    {
        if (stripos($html, '</body>') === false)
            return $html;
        return str_ireplace('</body>', self::render($profile, $nonce) . '</body>', $html);
    }

    private static function renderCacheTab(array $profile): string
    {
        $cache = $profile['cache'] ?? ['hits' => 0, 'misses' => 0, 'keys' => []];
        $total = $cache['hits'] + $cache['misses'];
        $hitRate = $total > 0 ? round(($cache['hits'] / $total) * 100, 1) : 0;

        $html = '<div class="plugs-cache-dashboard">';
        $html .= '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px;">';
        $stats = [
            'Hits' => ['val' => $cache['hits'], 'color' => '#10b981'],
            'Misses' => ['val' => $cache['misses'], 'color' => '#ef4444'],
            'Hit Rate' => ['val' => $hitRate . '%', 'color' => '#3b82f6'],
        ];
        foreach ($stats as $label => $s) {
            $html .= sprintf('<div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); text-align: center;">
                <div style="color: #64748b; font-size: 11px; text-transform: uppercase;">%s</div>
                <div style="font-size: 24px; font-weight: 700; color: %s; margin-top: 8px;">%s</div>
            </div>', $label, $s['color'], $s['val']);
        }
        $html .= '</div>';

        $html .= '<h3 style="color: #cbd5e1; margin-bottom: 16px; font-size: 16px;">Cache Operations</h3>';
        if (empty($cache['keys'])) {
            $html .= '<p style="color: #64748b; font-style: italic;">No cache operations recorded.</p>';
        } else {
            $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
            $html .= '<thead style="background: rgba(255,255,255,0.03);"><tr><th style="padding: 12px; text-align: left;">Key</th><th style="padding: 12px; text-align: left;">Status</th><th style="padding: 12px; text-align: right;">Time</th></tr></thead><tbody>';
            foreach ($cache['keys'] as $k) {
                $statusColor = $k['hit'] ? '#10b981' : '#ef4444';
                $statusText = $k['hit'] ? 'HIT' : 'MISS';
                $html .= sprintf('<tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                    <td style="padding: 12px; color: #f8fafc; font-family: monospace;">%s</td>
                    <td style="padding: 12px;"><span class="plugs-badge" style="background: %s15; color: %s; border: 1px solid %s30;">%s</span></td>
                    <td style="padding: 12px; text-align: right; color: #94a3b8; font-family: monospace;">+%s ms</td>
                </tr>', htmlspecialchars($k['key']), $statusColor, $statusColor, $statusColor, $statusText, number_format($k['time_offset'] ?? 0, 2));
            }
            $html .= '</tbody></table>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderEventsTab(array $profile): string
    {
        $events = $profile['events'] ?? [];
        $html = '<div class="plugs-events-dashboard">';
        $html .= '<h3 style="color: #cbd5e1; margin-bottom: 16px; font-size: 16px;">Dispatched Events</h3>';

        if (empty($events)) {
            $html .= '<p style="color: #64748b; font-style: italic;">No events were dispatched during this request.</p>';
        } else {
            $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
            $html .= '<thead style="background: rgba(255,255,255,0.03);"><tr><th style="padding: 12px; text-align: left;">Event</th><th style="padding: 12px; text-align: right;">Duration</th><th style="padding: 12px; text-align: right;">Fired At</th></tr></thead><tbody>';
            foreach ($events as $e) {
                $html .= sprintf('<tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                    <td style="padding: 12px; color: #f8fafc; font-weight: 500;">%s</td>
                    <td style="padding: 12px; text-align: right; color: #f59e0b; font-family: monospace;">%s ms</td>
                    <td style="padding: 12px; text-align: right; color: #94a3b8; font-family: monospace;">+%s ms</td>
                </tr>', htmlspecialchars($e['name']), number_format($e['duration'] ?? 0, 2), number_format($e['time_offset'] ?? 0, 2));
            }
            $html .= '</tbody></table>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function getDurationClass(float $duration): string
    {
        return $duration > 1000 ? 'pbar-slow' : ($duration > 200 ? 'pbar-medium' : 'pbar-fast');
    }
    private static function getStatusClass(int $status): string
    {
        return $status >= 500 ? 'pbar-error' : ($status >= 400 ? 'pbar-warning' : 'pbar-success');
    }
}
