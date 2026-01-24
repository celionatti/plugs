<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Profiler Dashboard Controller
 *
 * Provides routes for viewing and managing profiler data.
 */
class ProfilerController
{
    /**
     * Display profiler dashboard with profile list
     */
    public function index(ServerRequestInterface $request)
    {
        $profiles = Profiler::getProfiles(50);

        return ResponseFactory::html($this->renderDashboard($profiles));
    }

    /**
     * Display single profile detail
     */
    public function show(ServerRequestInterface $request, string $id)
    {
        $profile = Profiler::getProfile($id);
        if (!$profile) {
            return ResponseFactory::html($this->render404(), 404);
        }

        return ResponseFactory::html($this->renderProfile($profile));
    }

    /**
     * Delete a single profile
     */
    public function destroy(ServerRequestInterface $request, string $id)
    {
        Profiler::deleteProfile($id);

        return ResponseFactory::json(['success' => true, 'message' => 'Profile deleted']);
    }

    /**
     * Clear all profiles
     */
    public function clear(ServerRequestInterface $request)
    {
        $count = Profiler::clearProfiles();

        return ResponseFactory::json(['success' => true, 'deleted' => $count]);
    }

    /**
     * Get profile data as JSON (for toolbar)
     */
    public function latest(ServerRequestInterface $request)
    {
        $profiles = Profiler::getProfiles(1);

        return ResponseFactory::json($profiles[0] ?? null);
    }

    /**
     * Render the dashboard HTML
     */
    private function renderDashboard(array $profiles): string
    {
        $profileRows = '';
        foreach ($profiles as $profile) {
            $statusClass = $this->getStatusClass((int) ($profile['request']['status_code'] ?? 200));
            $durationClass = $this->getDurationClass((float) $profile['duration']);
            $queryCount = $profile['database']['query_count'] ?? 0;

            $profileRows .= sprintf(
                '
                <tr class="profile-row" onclick="window.location.href=\'/plugs/profiler/%s\'">
                    <td class="col-time">%s</td>
                    <td class="col-method"><span class="method-badge method-%s">%s</span></td>
                    <td class="col-path">%s</td>
                    <td class="col-status"><span class="status-badge %s">%s</span></td>
                    <td class="col-duration %s">%s ms</td>
                    <td class="col-memory">%s</td>
                    <td class="col-queries">%d</td>
                    <td class="col-actions">
                        <button class="btn-icon" onclick="event.stopPropagation(); deleteProfile(\'%s\')" title="Delete">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </td>
                </tr>',
                htmlspecialchars((string) $profile['id']),
                htmlspecialchars((string) $profile['datetime']),
                strtolower((string) ($profile['request']['method'] ?? 'GET')),
                htmlspecialchars((string) ($profile['request']['method'] ?? 'GET')),
                htmlspecialchars((string) ($profile['request']['path'] ?? '/')),
                $statusClass,
                $profile['request']['status_code'] ?? 200,
                $durationClass,
                number_format((float) $profile['duration'], 2),
                htmlspecialchars($profile['memory']['peak_formatted'] ?? ($profile['memory_peak_formatted'] ?? '0 B')),
                $queryCount,
                htmlspecialchars((string) $profile['id'])
            );
        }

        return $this->getBaseHtml('Profiler Dashboard', sprintf(
            '
            <div class="dashboard-header animate-fade-in">
                <div>
                    <h1>Profiler Dashboard</h1>
                    <p class="text-muted">Analyze your application performance and debug requests.</p>
                </div>
                <div class="header-actions">
                    <span class="profile-count">%d profiles</span>
                    <button class="btn btn-danger" onclick="clearAllProfiles()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Clear All
                    </button>
                </div>
            </div>
            
            <div class="table-container animate-fade-in">
                <table class="profiles-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Method</th>
                            <th>Path</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Memory</th>
                            <th>Queries</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        %s
                    </tbody>
                </table>
                %s
            </div>
            
            <script>
                const csrfToken = document.querySelector(\'meta[name="csrf-token"]\')?.getAttribute(\'content\');

                async function deleteProfile(id) {
                    if (!confirm("Delete this profile?")) return;
                    try {
                        const response = await fetch("/plugs/profiler/" + id, { 
                            method: "DELETE",
                            headers: { "X-CSRF-TOKEN": csrfToken, "Accept": "application/json" }
                        });
                        if (response.ok) { location.reload(); }
                        else { alert("Failed to delete"); }
                    } catch (e) { alert(e.message); }
                }
                
                async function clearAllProfiles() {
                    if (!confirm("Delete all profiles?")) return;
                    try {
                        const response = await fetch("/plugs/profiler/clear", { 
                            method: "POST",
                            headers: { "X-CSRF-TOKEN": csrfToken, "Accept": "application/json" }
                        });
                        if (response.ok) { location.reload(); }
                        else { alert("Failed to clear"); }
                    } catch (e) { alert(e.message); }
                }
            </script>',
            count($profiles),
            $profileRows,
            empty($profiles) ? '<div class="empty-state"><p>No profiles recorded yet.</p></div>' : ''
        ));
    }

    /**
     * Render single profile detail
     */
    private function renderProfile(array $profile): string
    {
        $duplicateQueries = $this->getDuplicateQueries($profile['database']['queries'] ?? []);

        $tabs = [
            'request' => 'Request',
            'database' => 'Database (' . ($profile['database']['query_count'] ?? 0) . ')',
            'timeline' => 'Timeline',
            'app' => 'Application',
            'config' => 'Framework',
        ];

        $tabContent = '';
        foreach ($tabs as $id => $label) {
            $tabContent .= sprintf('<div id="tab-%s" class="tab-pane">%s</div>', $id, $this->renderTab($id, $profile, $duplicateQueries));
        }

        $tabNav = '';
        foreach ($tabs as $id => $label) {
            $tabNav .= sprintf('<button class="tab-btn" onclick="openTab(\'%s\')">%s</button>', $id, $label);
        }

        return $this->getBaseHtml('Profile: ' . $profile['request']['path'], sprintf(
            '
            <div class="profile-header animate-fade-in">
                <a href="/plugs/profiler" class="back-link">← Back to Dashboard</a>
                <div class="profile-title">
                    <span class="method-badge method-%s">%s</span>
                    <h1>%s</h1>
                </div>
                <div class="profile-meta">
                    <span class="meta-item">%s</span>
                    <span class="meta-item">Status: <span class="%s">%s</span></span>
                    <span class="meta-item">IP: %s</span>
                </div>
            </div>
            
            <div class="stats-grid animate-fade-in">
                <div class="stat-card">
                    <div class="stat-label">Total Duration</div>
                    <div class="stat-value %s">%s ms</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Memory Peak</div>
                    <div class="stat-value">%s</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Database Queries</div>
                    <div class="stat-value">%d</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Included Files</div>
                    <div class="stat-value">%d</div>
                </div>
            </div>
            
            <div class="tab-container animate-fade-in">
                <div class="tab-header">%s</div>
                <div class="tab-body">%s</div>
            </div>
            
            <script>
                function openTab(tabId) {
                    document.querySelectorAll(".tab-pane").forEach(p => p.classList.remove("active"));
                    document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
                    document.getElementById("tab-" + tabId).classList.add("active");
                    document.querySelector(`button[onclick="openTab(\'${tabId}\')"]`).classList.add("active");
                    localStorage.setItem("plugs_profiler_tab", tabId);
                }
                const savedTab = localStorage.getItem("plugs_profiler_tab") || "request";
                openTab(savedTab);
            </script>',
            strtolower((string) ($profile['request']['method'] ?? 'GET')),
            htmlspecialchars((string) ($profile['request']['method'] ?? 'GET')),
            htmlspecialchars((string) ($profile['request']['path'] ?? '/')),
            htmlspecialchars((string) ($profile['datetime'] ?? '')),
            $this->getStatusClass((int) ($profile['request']['status_code'] ?? 200)),
            $profile['request']['status_code'] ?? 200,
            htmlspecialchars((string) ($profile['request']['ip'] ?? 'unknown')),
            $this->getDurationClass((float) $profile['duration']),
            number_format((float) $profile['duration'], 2),
            htmlspecialchars($profile['memory']['peak_formatted'] ?? ($profile['memory_peak_formatted'] ?? '0 B')),
            $profile['database']['query_count'] ?? 0,
            $profile['files']['count'] ?? 0,
            $tabNav,
            $tabContent
        ));
    }

    private function renderTab(string $id, array $profile, array $duplicateQueries): string
    {
        switch ($id) {
            case 'request':
                return $this->renderRequestTab($profile);
            case 'database':
                return $this->renderDatabaseTab($profile, $duplicateQueries);
            case 'timeline':
                return $this->renderTimelineTab($profile);
            case 'app':
                return $this->renderAppTab($profile);
            case 'config':
                return $this->renderConfigTab($profile);
            default:
                return '';
        }
    }

    private function renderRequestTab(array $profile): string
    {
        $html = '<div class="tab-grid">';

        $html .= '<div class="info-group"><h3>General</h3>' . $this->renderTable([
            'Method' => $profile['request']['method'] ?? 'N/A',
            'Path' => $profile['request']['path'] ?? '/',
            'URL' => $profile['request']['uri'] ?? 'N/A',
            'IP Address' => $profile['request']['ip'] ?? 'unknown',
            'Time' => $profile['datetime'] ?? 'unknown',
        ]) . '</div>';

        if (!empty($profile['request']['body'])) {
            $html .= '<div class="info-group"><h3>Request Body</h3><pre class="code-block">' . htmlspecialchars(json_encode($profile['request']['body'], JSON_PRETTY_PRINT)) . '</pre></div>';
        }

        if (!empty($profile['request']['headers'])) {
            $html .= '<div class="info-group"><h3>Request Headers</h3>' . $this->renderTable($profile['request']['headers']) . '</div>';
        }

        if (!empty($profile['response']['headers'])) {
            $html .= '<div class="info-group"><h3>Response Headers</h3>' . $this->renderTable($profile['response']['headers']) . '</div>';
        }

        if (!empty($profile['request']['session'])) {
            $html .= '<div class="info-group"><h3>Session</h3>' . $this->renderTable($profile['request']['session']) . '</div>';
        }

        if (!empty($profile['request']['cookies'])) {
            $html .= '<div class="info-group"><h3>Cookies</h3>' . $this->renderTable($profile['request']['cookies']) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function renderDatabaseTab(array $profile, array $duplicateQueries): string
    {
        $queries = $profile['database']['queries'] ?? [];
        if (empty($queries)) {
            return '<p class="empty">No database queries recorded.</p>';
        }

        $html = '<div class="db-stats">';
        if (!empty($duplicateQueries)) {
            $html .= sprintf('<div class="db-alert warning"><strong>%d Duplicate Queries Detected!</strong> This might indicate an N+1 problem.</div>', count($duplicateQueries));
        }
        $html .= '</div>';

        foreach ($queries as $i => $query) {
            $isSlow = ($query['time'] ?? 0) > 0.1; // 100ms
            $sqlHash = md5($query['query'] ?? '');
            $isDuplicate = isset($duplicateQueries[$sqlHash]);

            $html .= sprintf(
                '
                <div class="query-item %s %s">
                    <div class="query-header">
                        <span class="query-number">#%d</span>
                        <div class="query-meta">
                            %s %s
                            <span class="query-time %s">%s ms</span>
                        </div>
                    </div>
                    <pre class="query-sql sql-highlight">%s</pre>
                </div>',
                $isSlow ? 'slow' : '',
                $isDuplicate ? 'duplicate' : '',
                $i + 1,
                $isSlow ? '<span class="badge badge-warning">Slow</span>' : '',
                $isDuplicate ? '<span class="badge badge-danger">Duplicate</span>' : '',
                $isSlow ? 'text-danger' : 'text-accent',
                number_format((float) ($query['time'] ?? 0) * 1000, 2),
                htmlspecialchars($query['query'] ?? '')
            );
        }

        return $html;
    }

    private function renderTimelineTab(array $profile): string
    {
        $timeline = $profile['timeline'] ?? [];
        if (empty($timeline)) {
            return '<p class="empty">No timeline data.</p>';
        }

        $html = '<div class="timeline-v2">';
        $totalDuration = max($profile['duration'] ?? 1, 1);

        foreach ($timeline as $name => $segment) {
            if ($segment['duration'] === null) {
                continue;
            }
            $percentage = min(100, ($segment['duration'] / $totalDuration) * 100);
            $startOffset = 0; // Simplified for now

            $html .= sprintf(
                '
                <div class="timeline-row">
                    <div class="timeline-info">
                        <span class="timeline-name">%s</span>
                        <span class="timeline-val">%s ms</span>
                    </div>
                    <div class="timeline-track">
                        <div class="timeline-bar" style="width: %s%%; left: %s%%"></div>
                    </div>
                </div>',
                htmlspecialchars($segment['label']),
                number_format((float) $segment['duration'], 2),
                number_format($percentage, 1),
                number_format($startOffset, 1)
            );
        }
        $html .= '</div>';

        return $html;
    }

    private function renderAppTab(array $profile): string
    {
        $html = '<div class="tab-grid">';

        $models = $profile['models'] ?? [];
        $views = $profile['views'] ?? [];

        $html .= '<div class="info-group"><h3>Model Events (' . count($models) . ')</h3>';
        if (empty($models)) {
            $html .= '<p class="empty">No model events.</p>';
        } else {
            $modelList = [];
            foreach ($models as $m) {
                $modelList[] = sprintf('<strong>%s</strong>: <span class="badge">%s</span> at %s ms', class_basename($m['model']), $m['event'], number_format($m['time_offset'], 2));
            }
            $html .= '<ul class="simple-list"><li>' . implode('</li><li>', $modelList) . '</li></ul>';
        }
        $html .= '</div>';

        $html .= '<div class="info-group"><h3>Views Rendered (' . count($views) . ')</h3>';
        if (empty($views)) {
            $html .= '<p class="empty">No views rendered.</p>';
        } else {
            $viewList = [];
            foreach ($views as $v) {
                $viewList[] = sprintf('<strong>%s</strong> (%s ms)', htmlspecialchars($v['name']), number_format($v['duration'], 2));
            }
            $html .= '<ul class="simple-list"><li>' . implode('</li><li>', $viewList) . '</li></ul>';
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    private function renderConfigTab(array $profile): string
    {
        return '<div class="tab-grid">
            <div class="info-group"><h3>Environment</h3>' . $this->renderTable([
                        'PHP Version' => $profile['php']['version'] ?? PHP_VERSION,
                        'SAPI' => $profile['php']['sapi'] ?? PHP_SAPI,
                        'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                        'Memory Limit' => ini_get('memory_limit'),
                    ]) . '</div>
        </div>';
    }

    private function renderTable(array $data): string
    {
        $rows = '';
        foreach ($data as $key => $value) {
            $rows .= sprintf(
                '<tr><td class="key">%s</td><td class="val">%s</td></tr>',
                htmlspecialchars((string) $key),
                is_array($value) ? '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>' : htmlspecialchars((string) $value)
            );
        }

        return '<table class="info-table">' . $rows . '</table>';
    }

    private function getDuplicateQueries(array $queries): array
    {
        $hashes = [];
        $duplicates = [];
        foreach ($queries as $q) {
            $hash = md5($q['query']);
            if (isset($hashes[$hash])) {
                $duplicates[$hash] = true;
            }
            $hashes[$hash] = true;
        }

        return $duplicates;
    }

    /**
     * Render 404 page
     */
    private function render404(): string
    {
        return $this->getBaseHtml('Profile Not Found', '
            <div class="error-page animate-fade-in">
                <h1>Profile Not Found</h1>
                <p>The requested profile could not be found.</p>
                <a href="/plugs/profiler" class="btn btn-primary">Back to Dashboard</a>
            </div>
        ');
    }

    private function getStatusClass(int $status): string
    {
        if ($status >= 500) {
            return 'status-error';
        }
        if ($status >= 400) {
            return 'status-warning';
        }
        if ($status >= 300) {
            return 'status-redirect';
        }

        return 'status-success';
    }

    private function getDurationClass(float $duration): string
    {
        if ($duration > 1000) {
            return 'duration-slow';
        }
        if ($duration > 200) {
            return 'duration-medium';
        }

        return 'duration-fast';
    }

    private function getBaseHtml(string $title, string $content): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="' . (function_exists('csrf_token') ? csrf_token() : '') . '">
    <title>' . htmlspecialchars($title) . ' | Plugs</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Dancing+Script:wght@700&display=swap");
        
        :root {
            --bg-body: #080b12;
            --bg-card: rgba(30, 41, 59, 0.4);
            --bg-card-hover: rgba(30, 41, 59, 0.6);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-primary: #8b5cf6;
            --accent-secondary: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Outfit", sans-serif;
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(139, 92, 246, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(59, 130, 246, 0.1) 0%, transparent 40%);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
        }

        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .header {
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(8, 11, 18, 0.8); backdrop-filter: blur(10px);
            position: sticky; top: 0; z-index: 100;
        }
        
        .brand {
            font-family: "Dancing Script", cursive; font-size: 1.75rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-decoration: none;
        }
        
        .main-content { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .dashboard-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; }
        .dashboard-header h1 { font-size: 2rem; font-weight: 700; }
        .header-actions { display: flex; gap: 1.5rem; align-items: center; }
        
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.2rem; border-radius: 10px; font-size: 0.9rem; font-weight: 600;
            cursor: pointer; border: none; transition: all 0.2s;
        }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.25); transform: translateY(-1px); }
        
        .table-container { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .profiles-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        .profiles-table th { text-align: left; padding: 1.2rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); }
        .profiles-table td { padding: 1.2rem; border-bottom: 1px solid var(--border-color); }
        .profile-row { cursor: pointer; transition: background 0.15s; }
        .profile-row:hover { background: var(--bg-card-hover); }

        .method-badge { padding: 0.2rem 0.6rem; border-radius: 6px; font-family: "JetBrains Mono", monospace; font-size: 0.75rem; font-weight: 700; }
        .method-get { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .method-post { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .status-badge { padding: 0.2rem 0.6rem; border-radius: 6px; font-family: "JetBrains Mono", monospace; font-size: 0.75rem; }
        .status-success { color: var(--success); }
        .status-error { color: var(--danger); font-weight: 700; }

        .tab-btn { background: transparent; border: none; color: var(--text-muted); padding: 1rem 1.5rem; font-family: inherit; font-size: 0.95rem; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab-btn:hover { color: var(--text-primary); }
        .tab-btn.active { color: var(--accent-primary); border-bottom-color: var(--accent-primary); }
        .tab-pane { display: none; padding: 2rem 0; }
        .tab-pane.active { display: block; animation: tabFade 0.3s ease-out; }
        @keyframes tabFade { from { opacity: 0; } to { opacity: 1; } }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .stat-label { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem; }
        .stat-value { font-family: "JetBrains Mono", monospace; font-size: 1.75rem; font-weight: 600; }

        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
        .info-table .key { color: var(--text-muted); font-weight: 500; width: 200px; vertical-align: top; }
        .info-table .val { font-family: "JetBrains Mono", monospace; word-break: break-all; }

        .query-item { background: rgba(0,0,0,0.25); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; border-left: 4px solid var(--accent-secondary); }
        .query-item.slow { border-left-color: var(--warning); background: rgba(245, 158, 11, 0.05); }
        .query-item.duplicate { border-left-color: var(--danger); background: rgba(239, 68, 68, 0.05); }
        .query-header { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.8rem; }
        .query-sql { color: #e2e8f0; line-height: 1.6; white-space: pre-wrap; font-size: 0.9rem; }

        .timeline-v2 { display: flex; flex-direction: column; gap: 1rem; }
        .timeline-row { display: flex; align-items: center; gap: 2rem; }
        .timeline-info { width: 250px; display: flex; justify-content: space-between; font-size: 0.85rem; }
        .timeline-track { flex: 1; height: 10px; background: rgba(255,255,255,0.05); border-radius: 5px; position: relative; }
        .timeline-bar { height: 100%; position: absolute; background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary)); border-radius: 5px; }

        .badge { padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-warning { background: var(--warning); color: #000; }
        .badge-danger { background: var(--danger); color: #fff; }
        .text-accent { color: var(--accent-primary); }
        .simple-list { list-style: none; }
        .simple-list li { margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px dashed var(--border-color); font-size: 0.9rem; }
        pre.code-block { background: #000; padding: 1rem; border-radius: 8px; font-size: 0.85rem; overflow-x: auto; color: #10b981; }
    </style>
</head>
<body>
    <header class="header">
        <a href="/" class="brand">Plugs</a>
        <nav>
            <a href="/plugs/profiler" style="color: var(--text-secondary); text-decoration: none; font-weight: 500;">Profiler Dashboard</a>
        </nav>
    </header>
    <main class="main-content">
        ' . $content . '
    </main>
</body>
</html>';
    }
}
